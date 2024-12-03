<?php

namespace Peculiarventures\GoodkeyCms;

use Exception;

/**
 * GoodKey API Client
 */
class ApiClient
{
    /** @var string */
    private string $baseUrl;

    /** @var string */
    private string $token;

    /**
     * Constructor
     * 
     * @param string $baseUrl GoodKey API base URL
     * @param string $token API token
     */
    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }

    /**
     * Makes an API request
     * 
     * @param string $method HTTP method
     * @param string $path API path
     * @param array $options cURL options
     * @throws Exception When API request fails
     */
    private function request(string $method, string $path, array $options = []): array
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

    /**
     * Makes a JSON API request
     * 
     * @param string $method HTTP method
     * @param string $path API path
     * @param mixed $data Request data
     * @return array JSON response
     * @throws Exception When API request fails
     */
    private function jsonRequest(string $method, string $path, $data = null): array
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
            // error_log("JSON Request Payload: " . json_encode($data));
        }

        // Debug log for headers
        // error_log("JSON Request Headers: " . json_encode($options[CURLOPT_HTTPHEADER]));

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

    /**
     * Gets token profile
     * 
     * @return array Token profile
     */
    public function getTokenProfile(): array
    {
        return $this->jsonRequest('GET', '/token/profile');
    }

    /**
     * Gets key
     * 
     * @param string $keyId Key ID
     * @return array Key
     */
    public function getKey(string $keyId): array
    {
        return $this->jsonRequest('GET', '/key/' . urlencode($keyId));
    }

    /**
     * Gets certificate
     * 
     * @param string $keyId Key ID
     * @param string $certificateId Certificate ID
     * @return array Certificate
     */
    public function getCertificate(string $keyId, string $certificateId): array
    {
        return $this->jsonRequest('GET', '/key/' . urlencode($keyId) . '/certificate/' . urlencode($certificateId));
    }

    /**
     * Downloads certificate
     * 
     * @param string $keyId Key ID
     * @param string $certificateId Certificate ID
     * @return string Certificate data
     */
    public function downloadCertificate(string $keyId, string $certificateId): string
    {
        $response = $this->jsonRequest('GET', '/key/' . urlencode($keyId) . '/certificate/' . urlencode($certificateId) . '/download');

        return $this->base64UrlDecode($response['data']);
    }

    /**
     * Creates a signing operation
     * 
     * @param string $keyId Key ID
     * @return array Operation
     */
    public function createOperation(string $keyId): array
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


    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Finalizes a signing operation
     * 
     * @param string $keyId Key ID
     * @param string $operationId Operation ID
     * @param string $data Data to sign
     * @return array Finalized operation
     */
    public function finalizeOperation(string $keyId, string $operationId, string $data): array
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
