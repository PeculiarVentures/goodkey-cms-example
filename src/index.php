<?php

require __DIR__ . '/../vendor/autoload.php';

use Peculiarventures\GoodkeyCms\ApiClient;
use Sop\ASN1\Type\Constructed\Sequence;
use Sop\ASN1\Type\Constructed\Set;
use Sop\ASN1\Type\Primitive\ObjectIdentifier;
use Sop\ASN1\Type\Primitive\OctetString;
use Sop\ASN1\Type\Primitive\Integer;
use Sop\ASN1\Type\Primitive\UTCTime;
use Sop\ASN1\Type\Primitive\NullType;
use Sop\ASN1\Type\Tagged\ExplicitlyTaggedType;
use Sop\ASN1\Type\Tagged\ImplicitlyTaggedType;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    if (!isset($data['hash'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Hash parameter is required']);
        exit;
    }

    $hash = $data['hash'];

    // Create CMS package and add messageDigest attribute
    $cms = createCMS($hash);
    // echo bin2hex($cms);

    // Return signed data
    header('Content-Type: application/octet-stream');
    echo $cms;
}

// Function to create CMS package with messageDigest attribute
function createCMS($hash)
{
    // Create client
    $client = new ApiClient(
        getenv('API_URL'),
        getenv('API_TOKEN')
    );

    // Get token profile
    $token = $client->getTokenProfile();

    // Get keys and certificates
    $keys = $token['keys'];
    $certificates = $token['certificates'];

    // Check if keys and certificates are available
    if (count($keys) === 0 || count($certificates) === 0) {
        throw new Exception('No keys or certificates found in token');
    }

    // Download certificate
    $certRaw = $client->downloadCertificate($keys[0]['id'], $certificates[0]['id']);
    // echo 'Certificate: ' . bin2hex($certRaw) . PHP_EOL;

    // Parse certificate (using sop/asn1 library)
    /** @var Sequence */
    $certAsn = Sequence::fromDER($certRaw);
    $certSerial = $certAsn->at(0)->asSequence()->at(1)->asInteger();
    $certIssuer = $certAsn->at(0)->asSequence()->at(3)->asSequence();

    // OIDs
    $signedDataOID = '1.2.840.113549.1.7.2';
    $sha256OID = '2.16.840.1.101.3.4.2.1';

    // Create SignerIdentifier (issuerAndSerialNumber)
    $issuerAndSerial = new Sequence(
        $certIssuer, // issuer
        $certSerial // serialNumber
    );

    // Create attributes
    $contentTypeAttr = new Sequence(
        new ObjectIdentifier('1.2.840.113549.1.9.3'),
        new Set(
            new ObjectIdentifier('1.2.840.113549.1.7.1') // id-data
        )
    );

    $signingTimeAttr = new Sequence(
        new ObjectIdentifier('1.2.840.113549.1.9.5'),
        new Set(
            new UTCTime(new DateTimeImmutable('@' . time()))
        )
    );

    $messageDigestAttr = new Sequence(
        new ObjectIdentifier('1.2.840.113549.1.9.4'),
        new Set(
            new OctetString(hex2bin($hash))
        )
    );

    $certHash = hash('sha256', $certRaw, true);
    $signingCertV2Attr = new Sequence(
        new ObjectIdentifier('1.2.840.113549.1.9.16.2.47'),
        new Set(
            new Sequence(
                new Sequence(
                    new Sequence(
                        new Sequence(
                            new ObjectIdentifier($sha256OID),
                            new NullType()
                        ),
                        new OctetString($certHash)
                    )
                )
            )
        )
    );

    // Create SignedAttributes
    $signedAttrs = new Set(
        $contentTypeAttr,
        $signingTimeAttr,
        $messageDigestAttr,
        $signingCertV2Attr
    );
    $signedAttrsRaw = $signedAttrs->toDER();
    $signedAttrsHash = hash('sha256', $signedAttrsRaw, true);

    // Create operation
    $operation = $client->createOperation($keys[0]['id']);
    $operationId = $operation['id'];
    $operationResult = $client->finalizeOperation($keys[0]['id'], $operationId, $signedAttrsHash);
    if ($operationResult['operation']['status'] === 'error') {
        throw new Exception('Failed to finalize operation: ' . $operationResult['error']);
    }
    $signature = $operationResult['data'];

    // Create SignerInfo
    $signerInfo = new Sequence(
        new Integer(1), // version
        $issuerAndSerial,
        new Sequence(
            new ObjectIdentifier($sha256OID), // digestAlgorithm
            new NullType()
        ),
        new ImplicitlyTaggedType(
            0, // authenticatedAttributes [0] IMPLICIT
            $signedAttrs
        ),
        new Sequence(
            new ObjectIdentifier('1.2.840.113549.1.1.11'), // sha256WithRSAEncryption
            new NullType()
        ),
        new OctetString($signature),
    );

    // Create SignedData
    $signedData = new Sequence(
        new Integer(1), // version
        new Set( // digestAlgorithms
            new Sequence(
                new ObjectIdentifier($sha256OID),
                new NullType()
            )
        ),
        new Sequence( // contentInfo
            new ObjectIdentifier('1.2.840.113549.1.7.1'), // id-data
        ),
        new ImplicitlyTaggedType(
            0, // certificates [0] IMPLICIT
            new Sequence(
                $certAsn,
            )
        ),
        new Set( // signerInfos
            $signerInfo
        )
    );

    // Create ContentInfo (root CMS structure)
    $cms = new Sequence(
        new ObjectIdentifier($signedDataOID),
        new ExplicitlyTaggedType(0, $signedData)
    );

    return $cms->toDER();
}
