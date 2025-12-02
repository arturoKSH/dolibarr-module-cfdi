<?php

namespace PhpCfdi\XmlCancelacion\Capsules;

use Countable;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use PhpCfdi\XmlCancelacion\Definitions\DocumentType;

class Cancellation implements Countable, CapsuleInterface
{
    /** @var string */
    private $rfc;

    /** @var DateTimeImmutable */
    private $date;

    /** @var array<string, bool> This is a B-Tree array, values are stored in keys */
    private $uuids;

    /** @var DocumentType */
    private $documentType;

      /** @var reason */
      private $reason;
    
      /** @var uuidRepl */
      private $uuidRepl;

    /**
     * DTO for cancellation request, it support CFDI and Retention
     *
     * @param string $rfc
     * @param string[] $uuids
     * @param DateTimeImmutable $date
     * @param DocumentType|null $type Uses CFDI if non provided
     */
    public function __construct($rfc, $uuids, $date, $reason, $uuidRepl,  $type = null)
    {
        $this->rfc = $rfc;
        $this->date = $date;
        $this->uuids = str_split($uuidRepl);
      //  var_dump( $this->uuids);
        //$this->documentType = $type ?? DocumentType::cfdi(); ->Erick
        $this->documentType = $type ?: DocumentType::cfdi();

        //echo $uuidRepl;
        /*
        foreach ($uuidRepl as $uuid) {

            if(is_numeric($uuid))
                echo $uuid;
            else
                echo $uuid.' LLLL';

                echo 'sssssssssssssssss';
            //$this->uuids[strtoupper($uuid)] = true;
        }*/
         //ehm
         //echo $reason;
         $this ->reason = $reason; 
         $this ->uuid = $uuidRepl;
    }

    public function rfc()
    {
        return $this->rfc;
    }

    public function date()
    {
        return $this->date;
    }

    public function documentType()
    {
        return $this->documentType;
    }

    /**
     * The list of UUIDS
     * @return string[]
     */
    public function uuids()
    {
        //  return array_key($this->uuids);
        return ($this->uuids);
    }

    public function count()
    {
        return count($this->uuids);
    }

    public function exportToDocument()
    {
        $document = (new BaseDocumentBuilder())->createBaseDocument('Cancelacion', $this->documentType->value());

        /** @var DOMElement $cancelacion */
        $cancelacion = $document->documentElement;
        $cancelacion->setAttribute('RfcEmisor', $this->rfc()); // en el anexo 20 es opcional!
        $cancelacion->setAttribute('Fecha', $this->date()->format('Y-m-d\TH:i:s'));
        $folios = $cancelacion->appendChild($document->createElement('Folios'));
        $folio = $folios->appendChild($document->createElement('Folio'));//ehm
        $uuidF = '';
        //foreach ($this->uuids() as $uuid) {
            //$folios->appendChild($document->createElement('UUID', htmlspecialchars($uuid, ENT_XML1)));
           // echo $uuid;
           // $uuidF = $uuidF + (string)$uuid;      
        //}
        $folio->setAttribute('FolioSustitucion', $this->uuidRepl()); //ehm            
        $folio->setAttribute('Motivo', $this->reason());
        $folio->setAttribute('UUID', htmlspecialchars(implode($this->uuids()), ENT_XML1));  
        return $document;
    }

    public function belongsToRfc($rfc)
    {
        return ($rfc === $this->rfc());
    }

     //ehm
     public function uuidRepl()
     {
         return $this->uuidRepl;
     }
     public function reason()
     {
         return $this->reason;
     }
}
