<?php

namespace Peculiarventures\GoodkeyCms;

use DateTimeImmutable;
use Exception;
use Sop\ASN1\Type\Constructed\Sequence;
use Sop\ASN1\Type\Constructed\Set;
use Sop\ASN1\Type\Primitive\ObjectIdentifier;
use Sop\ASN1\Type\Primitive\OctetString;
use Sop\ASN1\Type\Primitive\Integer;
use Sop\ASN1\Type\Primitive\UTCTime;
use Sop\ASN1\Type\Primitive\NullType;
use Sop\ASN1\Type\Tagged\ExplicitlyTaggedType;
use Sop\ASN1\Type\Tagged\ImplicitlyTaggedType;

/**
 * CMS Package Builder
 */
class CmsBuilder
{
  private $client;
  private static $dataOID = '1.2.840.113549.1.7.1';
  private static $signedDataOID = '1.2.840.113549.1.7.2';
  private static $sha256OID = '2.16.840.1.101.3.4.2.1';
  private static $rsaEncryptionWithSha256OID = '1.2.840.113549.1.1.11';

  public function __construct(ApiClient $client)
  {
    $this->client = $client;
  }

  /**
   * Creates a CMS package with the given hash
   *
   * @param string $hash Hash of the content to sign
   * @return string DER-encoded CMS package
   * 
   * @throws Exception When token profile is invalid or signing fails
   */
  public function create(string $hash): string
  {
    if (empty($hash)) {
      throw new Exception('Hash cannot be empty');
    }

    if (!ctype_xdigit($hash)) {
      throw new Exception('Hash must be a hexadecimal string');
    }

    // Get token profile
    $token = $this->client->getTokenProfile();

    // Get keys and certificates
    $keys = $token['keys'];
    $certificates = $token['certificates'];

    // Check if keys and certificates are available
    if (count($keys) === 0 || count($certificates) === 0) {
      throw new Exception('No keys or certificates found in token');
    }

    // Download certificate
    $certRaw = $this->client->downloadCertificate($keys[0]['id'], $certificates[0]['id']);

    // Suppress deprecation warnings for ASN.1 parsing
    error_reporting(E_ALL & ~E_DEPRECATED);

    // Parse certificate (using sop/asn1 library)
    /** @var Sequence */
    $certAsn = Sequence::fromDER($certRaw);

    // Restore error reporting
    error_reporting(E_ALL);

    $certSerial = $certAsn->at(0)->asSequence()->at(1)->asInteger();
    $certIssuer = $certAsn->at(0)->asSequence()->at(3)->asSequence();

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
                new ObjectIdentifier(CmsBuilder::$sha256OID),
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
    $operation = $this->client->createOperation($keys[0]['id']);
    $operationId = $operation['id'];
    $operationResult = $this->client->finalizeOperation($keys[0]['id'], $operationId, $signedAttrsHash);
    if ($operationResult['operation']['status'] === 'error') {
      throw new Exception('Failed to finalize operation: ' . $operationResult['error']);
    }
    $signature = $operationResult['data'];

    // Create SignerInfo
    $signerInfo = new Sequence(
      new Integer(1), // version
      $issuerAndSerial,
      new Sequence(
        new ObjectIdentifier(CmsBuilder::$sha256OID),
        new NullType()
      ),
      new ImplicitlyTaggedType(
        0, // authenticatedAttributes [0] IMPLICIT
        $signedAttrs
      ),
      new Sequence(
        new ObjectIdentifier(CmsBuilder::$rsaEncryptionWithSha256OID),
        new NullType()
      ),
      new OctetString($signature),
    );

    // Create SignedData
    $signedData = new Sequence(
      new Integer(1), // version
      new Set( // digestAlgorithms
        new Sequence(
          new ObjectIdentifier(CmsBuilder::$sha256OID),
          new NullType()
        )
      ),
      new Sequence( // contentInfo
        new ObjectIdentifier(CmsBuilder::$dataOID),
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
      new ObjectIdentifier(CmsBuilder::$signedDataOID),
      new ExplicitlyTaggedType(0, $signedData)
    );

    return $cms->toDER();
  }
}
