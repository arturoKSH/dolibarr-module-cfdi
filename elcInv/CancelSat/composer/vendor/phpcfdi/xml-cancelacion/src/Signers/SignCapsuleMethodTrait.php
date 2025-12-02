<?php

namespace PhpCfdi\XmlCancelacion\Signers;

use PhpCfdi\XmlCancelacion\Capsules\CapsuleInterface;
use PhpCfdi\XmlCancelacion\Credentials;
use PhpCfdi\XmlCancelacion\Exceptions\CapsuleRfcDoesnotBelongToCertificateRfc;

trait SignCapsuleMethodTrait
{
    public function signCapsule($capsule, $credentials,$ruta)
    {
        
        if (! $capsule->belongsToRfc($credentials->rfc())) {
            throw new CapsuleRfcDoesnotBelongToCertificateRfc($capsule, $credentials->rfc());
        }
        $document = $capsule->exportToDocument();
        $this->signDocument($document, $credentials,$ruta);
        //return $document->saveXML() ?: '';
        return $document->save($ruta);
    }
}
