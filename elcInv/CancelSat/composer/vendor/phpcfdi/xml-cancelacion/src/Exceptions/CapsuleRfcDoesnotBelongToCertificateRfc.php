<?php

namespace PhpCfdi\XmlCancelacion\Exceptions;

use PhpCfdi\XmlCancelacion\Capsules\CapsuleInterface;

class CapsuleRfcDoesnotBelongToCertificateRfc extends XmlCancelacionRuntimeException
{
    /** @var CapsuleInterface */
    private $capsule;

    /** @var string */
    private $certificateRfc;

    public function __construct($capsule, $certificateRfc)
    {
        parent::__construct('The capsule RFC does not belong to certificate RFC');
        $this->capsule = $capsule;
        $this->certificateRfc = $certificateRfc;
    }

    public function getCapsule()
    {
        return $this->capsule;
    }

    public function getCertificateRfc()
    {
        return $this->certificateRfc;
    }
}
