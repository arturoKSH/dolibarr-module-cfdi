<?php



namespace PhpCfdi\XmlCancelacion\Capsules;

use DOMDocument;
use DOMElement;
use PhpCfdi\XmlCancelacion\Definitions;
use PhpCfdi\XmlCancelacion\Definitions\DocumentType;
use PhpCfdi\XmlCancelacion\Definitions\RfcRole;

class ObtainRelated implements CapsuleInterface
{
    /** @var string */
    private $uuid;

    /** @var string */
    private $rfc;

    /** @var Definitions\RfcRole */
    private $role;

    /** @var string */
    private $pacRfc;

    public function __construct($uuid, $rfc,$role,$pacRfc)
    {
        $this->uuid = $uuid;
        $this->rfc = $rfc;
        $this->role = $role;
        $this->pacRfc = $pacRfc;
    }

    public function uuid()
    {
        return $this->uuid;
    }

    public function rfc()
    {
        return $this->rfc;
    }

    public function role()
    {
        return $this->role;
    }

    public function pacRfc()
    {
        return $this->pacRfc;
    }

    public function exportToDocument()
    {
        $document = (new BaseDocumentBuilder())
            ->createBaseDocument('PeticionConsultaRelacionados', DocumentType::cfdi()->value());

        /** @var DOMElement $peticion */
        $peticion = $document->documentElement;
        $peticion->setAttribute('RfcEmisor', $this->rfc() );
        $peticion->setAttribute('RfcPacEnviaSolicitud', $this->pacRfc());
        $peticion->setAttribute('RfcReceptor',  $this->rfc() );
        $peticion->setAttribute('Uuid', $this->uuid());

        return $document;
    }

    public function belongsToRfc($rfc)
    {
        return ($rfc === $this->rfc());
    }
}
