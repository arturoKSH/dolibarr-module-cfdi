<?php

namespace PhpCfdi\XmlCancelacion\Capsules;

use DOMDocument;

interface CapsuleInterface
{
    public function exportToDocument();

    public function belongsToRfc($rfc);
}
