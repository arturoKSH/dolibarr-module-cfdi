<?php
/* echo DOL_DATA_ROOT."/facture/".$object->ref."/".$object->ref.".xml";*/
  $fname = DOL_DATA_ROOT."/facture/".$object->ref."/".$object->ref.".xml";
  if(!file_exists($fname)){
    die(PHP_EOL . "File not found" . PHP_EOL . PHP_EOL);
  }

  $handle = fopen($fname, "r");
  $sData = '';

  while(!feof($handle))
      $sData .= fread($handle, filesize($fname));

  fclose($handle);

  $b64 = base64_encode($sData);

  ini_set("soap.wsdl_cache_enabled", "0");


  try
  {  
      // $client = new SoapClient("http://201.144.64.67:81/wsTimbrado.asmx?WSDL" , array('trace' => 1));
      // echo 'oooo';
   $client = new SoapClient("https://develop.timbrado.com.mx/wsTimbrado.asmx?WSDL" , array('trace' => 1));//pruebas
  
  //$client = new SoapClient("https://cfdi33.timbrado.com.mx/wsTimbrado.asmx?WSDL" , array('trace' => 1));https://cfdi33.drptimbrado.com.mx/wsTimbrado.asmx
  //$client = new SoapClient("https://cfdi33.timbrado.com.mx/wsTimbrado.asmx?WSDL" , array('trace' => 1));//produccion
  
} catch (SoapFault $fault) {
  
  trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
}
  try
  {
   
    /** Procedimiento para versiones menores a php 8.0 */
    // $auten = array('UserName' => 'crasa_t', 'Password' => '2x!D-Bf9Ln6=$Gp4');
    // //$auten = array('UserName' => 'autofac_t', 'Password' => '6Pj!N+5sbQ$4=t8Y');    
    // $params = array('minOccurs'=>'0', 'maxOccurs'=>'1', 'cfdiBytes' => $sData, 'type'=>'s:base64Binary');
    // $result = $client->__Call('GeneraTimbre', array('cfdiBytes' => $params), null,
    // new SoapHeader("https://cfdi.timbrado.com.mx/timbradov2", "AuthenticationHeader", $auten));
   
    /**Procedimiento para versiones > a php 8.0 */
    // $auten = array('UserName' => 'crasa_t', 'Password' => '2x!D-Bf9Ln6=$Gp4');//produccion
    $auten = array('UserName' => 'autofac_t', 'Password' => '6Pj!N+5sbQ$4=t8Y');    
    $params = array('minOccurs'=>'0', 'maxOccurs'=>'1', 'cfdiBytes' => $sData, 'type'=>'s:base64Binary');
    try {
      $header = new SoapHeader(
          "https://cfdi.timbrado.com.mx/timbradov2",
          "AuthenticationHeader",
          $auten
      );
   
      // Agregar el encabezado SOAP al cliente
      $client->__setSoapHeaders($header);
    
      // Realizar la llamada al método 'GeneraTimbre'
      $result = $client->GeneraTimbre($params);

    } catch (SoapFault $e) {
      // Maneja las excepciones de SOAP aquí
      echo "Error: " . $e->getMessage();
    }

    $stamp =  $result->GeneraTimbreResult->Timbre;
    if ($stamp) 
    { 
        $xml2 = simplexml_load_string($result->GeneraTimbreResult->Timbre);
        $xml_doc = new DomDocument('1.0', 'UTF-8');
        $xml_doc->Load($fname);
        $xmlbody = $xml_doc->getElementsByTagName('Comprobante')->item(0);
        //var_dump($xmlbody);
        
        // $Complemento=$xml_doc->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:Complemento");
        // $xmlbody->appendChild($Complemento);

        // $TimbreFiscalDigital=$xml_doc->createElementNS('http://www.sat.gob.mx/TimbreFiscalDigital',"tfd:TimbreFiscalDigital");
        // $Complemento->appendChild($TimbreFiscalDigital);

        //Correccion macv 21/02/2024 Inicio
        if($object->element == 'facture'){
          $Complemento=$xml_doc->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:Complemento");
          $xmlbody->appendChild($Complemento);
  
          $TimbreFiscalDigital=$xml_doc->createElementNS('http://www.sat.gob.mx/TimbreFiscalDigital',"tfd:TimbreFiscalDigital");
          $Complemento->appendChild($TimbreFiscalDigital);
        }else if($object->element == 'payment'){
          
          if ($xmlbody->getElementsByTagName('Complemento')->length > 0) {
            $complementoNode = $xmlbody->getElementsByTagName('Complemento')->item(0);
      
            $TimbreFiscalDigital=$xml_doc->createElementNS('http://www.sat.gob.mx/TimbreFiscalDigital',"tfd:TimbreFiscalDigital");
            $complementoNode->appendChild($TimbreFiscalDigital);
          }
         
        }else{
          $Complemento=$xml_doc->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:Complemento");
          $xmlbody->appendChild($Complemento);
  
          $TimbreFiscalDigital=$xml_doc->createElementNS('http://www.sat.gob.mx/TimbreFiscalDigital',"tfd:TimbreFiscalDigital");
          $Complemento->appendChild($TimbreFiscalDigital);
        }
        //Correccion macv 21/02/2024 Fin
        
        $xsi=$xml_doc->createAttributeNS('http://www.w3.org/2001/XMLSchema-instance','xsi:schemaLocation');
        $xsi->value="http://www.sat.gob.mx/TimbreFiscalDigital http://www.sat.gob.mx/sitio_internet/cfd/TimbreFiscalDigital/TimbreFiscalDigitalv11.xsd";

        $SelloSAT=$xml_doc->createAttribute('SelloSAT');
        $SelloSAT->value=$xml2['SelloSAT'];

        $FechaTimbrado=$xml_doc->createAttribute('FechaTimbrado');
        $FechaTimbrado->value=$xml2['FechaTimbrado'];

        $UUID=$xml_doc->createAttribute('UUID');
        $UUID->value=$xml2['UUID'];

        $RfcProvCertif=$xml_doc->createAttribute('RfcProvCertif');
        $RfcProvCertif->value=$xml2['RfcProvCertif'];

        //leyenda 
        if (!empty($xml2['Leyenda'])) {
          $leyenda=$xml_doc->createAttribute('Leyenda');
          $leyenda->value=$xml2['Leyenda'];
        }

        $NoCertificadoSAT=$xml_doc->createAttribute('NoCertificadoSAT');
        $NoCertificadoSAT->value=$xml2['NoCertificadoSAT'];

        $SelloCFD=$xml_doc->createAttribute('SelloCFD');
        $SelloCFD->value=$xml2['SelloCFD'];

        $Version=$xml_doc->createAttribute('Version');
        $Version->value=$xml2['Version'];

        $TimbreFiscalDigital->appendChild($xsi);
        $TimbreFiscalDigital->appendChild($SelloCFD);
        $TimbreFiscalDigital->appendChild($NoCertificadoSAT);
        if (!empty($xml2['Leyenda'])) {
          $TimbreFiscalDigital->appendChild($leyenda);
        }
        $TimbreFiscalDigital->appendChild($RfcProvCertif);
        $TimbreFiscalDigital->appendChild($UUID);
        $TimbreFiscalDigital->appendChild($FechaTimbrado);
        $TimbreFiscalDigital->appendChild($SelloSAT);
        $TimbreFiscalDigital->appendChild($Version);

        $done = $xml_doc->save($fname);

        //Ejemplo de como extraer datos de xml 

        /*$xml = simplexml_load_file('./FA2005-0007.xml'); 
        $ns = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('c', $ns['cfdi']);
        $xml->registerXPathNamespace('t', $ns['tfd']);
        foreach ($xml->xpath('//cfdi:Comprobante') as $cfdiComprobante){ 
              echo $cfdiComprobante['Version']; 
              echo "<br />"; 
              echo $cfdiComprobante['Fecha']; 
              echo "<br />"; 
              echo $cfdiComprobante['Sello']; 
              echo "<br />"; 
              echo $cfdiComprobante['Total']; 
              echo "<br />"; 
              echo $cfdiComprobante['SubTotal']; 
              echo "<br />"; 
              echo $cfdiComprobante['Certificado']; 
              echo "<br />"; 
              echo $cfdiComprobante['FormaDePago']; 
              echo "<br />"; 
              echo $cfdiComprobante['NoCertificado']; 
              echo "<br />"; 
              echo $cfdiComprobante['TipoDeComprobante']; 
              echo "<br />"; 
        } 

        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor') as $Emisor){ 
          echo $Emisor['Rfc']; 
          echo "<br />"; 
          echo $Emisor['Nombre']; 
          echo "<br />"; 
        } */
        /*$xml_doc = new DomDocument('1.0', 'UTF-8');

        $xml_doc->Load('./FA2005-0007.xml');

        $xmlbody = $xml_doc->getElementsByTagName('Emisor')->item(0);
        echo  $xmlbody[0]->getAttribute('Rfc');*/


        // Generacion de codigo QR

        $update = "UPDATE ".MAIN_DB_PREFIX.$table." SET";
        $update.= "  uuid = '".$xml2['UUID']."'"; 
        $update.= " WHERE ".$field." = ".$id;
        $up=$db->query($update);
        setEventMessages('CFDI Timbrado', null, 'mesgs');
        $timbre=1;
         //include(DOL_DOCUMENT_ROOT.'/elcInv/phpqrcode/qrlib.php');
        $fileQr = DOL_DATA_ROOT."/facture/".$object->ref."/".$object->ref.".png";
        if(!function_exists("getRfcEmisor"))
          include(DOL_DOCUMENT_ROOT.'/elcInv/phpqrcode/functionQR.php'); 

      //  $doc = new DOMDocument('1.0', 'UTF-8');
      //  $doc->load($fname);
      //  QRcode::png(getURL($doc),$fileQr, QR_ECLEVEL_M, 2.75, 2.75);        
        $pthFiles = DOL_DOCUMENT_ROOT."/elcInv/".$id.".txt";
          if (file_exists($pthFiles) )
            unlink($pthFiles);
    }else{
     
        $erros = $result->GeneraTimbreResult->Error->ErroresVerificacion->VerificationError;
      
        $mssg = "";
        foreach ($erros as $mssgArr) 
        {
            $mssg.= "***".$mssgArr->Descripcion."***";
        }
        $mssg = str_replace('*','',$mssg);
        
        if($mssg=="")
        {
            
            $mssg = $erros->Descripcion;
        }
       // print_r($result->GeneraTimbreResult->Error->Descripcion);
        setEventMessages($mssg, $object->errors, 'errors');
        if($result->GeneraTimbreResult->Error->Descripcion){
          setEventMessages( $result->GeneraTimbreResult->Error->Descripcion,$object->errors, 'errors');
        }
       
        
    }
  } catch (SoapFault $fault) {
      trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
  }

 
     
