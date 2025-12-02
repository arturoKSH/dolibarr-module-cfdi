<?php

namespace PhpCfdi\XmlCancelacion;

use PhpCfdi\Credentials\Credential;
use PhpCfdi\XmlCancelacion\Exceptions\CannotLoadCertificateAndPrivateKey;
use PhpCfdi\XmlCancelacion\Exceptions\CertificateIsNotCSD;
use Throwable;

class Credentials
{
    /** @var string */
    private $certificate;

    /** @var string */
    private $privateKey;

    /** @var string */
    private $passPhrase;

    /** @var Credential|null */
    private $csd;

    public function __construct($certificate, $privateKey, $passPhrase)
    {
        $this->certificate = $certificate;
        $this->privateKey = $privateKey;
        $this->passPhrase = $passPhrase;

        
    }

    public static function createWithPhpCfdiCredential($credential)
    {
        $new = new self('', '', '');
        $new->setCsd($credential);
        return $new;
    }

    public function certificate()
    {
        return $this->certificate;
    }

    public function privateKey()
    {
        return $this->privateKey;
    }

    public function passPhrase()
    {
        return $this->passPhrase;
    }

    public function sign($data, $algorithm = OPENSSL_ALGO_SHA256)
    {
        $result = $this->getCsd()->sign($data, $algorithm);
        //null
        return $result;
    }

    public function certificateIssuerName()
    {
        return $this->getCsd()->certificate()->issuerAsRfc4514();
    }

    public function serialNumber()
    {
        return $this->getCsd()->certificate()->serialNumber()->bytes();
    }

    public function certificateAsPEM()
    {
        return $this->getCsd()->certificate()->pem();
    }

    /** @return array<mixed> */
    public function publicKeyData()
    {
        return $this->getCsd()->certificate()->publicKey()->parsed();
    }

    public function rfc()
    {
        return $this->getCsd()->rfc();
    }

    /**
     * @return Credential
     * @throws CannotLoadCertificateAndPrivateKey
     */
    protected function makePhpCfdiCredential()
    {
        try {
            $credential = Credential::openFiles($this->certificate(), $this->privateKey(), $this->passPhrase());
            return $credential;
        } catch (Throwable $exception) {
            throw new CannotLoadCertificateAndPrivateKey(
                $this->certificate(),
                $this->privateKey(),
                $this->passPhrase(),
                $exception
            );
        }
    }

    protected function getCsd()
    {
        if (null === $this->csd) {
            $credential = $this->makePhpCfdiCredential();
            $this->setCsd($credential);
            return $credential;
        }
        return $this->csd;
    }

    /**
     * @param Credential $credential
     * @throws CertificateIsNotCSD
     */
    protected function setCsd($credential)
    {
        if (! $credential->isCsd()) {
            throw new CertificateIsNotCSD($credential->certificate()->serialNumber()->bytes());
        }
        $this->csd = $credential;
    }
}
