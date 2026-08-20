<?php

//$xlst = "/xslt/origStr40.xslt";
include(DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/xmlCrt/stampCfdi.php'); //ehm
include(DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/dbExc/excFetch.php'); //ehm

if (empty($certName)) {
    setEventMessages('Certificado SAT no configurado. Suba el .cer y .key en CFDI > Configuración.', null, 'errors');
    return;
}

$search  = array('-', ',');
$replace = array('', '');

$grlArray=array('Total','Moneda','SubTotal','MetodoPago','Descuento',
                'FormaPago','CondicionesDePago','TipoDeComprobante',
                'Fecha','Folio','Serie','LugarExpedicion','Version','Exportacion');

$objGrl =  getCustInf($id);
$excQryError = '';
if (excQryFailed($excQryError) || empty($objGrl)) {
    setEventMessages('No se pudieron obtener los datos fiscales principales de la factura. El timbrado fue cancelado.', null, 'errors');
    return;
}

$xml = new DOMdocument('1.0', 'UTF-8');

$mainElmnt = crtElmnt ($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Comprobante",$xml);// PAT
foreach ($grlArray as $key => $value)
{
    if($objGrl[0][$value]){
        createAttribt($xml, $mainElmnt, $value, $objGrl[0][$value]);//PAT    
    }
}
createAttribtDNS($xml, $mainElmnt,"http://www.w3.org/2001/XMLSchema-instance",
        "xsi:schemaLocation","http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd");

createAttribt($xml, $mainElmnt, "LugarExpedicion", $conf->global->MAIN_INFO_ACCOUNTANT_ZIP);//PAT
createAttribt($xml, $mainElmnt, "Sello", "Sello");//PAT
createAttribt($xml, $mainElmnt, "Certificado", $conf->global->MAIN_INFO_CFDI_CERT_VAL);//PAT
createAttribt($xml, $mainElmnt, "NoCertificado", $conf->global->MAIN_INFO_CFDI_CERT);//PAT


/*Informacion global*/
if($objGrl[0]["Rfc"]=="XAXX010101000" && $objGrl[0]['TipoDeComprobante']=='I' ) //ehm
{
    $glblInf = crtElmnt($xml, 'http://www.sat.gob.mx/cfd/4',"cfdi:InformacionGlobal", $mainElmnt);
    
    $glblInfArray=array('Periodicidad','Meses','Año');
    
    foreach ($glblInfArray as $key => $value)
    {
        if($objGrl[0][$value]!='' && $objGrl[0][$value]!='0')
            createAttribt($xml, $glblInf, $value, $objGrl[0][$value]);
    }
}


//$objtrasladostotales = getTrasTax($id);

//cfdi relacionados 

// extrae UUID de XML facturas relacionadas


if ($objGrl[0]['TipoDeComprobante']=='E' || $objGrl[0]['TipoDeComprobante']=='I') 
{
    $objsrelextras = getRelType($id);
    if(empty($objsrelextras))
        $objsrelextras = getRelTypeNC($id);

    if ($objGrl[0]['TipoDeComprobante']=='E') 
        $objsrel = getRef($id);
    
    if ($objGrl[0]['TipoDeComprobante']=='I')
        $objsrel = getRefInv($id);
    if(!empty($objsrelextras))
    { 
        if(file_exists(DOL_DATA_ROOT."/facture/".$objsrel[0]['ref']."/".$objsrel[0]['ref'].".xml"))
        {
            $doc = new DOMDocument('1.0', 'UTF-8');
            //echo DOL_DATA_ROOT."/facture/".$objsrel[0]['ref']."/".$objsrel[0]['ref'].".xml";
            $doc->load(DOL_DATA_ROOT."/facture/".$objsrel[0]['ref']."/".$objsrel[0]['ref'].".xml");

            $Nocertificado= $doc->getElementsByTagName('Comprobante')->item(0)->getAttribute('NoCertificado');
            
            if (!empty($doc->getElementsByTagName('Complemento')->item(0))) {

            
            $RfcEmisor = $doc->getElementsByTagName('Complemento')->item(0);
            $complemento=$RfcEmisor->getElementsByTagName('TimbreFiscalDigital')->item(0);
            $sellosatr=$complemento->getAttribute('SelloSAT');
            $sellocfd=$complemento->getAttribute('SelloCFD');
            $UUID=$complemento->getAttribute('UUID');
            
            $cfdiRels = crtElmnt ($xml ,'http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionados", $mainElmnt);// PAT
            // $cfdiRel = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionado",$cfdiRels);//PAT
            if($objsrelextras)
            {
                $relType = $objsrelextras[0]['reltype'];
            }else
            {
                $relType = $objGrl[0]['typerelsat'];
            }
                

            createAttribt($xml, $cfdiRels, "TipoRelacion", $relType);
            // createAttribt($xml, $cfdiRel, "UUID", $UUID);
            
            $morequals='';
            $mor=0;
            
            if (!empty($objsrelextras)) {
                
                foreach ($objsrelextras as $key => $value) {
                    if ($value['reltype'] == $objdtl[0]['idsat']) {
                        $morequals='yes';
                    }
                    $mor++;
                }
                if (!empty($morequals)) {
                    
                    $objsrelextra = getRelInv($id."' and reltype = '".$relType."'");
                    
                    $cont=0;
                    foreach ($objsrelextra as $key => $value) {
                        $cfdiRel = crtElmnt ($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionado",$cfdiRels);                       
                        createAttribt($xml, $cfdiRel, "UUID" , $value['uuid']);                              
                        $UIDDR=$cont.'extra';
                        $cont++;                   
                        }
                }
                if ($mor>0) {
                    
                    $objsrelextra = getRelInv($id."' and reltype = '".$relType."'");

                    if(empty($objsrelextra))
                            $objsrelextra = getRelNC($id);

                    foreach ($objsrelextra as $key => $value) {
                        //$cfdiRels = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionados",$mainElmnt); //PAT
                        $cfdiRel = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionado",$cfdiRels);//PAT
                        
                        createAttribt($xml, $cfdiRels, "TipoRelacion", $value['reltype']);//PAT
                        createAttribt($xml, $cfdiRel, "UUID", $value['uuid']);//PAT                   
                    }
                }
            }
            
            }else{
                setEventMessages('La factura a la que se hace referencia no esta timbrada 1: ', null, 'errors');
            }
        }else{
            setEventMessages('La factura a la que se hace referencia no esta timbrada 2: ', null, 'errors');
        }
     }
}



//EHM 
//$relextra = getRelType($object->ref);
/*
if (!empty($objsrelextra) and ($objdtl[0]['type']!='E'|| $objdtl[0]['type']!='I') ) {		
    
    $cfdiRels = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionados",$mainElmnt);//PAT

    foreach ($objsrelextra as $key => $value) {
        
        $objsrelextraRel = getRelInv($id."' and reltype != '".$value['reltype']."'");
        
        if (!empty($objsrelextraRel)  ) {
            createAttribt($xml, $cfdiRel, "TipoRelacion", $value['reltype']);//PAT            
            foreach ($objsrelextraRel as $keyI => $valueII)
            {
                $cfdiRel = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionado",$cfdiRels );//PAT                
                createAttribt($xml, $cfdiRel, "UUID", $valueII['uuid']);//PAT                
            }
            $cfdiRels->appendChild($TipoRelacion);
       }		
   }
}
*/

/********/
//emisro------
$emisor = crtElmnt($xml, 'http://www.sat.gob.mx/cfd/4',"cfdi:Emisor", $mainElmnt);//PAT
//createAttribt($xml, $emisor, "RegimenFiscal", $conf->global->MAIN_INFO_SOCIETE_OBJECT);//PAT
//createAttribt($xml, $emisor, "Nombre","CRASA REPRESENTACIONES SA DE CV");//PAT
//createAttribt($xml, $emisor, "Rfc","CRE140120TI9");//PAT
createAttribt($xml, $emisor, "RegimenFiscal", $conf->global->MAIN_INFO_SOCIETE_OBJECT);//PAT
createAttribt($xml, $emisor, "Nombre",$conf->global->MAIN_INFO_ACCOUNTANT_NAME);//PAT
createAttribt($xml, $emisor, "Rfc",$conf->global->MAIN_INFO_SIREN);//PAT
//Fin emisor------

//Receptor------
$custArray=array('Rfc','Nombre','DomicilioFiscalReceptor','RegimenFiscalReceptor','UsoCFDI');
$Receptor =crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Receptor", $mainElmnt);//PAT
// var_dump($objGrl[0]);
foreach ($custArray as $key => $value)
{
    if($value == 'Nombre' && $objGrl[0]["Rfc"]=="XAXX010101000" && $objGrl[0]['TipoDeComprobante']=='E'){// add from MACV
        createAttribt($xml, $Receptor, $value, 'PUBLICO EN GENERAL');// add from MACV
        
    }else{// add from MACV
        createAttribt($xml, $Receptor, $value, $objGrl[0][$value]);//PAT
    }// add from MACV
   
}
//Fin Receptor------
//conceptos-------
$retTaxBoo = false;
$trasTaxBoo = false;

$objdtl = getDtlInv($id);
$concepts = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Conceptos", $mainElmnt);//PAT

$array=array('ClaveProdServ','ClaveUnidad','NoIdentificacion','Cantidad','Unidad','Descripcion','ValorUnitario','Importe','Descuento','ObjetoImp');
$arraytax=array('Base','Impuesto','TipoFactor','TasaOCuota','Importe');


foreach ($objdtl as $key => $value2) {

    $concept = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Concepto",$concepts);//PAT
    
    // creaAtributos Conceptos.
    foreach ($array as $key => $value) {        
        $strVal = str_replace($search, $replace,$value2[$value]);        
        $strVal = str_replace("&iacute;","i",$strVal);
        $strVal = str_replace("&Ntilde;","Ñ",$strVal);
        $strVal = str_replace("&nbsp;"," ",$strVal);
        $strVal = str_replace("[\n|\r|\n\r]", "",$strVal);
        if ($objGrl[0]['TipoDeComprobante']=='E' and $value == 'ClaveUnidad') {

            createAttribt($xml, $concept, $value , 'ACT'); 
        }elseif ($objGrl[0]['TipoDeComprobante']=='E' and $value == 'ClaveProdServ') {
            
            createAttribt($xml, $concept, $value , '84111506'); 
        }else if($objGrl[0]["Rfc"]=="XAXX010101000" and $objGrl[0]['TipoDeComprobante']=='I' and  $value == 'ClaveProdServ' ){
            // add ClaveProdServ generic 01010101 in facture to general public macv
            createAttribt($xml, $concept, $value , '01010101');   
        }else if($objGrl[0]["Rfc"]=="XAXX010101000" and $objGrl[0]['TipoDeComprobante']=='I' and  $value == 'ClaveUnidad' ){
            // add ClaveUnidad generic ACT in facture to general public macv
            createAttribt($xml, $concept, $value , 'ACT');   
        }else if($objGrl[0]["Rfc"]=="XAXX010101000" and $objGrl[0]['TipoDeComprobante']=='I' and  $value == 'Descripcion' ){
            // add Descripcion generic Venta in facture to general public macv
            createAttribt($xml, $concept, $value , 'Venta');   
        }else{
            createAttribt($xml, $concept, $value , $strVal); 
        }
              
        
    }
    if($value2["vat_src_code"] == 'IVAIEPSXAC'){
        // Traslados
        $taxDtl = getTrasTaxIEPS($value2["fk_facture"], $value2["rowid"]);
        if(count($taxDtl))
        {
            $taxes=crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Impuestos",$concept);//PAT
            $transTax=crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Traslados",$taxes);//PAT        
            foreach ($taxDtl as $taxKey => $taxValue){
                $tranTax=crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Traslado",$transTax);//PAT
                $trasTaxBoo = true;
                foreach ($arraytax as $transKey => $transValue){

                    //createAttribt($xml,$tranTax,$transValue, $taxValue[$transValue]);//PAT
                    if($transValue == 'Importe'){
                        $valorSinRedonear = number_format($taxValue[$transValue], 2, '.', '');// macv 02/07/2024 se agrega linea para dos decimales en importe
                        createAttribt($xml,$tranTax,$transValue, $valorSinRedonear);//PAT
                    }else{
                        createAttribt($xml,$tranTax,$transValue, $taxValue[$transValue]);//PAT
                    }
                }
            }
        }
    }else{ 
        // Traslados
        $taxDtl = getTrasTax($value2["fk_facture"], $value2["rowid"]);
        if(count($taxDtl))
        {
            $taxes=crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Impuestos",$concept);//PAT
            $transTax=crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Traslados",$taxes);//PAT        
            foreach ($taxDtl as $taxKey => $taxValue){
                $tranTax=crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Traslado",$transTax);//PAT
                $trasTaxBoo = true;
                foreach ($arraytax as $transKey => $transValue){
                    createAttribt($xml,$tranTax,$transValue, $taxValue[$transValue]);//PAT
                }
            }
        }
    }
    
    // Retenciones
    $taxRetDtl = getRetTax($value2["fk_facture"], $value2["rowid"]);   
    if(count($taxRetDtl))
    {
        if(!$taxes)
            $taxes=crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Impuestos",$concept);//PAT
        
        $retsTax =crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Retenciones",$taxes);//PAT 
        foreach ($taxRetDtl as $taxKey => $taxValue){
            $retTax = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Retencion", $retsTax);//PAT
            $retTaxBoo = true;
            foreach ($arraytax as $retKey => $retValue){                
                createAttribt($xml,$retTax,$retValue, $taxValue[$retValue]);//PAT                
            }
        }
    }
 }

 //fin conceptos---- 
 
 
 
    $arrayTotTran=array('Impuesto','TipoFactor','TasaOCuota','Importe','Base');    
        
    $totTax=crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Impuestos",$mainElmnt);//PAT
    
    $totTaxAmt=array($trasTaxBoo==true?'TotalImpuestosTrasladados':'NA',$retTaxBoo==true?'TotalImpuestosRetenidos':'NA');
    // Totales Ret y Tras
    foreach ($totTaxAmt as $keyTAmt => $valueTAmt)
    {
        if($valueTAmt !='NA')
            createAttribt($xml, $totTax, $valueTAmt, $objGrl[0][$valueTAmt]);//PAT    
    }
    
    //impuesto total Retenidos
    $taxRetTot = getRetTotTax($id);
    $arrayTorRet=array('Impuesto','Importe');    
    
    if(!$totTax)        
        $totTax=crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Impuestos",$mainElmnt);//PAT
    
    if(count($taxRetTot))
    {
        $totTRet = crtElmnt($xml,"http://www.sat.gob.mx/cfd/4","cfdi:Retenciones",$totTax);//PAT
        foreach ($taxRetTot as $taxTRKey => $taxTRValue){
            $totRet = crtElmnt($xml,"http://www.sat.gob.mx/cfd/4","cfdi:Retencion", $totTRet);//PAT            
            foreach ($arrayTorRet as $retTKey => $retTValue){                
                createAttribt($xml,$totRet ,$retTValue, $taxTRValue[$retTValue]);//PAT
            }
        }
    }
    
    
    //impuesto total trasladados
 
    $taxTransTot = getTrasTotTax($id);
    if(count($taxTransTot))
    {
        $totTTras = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Traslados",$totTax);//PAT
        foreach ($taxTransTot as $taxTTKey => $taxTTValue){
            $totTras = crtElmnt($xml,"http://www.sat.gob.mx/cfd/4","cfdi:Traslado", $totTTras);//PAT
            foreach ($arrayTotTran as $trasTKey => $trasTValue){
                $valorPositivo = '';
                $valorPositivo = str_replace('-','',$taxTTValue[$trasTValue]);
                createAttribt($xml,$totTras ,$trasTValue, $valorPositivo);//PAT
            }
        }
    }   
 
 //fin inpuesto total

//cadena original 

createPem($localPht, $certName, $certPsw);

$orgStr = getOrigStr($elcInvDir, $xml, $xlst);
//echo $orgStr;
$linea = stampStr($localPht, $certName, $orgStr);

//if (file_exists($pathCer.".key.pem"))
//        unlink($pathCer.".key.pem");
//
//if (file_exists($pathCer.".cer.pem"))
//        unlink($pathCer.".cer.pem");

//openssl.exe x509 -inform DER -in "aaa010101aaa_CSD_01.cer" -noout -startdate > "IniciaVigencia.txt"

//openssl.exe x509 -inform DER -in "aaa010101aaa_CSD_01.cer" -noout -enddate > "FinVigencia.txt"

//openssl.exe x509 -inform DER -in "aaa010101aaa_CSD_01.cer" -noout -serial > "Serial.txt"

//openssl.exe x509 -inform DER -in "aaa010101aaa_CSD_01.cer" > "Cert.txt"

$dir = DOL_DATA_ROOT."/facture/".$object->ref."/";
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$Sello=$xml->createAttribute('Sello');
$Sello->value=$linea;
$mainElmnt->appendChild($Sello);

$xml->formatOutput = true;
$el_xml = $xml->saveXML();
$ruta=DOL_DATA_ROOT."/facture/".$object->ref."/".$object->ref.".xml";
$xml->save($ruta);
    
$update = "UPDATE ".MAIN_DB_PREFIX."facture_extrafields SET";
$update.= "  stampcfdi = '". $db->escape($orgStr)."'";
$update.= " WHERE fk_object = ".$id;
$up=$db->query($update);



unset($_SESSION['valid']);

delFilesCFDI($localPht, $certName,$object->ref);

$model = $certName;

//function getObjFetch($societee, $db)
//{
//    $ressociete = $db->query($societee);
//    $objsociete = $ressociete ->fetch_all(MYSQLI_ASSOC);
//    return $objsociete;
//}


function delFilesCFDI($localPht, $certName,$id)
{

//    $pthFiles = "./stamp.txt";
//    if (file_exists($pthFiles) )
//    unlink($pthFiles);
    /*
    $pthFiles = $localPht.$certName."/".$certName.".key.pem";
    if (file_exists($pthFiles) ) 
      unlink($pthFiles);
    
    $pthFiles = $localPht.$certName."/".$certName.".cer.pem";
    if (file_exists($pthFiles) ) 
      unlink($pthFiles);
    */
    $certDir = cfdiCertDir($certName);
    if ($certDir === '') return;

    $pthFiles = $certDir."Serial.txt";
    if (file_exists($pthFiles) )
      unlink($pthFiles);

    $pthFiles = $certDir."Cert.txt";
    if (file_exists($pthFiles) )
      unlink($pthFiles);
 
//    $pthFiles = "./".$id.".txt";
//    if (file_exists($pthFiles) ) 
//      unlink($pthFiles);      
}
function crtElmnt($xml, $nameSpace, $elmName, $parentNd )
 {
     $element = $xml->createElementNS($nameSpace,$elmName);
     $parentNd->appendChild($element);

     return $element;
 }

 function createAttribt($xml, $element,$attName, $val)
 {  
     $attrbt= $xml->createAttribute($attName);
     $attrbt->value=$val;
     $element->appendChild($attrbt);       
 }
 function createAttribtDNS($xml, $element,$nameSpace, $attName, $val)
 {  
     $attrbt= $xml->createAttributeNS($nameSpace, $attName);
     $attrbt->value=$val;
     $element->appendChild($attrbt);       
 }
