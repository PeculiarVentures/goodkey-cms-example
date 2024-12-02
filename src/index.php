<?php

require __DIR__ . '/../vendor/autoload.php';

use Peculiarventures\GoodkeyCms\ApiClient;
use FG\ASN1\Universal\Sequence;
use FG\ASN1\Universal\Integer;
use FG\ASN1\Universal\ObjectIdentifier;
use FG\ASN1\Universal\OctetString;
use FG\ASN1\Universal\Set;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hash = $_POST['hash'];

    // Create CMS package and add messageDigest attribute
    $cms = createCMS($hash);

    // Sign CMS through signing service
    $signedCMS = signCMS($cms);

    // Return signed data
    header('Content-Type: application/octet-stream');
    echo $signedCMS;
}

// Function to create CMS package with messageDigest attribute
function createCMS($hash)
{
    // OID for CMS SignedData
    $signedDataOID = '1.2.840.113549.1.7.2';
    // OID for SHA256 messageDigest
    $messageDigestOID = '1.2.840.113549.1.9.4';

    // Create messageDigest attribute structure
    $messageDigestAttribute = new Sequence(
        new ObjectIdentifier($messageDigestOID),
        new Set(
            new OctetString(hex2bin($hash))
        )
    );

    // Create SignedAttributes
    $signedAttributes = new Set([$messageDigestAttribute]);

    // Get SignedAttributes signature
    $signatureValue = signCMS($signedAttributes->getBinary());

    // Create DER certificate from PEM
    global $cert;

    // Create certificates set
    $certificateSet = new Set([
        new Sequence(bin2hex($cert))
    ]);

    // Create SignerInfo with issuer and serial from certificate
    global $certData;
    $issuerAndSerial = new Sequence([
        // Create issuer name from certificate data
        new Sequence($certData['issuer']),
        new Integer($certData['serialNumber'])
    ]);

    $signerInfo = new Sequence([
        new Integer(1), // version
        $issuerAndSerial,
        // issuerAndSerial will be added during signing
        new ObjectIdentifier('2.16.840.1.101.3.4.2.1'), // SHA256 algorithm
        $signedAttributes,
        new ObjectIdentifier('1.2.840.113549.1.1.1'), // RSA algorithm
        new OctetString($signatureValue)
    ]);

    // Create SignedData structure
    $signedData = new Sequence([
        new Integer(1), // version
        // digestAlgorithms
        new Set([
            new Sequence([
                new ObjectIdentifier('2.16.840.1.101.3.4.2.1') // SHA256
            ])
        ]),
        // contentInfo
        new Sequence([
            new ObjectIdentifier('1.2.840.113549.1.7.1'), // Data
            // actual content is detached
        ]),
        $certificateSet, // Add certificates before signerInfos
        new Set([$signerInfo]) // signerInfos
    ]);

    // Create root ContentInfo structure
    $contentInfo = new Sequence([
        new ObjectIdentifier($signedDataOID),
        $signedData
    ]);

    return $contentInfo->getBinary();
}

function signCMS($signedAttributesBytes)
{
    $client = new ApiClient(
        getenv('API_URL'),
        getenv('API_TOKEN')
    );

    return 'SIGNATURE'; // Replace with actual signing code
}

// Temp code
$client = new ApiClient(
    getenv('API_URL'),
    getenv('API_TOKEN')
);
$token = $client->getTokenProfile();

// token.keys - should have at least one key, check it's length
$keys = $token['keys'];
if (count($keys) === 0) {
    throw new Exception('No keys found in token');
}

// token.certificates - should have at least one certificate, check it's length
$certificates = $token['certificates'];
if (count($certificates) === 0) {
    throw new Exception('No certificates found in token');
}
$certRaw = $client->downloadCertificate($keys[0]['id'], $certificates[0]['id']);
$certPEM = '-----BEGIN CERTIFICATE-----' . PHP_EOL . chunk_split(base64_encode($certRaw), 64, PHP_EOL) . '-----END CERTIFICATE-----';

$x509 = openssl_x509_read($certPEM);
if ($x509 === false) {
    throw new Exception('Failed to read certificate: ' . openssl_error_string());
}
$certData = openssl_x509_parse($x509);
if ($certData === false) {
    throw new Exception('Failed to parse certificate');
}

echo '<pre>';
echo '<code>';
echo 'Now: ' . date('Y-m-d H:i:s') . '<br>';
echo 'Key ID: ' . $keys[0]['id'] . '<br>';
echo 'Certificate:' . '<br>';
echo '  ID: ' . $certificates[0]['id'] . '<br>';
echo '  Subject: ' . $certData['subject']['CN'] . '<br>';
echo '  Issuer: ' . $certData['issuer']['CN'] . '<br>';
echo '  Serial: ' . $certData['serialNumber'] . '<br>';

// Create operation
$operation = $client->createOperation($keys[0]['id']);
echo 'Operation:' . '<br>';
echo '  ID: ' . $operation['id'] . '<br>';
echo '  Status: ' . $operation['status'] . '<br>';

// Finalize operation
$sha256 = hash('sha256', 'Hello, World!');
$sha256Binary = hex2bin($sha256);
$operationId = $operation['id'];
$operationResult = $client->finalizeOperation($keys[0]['id'], $operationId, $sha256Binary);
echo 'Operation result:' . '<br>';
echo '  ID: ' . $operationResult['operation']['id'] . '<br>';
echo '  Status: ' . $operationResult['operation']['status'] . '<br>';
if ($operationResult['operation']['status'] === 'error') {
    echo '  Error: ' . $operationResult['error'] . '<br>';
} else {
    echo '  Data: ' . $operationResult['data'] . '<br>';
}

echo '</code>';
echo '</pre>';
