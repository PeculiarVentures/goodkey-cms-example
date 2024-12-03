<?php

namespace Peculiarventures\GoodkeyCms;

use Exception;

class ApiClient
{
    private $baseUrl;
    private $token;

    public function __construct($baseUrl, $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }

    private function request($method, $path, $options = [])
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);

        $defaultOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token
            ]
        ];

        if ($method === 'POST') {
            $defaultOptions[CURLOPT_POST] = true;
        } elseif ($method !== 'GET') {
            $defaultOptions[CURLOPT_CUSTOMREQUEST] = $method;
        }

        // Proper merging of options
        $finalOptions = $defaultOptions;
        foreach ($options as $key => $value) {
            if ($key === CURLOPT_HTTPHEADER && isset($defaultOptions[CURLOPT_HTTPHEADER])) {
                $finalOptions[CURLOPT_HTTPHEADER] = array_merge($defaultOptions[CURLOPT_HTTPHEADER], $value);
            } else {
                $finalOptions[$key] = $value;
            }
        }

        curl_setopt_array($ch, $finalOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [$response, $httpCode];
    }

    private function jsonRequest($method, $path, $data = null)
    {
        $options = [
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ];

        if ($data !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
            // Debug log for request data
            error_log("JSON Request Payload: " . json_encode($data));
        }

        // Debug log for headers
        error_log("JSON Request Headers: " . json_encode($options[CURLOPT_HTTPHEADER]));

        list($response, $httpCode) = $this->request($method, $path, $options);

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            $message = $error['message'] ?? 'Failed to make request';
            $code = $error['code'] ?? $httpCode;
            $statusCode = $error['statusCode'] ?? $httpCode;
            throw new Exception("Error: $message, Code: $code, Status Code: $statusCode");
        }

        return json_decode($response, true);
    }

    public function getTokenProfile()
    {
        return $this->jsonRequest('GET', '/token/profile');
    }

    public function getKey($keyId)
    {
        return $this->jsonRequest('GET', '/key/' . urlencode($keyId));
    }

    public function getCertificate($keyId, $certificateId)
    {
        return $this->jsonRequest('GET', '/key/' . urlencode($keyId) . '/certificate/' . urlencode($certificateId));
    }

    public function downloadCertificate($keyId, $certificateId)
    {
        $response = $this->jsonRequest('GET', '/key/' . urlencode($keyId) . '/certificate/' . urlencode($certificateId) . '/download');

        return $this->base64UrlDecode($response['data']);
    }

    public function createOperation($keyId)
    {
        $data = [
            'type' => 'sign',
            'algorithm' => [
                'name' => 'RSASSA-PKCS1-v1_5',
                'hash' => 'SHA-256'
            ],
            'expirationDate' => date('c', strtotime('+1 hour'))
        ];

        return $this->jsonRequest('POST', '/key/' . urlencode($keyId) . '/operation', $data);
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    public function finalizeOperation($keyId, $operationId, $data)
    {
        $requestData = [
            'data' => $this->base64UrlEncode($data)
        ];

        $result = $this->jsonRequest(
            'PATCH',
            '/key/' . urlencode($keyId) . '/operation/' . urlencode($operationId) . '/finalize',
            $requestData
        );
        if ($result['data'] !== null) {
            $result['data'] = $this->base64UrlDecode($result['data']);
        }

        return $result;
    }
}