// function getRfcEmisor($doc){
//    $RfcEmisor = $doc->getElementsByTagName('Emisor')->item(0);
//    return $RfcEmisor->getAttribute('Rfc');
// }
//
//  function getRfcReceptor($doc){
//    $RfcReceptor = $doc->getElementsByTagName('Receptor')->item(0);
//    return $RfcReceptor->getAttribute('Rfc');
//  }
//
//  function getTotal($doc){
//    $Total = $doc->getElementsByTagName('Comprobante')->item(0);
//    if (empty($Total)){
//      return null;
//    }else{
//      return $Total->getAttribute('Total');
//    }
//
//  }
//
//  function getUUID($doc){
//    $UUID = $doc->getElementsByTagName('TimbreFiscalDigital')->item(0);
//     if (empty($UUID)){
//      return null;
//    }else{
//      return $UUID->getAttribute('UUID');
//    }
//  }
//
//  function getSello($doc){
//    $Sello = $doc->getElementsByTagName('TimbreFiscalDigital')->item(0);
//    if (empty($Sello)){
//      return null;
//    }else{
//      $Length = strlen($Sello->getAttribute('SelloCFD'));
//      return substr($Sello->getAttribute('SelloCFD'),-8);
//    }
//  } 
//    
//  function  getURL($doc) {
//        return "https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?&id=".getUUID($doc)."&re=".getRfcEmisor($doc)."&rr=".getRfcReceptor($doc)."&tt=".getTotal($doc)."&fe=".getSello($doc);
//  }

	