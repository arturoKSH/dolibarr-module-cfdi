<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
include(DOL_DOCUMENT_ROOT.'/elcInv/phpqrcode/qrlib.php');

$doc = new DOMDocument('1.0', 'UTF-8');
$doc->load($fname);

QRcode::png(getURL($doc),$fileQr, QR_ECLEVEL_M, 2.75, 2.75);

function getRfcEmisor($doc){
    $RfcEmisor = $doc->getElementsByTagName('Emisor')->item(0);
    return $RfcEmisor->getAttribute('Rfc');
 }

  function getRfcReceptor($doc){
    $RfcReceptor = $doc->getElementsByTagName('Receptor')->item(0);
    return $RfcReceptor->getAttribute('Rfc');
  }

  function getTotal($doc){
    $Total = $doc->getElementsByTagName('Comprobante')->item(0);
    if (empty($Total)){
      return null;
    }else{
      return $Total->getAttribute('Total');
    }

  }

  function getUUID($doc){
    $UUID = $doc->getElementsByTagName('TimbreFiscalDigital')->item(0);
     if (empty($UUID)){
      return null;
    }else{
      return $UUID->getAttribute('UUID');
    }
  }

  function getSello($doc){
    $Sello = $doc->getElementsByTagName('TimbreFiscalDigital')->item(0);
    if (empty($Sello)){
      return null;
    }else{
      $Length = strlen($Sello->getAttribute('SelloCFD'));
      return substr($Sello->getAttribute('SelloCFD'),-8);
    }
  } 
    
  function  getURL($doc) {
        return "https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?&id=".getUUID($doc)."&re=".getRfcEmisor($doc)."&rr=".getRfcReceptor($doc)."&tt=".getTotal($doc)."&fe=".getSello($doc);
  }