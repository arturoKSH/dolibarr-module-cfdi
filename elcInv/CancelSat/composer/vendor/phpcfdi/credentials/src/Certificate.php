<?php

namespace PhpCfdi\Credentials;

use DateTimeImmutable;
use PhpCfdi\Credentials\Internal\DataArrayTrait;
use PhpCfdi\Credentials\Internal\LocalFileOpenTrait;
use PhpCfdi\Credentials\Internal\SatTypeEnum;
use UnexpectedValueException;

class Certificate
{
    use DataArrayTrait;
    use LocalFileOpenTrait;

    /** @var string PEM contents including headers */
    private $pem;

    /** @var string RFC as parsed from subject/x500UniqueIdentifier */
    private $rfc;

    /** @var string Legal name as parsed from subject/x500UniqueIdentifier */
    private $legalName;

    /** @var SerialNumber|null Parsed serial number */
    private $serialNumber;

    /** @var PublicKey|null Parsed public key */
    private $publicKey;

    /**
     * Certificate constructor
     *
     * @param string $contents can be a X.509 PEM, X.509 DER or X.509 DER base64
     */
    public function __construct($contents)
    {
        if ('' === $contents) {
            throw new UnexpectedValueException('Create certificate from empty contents');
        }
        $pem = (new PemExtractor($contents))->extractCertificate();
        if ('' === $pem) { // it could be a DER content, convert to PEM
            $pem = static::convertDerToPem($contents);
        }

        /** @var array<mixed>|false $parsed */
        $parsed = openssl_x509_parse($pem, true);
        if (false === $parsed) {
            throw new UnexpectedValueException('Cannot parse X509 certificate from contents');
        }
        $this->pem = $pem;
        $this->dataArray = $parsed;
        //$this->rfc = strval(strstr(($parsed['subject']['x500UniqueIdentifier'] ?? '') . ' ', ' ', true)); ->Erick
        $this->rfc = strval(strstr(($parsed['subject']['x500UniqueIdentifier'] ?: '') . ' ', ' ', true));
        //$this->legalName = strval($parsed['subject']['name'] ?? ''); ->Erick
        $this->legalName = strval($parsed['subject']['name'] ?: '');
    }

    /**
     * Convert X.509 DER base64 or X.509 DER to X.509 PEM
     *
     * @param string $contents can be a X.509 DER or X.509 DER base64
     * @return string
     */
    public static function convertDerToPem($contents)
    {
        // effectivelly compare that all the content is base64, if it isn't then encode it
        if ($contents !== base64_encode(base64_decode($contents, true) ?: '')) {
            $contents = base64_encode($contents);
        }
        return '-----BEGIN CERTIFICATE-----' . PHP_EOL
            . chunk_split($contents, 64, PHP_EOL)
            . '-----END CERTIFICATE-----';
    }

    /**
     * Create a Certificate object by opening a local file
     * The content file can be a X.509 PEM, X.509 DER or X.509 DER base64
     *
     * @param string $filename must be a local file (without scheme or file:// scheme)
     * @return Certificate
     */
    public static function openFile($filename)
    {
        return new self(static::localFileOpen($filename));
    }

    public function pem()
    {
        return $this->pem;
    }

    public function pemAsOneLine()
    {
        return implode('', preg_grep('/^((?!-).)*$/', explode(PHP_EOL, $this->pem())) ?: []);
    }

    /**
     * @return array<mixed>
     */
    public function parsed()
    {
        return $this->dataArray;
    }

    public function rfc()
    {
        return $this->rfc;
    }

    public function legalName()
    {
        return $this->legalName;
    }

    public function branchName()
    {
        return $this->subjectData('OU');
    }

    public function name()
    {
        return $this->extractString('name');
    }

    /** @return array<string, string> */
    public function subject()
    {
        return $this->extractArray('subject');
    }

    public function subjectData($key)
    {
        //return strval($this->subject()[$key] ?? ''); ->Erick
        return strval($this->subject()[$key] ?: '');
    }

    public function hash()
    {
        return $this->extractString('hash');
    }

    /** @return array<string, string> */
    public function issuer()
    {
        return $this->extractArray('issuer');
    }

    public function issuerData($string)
    {
        //return strval($this->issuer()[$string] ?? ''); ->Erick
        return strval($this->issuer()[$string] ?: '');
    }

    public function version()
    {
        return $this->extractString('version');
    }

    public function serialNumber()
    {
        if (null === $this->serialNumber) {
            $this->serialNumber = $this->createSerialNumber(
                $this->extractString('serialNumberHex'),
                $this->extractString('serialNumber')
            );
        }
        return $this->serialNumber;
    }

    public function validFrom()
    {
        return $this->extractString('validFrom');
    }

    public function validTo()
    {
        return $this->extractString('validTo');
    }

    public function validFromDateTime()
    {
        return $this->extractDateTime('validFrom_time_t');
    }

    public function validToDateTime()
    {
        return $this->extractDateTime('validTo_time_t');
    }

    public function signatureTypeSN()
    {
        return $this->extractString('signatureTypeSN');
    }

    public function signatureTypeLN()
    {
        return $this->extractString('signatureTypeLN');
    }

    public function signatureTypeNID()
    {
        return $this->extractString('signatureTypeNID');
    }

    /** @return array<mixed> */
    public function purposes()
    {
        return $this->extractArray('purposes');
    }

    /** @return array<string, string> */
    public function extensions()
    {
        return $this->extractArray('extensions');
    }

    public function publicKey()
    {
        if (null === $this->publicKey) {
            // The public key can be created from PUBLIC KEY or CERTIFICATE
            $this->publicKey = new PublicKey($this->pem);
        }
        return $this->publicKey;
    }

    public function satType()
    {
        // as of 2019-08-01 is known that only CSD have OU (Organization Unit)
        if ('' === $this->branchName()) {
            return SatTypeEnum::fiel();
        }
        return SatTypeEnum::csd();
    }

    public function validOn($datetime = null)
    {
        if (null === $datetime) {
            $datetime = new DateTimeImmutable();
        }
        return ($datetime >= $this->validFromDateTime() && $datetime <= $this->validToDateTime());
    }

    protected function createSerialNumber($hexadecimal, $decimal)
    {
        if ('' !== $hexadecimal) {
            return SerialNumber::createFromHexadecimal($hexadecimal);
        }
        if ('' !== $decimal) {
            // in some cases openssl report serialNumberHex on serialNumber
            if (0 === strcasecmp('0x', substr($decimal, 0, 2))) {
                return SerialNumber::createFromHexadecimal(substr($decimal, 2));
            }
            return SerialNumber::createFromDecimal($decimal);
        }
        throw new UnexpectedValueException('Certificate does not contain a serial number');
    }

    public function issuerAsRfc4514()
    {
        $issuer = $this->issuer();
        return (new Internal\Rfc4514())->escapeArray($issuer);
    }
}
