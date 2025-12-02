<?php


namespace PhpCfdi\XmlCancelacion\Capsules;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use PhpCfdi\XmlCancelacion\Definitions\CancelAnswer;
use PhpCfdi\XmlCancelacion\Definitions\DocumentType;

class CancellationAnswer implements CapsuleInterface
{
    /** @var string */
    private $uuid;

    /** @var CancelAnswer */
    private $answer;

    /** @var string */
    private $rfc;

    /** @var DateTimeImmutable */
    private $dateTime;

    /** @var string */
    private $pacRfc;

    public function __construct(
         $rfc,
         $uuid,
         $answer,
         $pacRfc,
         $dateTime
    ) {
        $this->rfc = $rfc;
        $this->uuid = $uuid;
        $this->answer = $answer;
        $this->dateTime = $dateTime;
        $this->pacRfc = $pacRfc;
    }

    public function rfc()
    {
        return $this->rfc;
    }

    public function uuid()
    {
        return $this->uuid;
    }

    public function answer()
    {
        return $this->answer;
    }

    public function dateTime()
    {
        return $this->dateTime;
    }

    public function pacRfc()
    {
        return $this->pacRfc;
    }

    public function exportToDocument()
    {
        $document = (new BaseDocumentBuilder())
            ->createBaseDocument('SolicitudAceptacionRechazo', DocumentType::cfdi()->value());

        /** @var DOMElement $solicitudAceptacionRechazo */
        $solicitudAceptacionRechazo = $document->documentElement;
        $solicitudAceptacionRechazo->setAttribute('Fecha', $this->dateTime()->format('Y-m-d\TH:i:s'));
        $solicitudAceptacionRechazo->setAttribute('RfcPacEnviaSolicitud', $this->pacRfc());
        $solicitudAceptacionRechazo->setAttribute('RfcReceptor', $this->rfc());
        $solicitudAceptacionRechazo->appendChild(
            $folios = $document->createElement('Folios')
        );
        $folios->appendChild(
            $document->createElement('UUID', htmlspecialchars($this->uuid(), ENT_XML1))
        );
        $folios->appendChild(
            $document->createElement('Respuesta', htmlspecialchars($this->answer()/*->value()*/, ENT_XML1))
        );

        return $document;
    }

    public function belongsToRfc( $rfc)
    {
        return ($rfc === $this->rfc());
    }
}
