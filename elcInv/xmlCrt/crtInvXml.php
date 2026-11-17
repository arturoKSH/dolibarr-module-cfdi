<?php

//$xlst = "/xslt/origStr40.xslt";
include(DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/xmlCrt/stampCfdi.php'); //ehm
include(DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/dbExc/excFetch.php'); //ehm
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

$xmlPath = DOL_DATA_ROOT."/facture/".$object->ref."/".$object->ref.".xml";
$abortInvXml = function ($msg) use ($xmlPath) {
    // Sin borrarlo, Timbrar mandaría el XML de una generación anterior.
    if (file_exists($xmlPath)) {
        dol_delete_file($xmlPath);
    }
    setEventMessages($msg.' No se generó el XML CFDI.', null, 'errors');
};

if (empty($certName)) {
    $abortInvXml('Certificado SAT no configurado. Suba el .cer y .key en CFDI > Configuración.');
    return;
}

$search  = array('-', ',');
$replace = array('', '');

// SubTotal, Descuento y Total no van aquí: se calculan al final con la suma de los conceptos.
$grlArray=array('Moneda','MetodoPago',
                'FormaPago','CondicionesDePago','TipoDeComprobante',
                'Fecha','Folio','Serie','LugarExpedicion','Version','Exportacion');

$objGrl =  getCustInf($id);
$excQryError = '';
if (excQryFailed($excQryError) || empty($objGrl)) {
    $abortInvXml('No se pudieron obtener los datos fiscales principales de la factura.');
    return;
}

$missingReceptor = array();
foreach (array('Rfc', 'Nombre', 'DomicilioFiscalReceptor', 'RegimenFiscalReceptor', 'UsoCFDI') as $field) {
    // En NC a público en general el Nombre se fija más abajo como 'PUBLICO EN GENERAL'.
    if ($field == 'Nombre' && $objGrl[0]['Rfc'] == 'XAXX010101000' && $objGrl[0]['TipoDeComprobante'] == 'E') {
        continue;
    }
    if (empty($objGrl[0][$field])) {
        $missingReceptor[] = $field;
    }
}
if (!empty($missingReceptor)) {
    $abortInvXml('Faltan datos fiscales del receptor: '.implode(', ', $missingReceptor).'.');
    return;
}

$xml = new DOMdocument('1.0', 'UTF-8');

$mainElmnt = crtElmnt ($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Comprobante",$xml);// PAT
foreach ($grlArray as $key => $value)
{
    if(isset($objGrl[0][$value]) && $objGrl[0][$value] !== ''){
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
// Acumulados de los conceptos (millonésimas, ver cfdiMicros()) para los totales del comprobante.
$sumImporte = 0;
$sumDescuento = 0;
$trasTot = array();   // Impuesto|TipoFactor|TasaOCuota => Base, Importe
$retTot = array();    // Impuesto => Importe

$objdtl = getDtlInv($id);
if (excQryFailed($excQryError) || empty($objdtl)) {
    $abortInvXml('No se pudieron obtener los conceptos de la factura.');
    return;
}
$concepts = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Conceptos", $mainElmnt);//PAT

$array=array('ClaveProdServ','ClaveUnidad','NoIdentificacion','Cantidad','Unidad','Descripcion','ValorUnitario','Importe','Descuento','ObjetoImp');


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
    // Montos del concepto en millonésimas, los mismos que acaban de escribirse en sus atributos.
    $cptImporte = cfdiMicros($value2['Importe']);
    $cptDescuento = cfdiMicros($value2['Descuento']);
    $cptBase = $cptImporte - $cptDescuento;
    $sumImporte += $cptImporte;
    $sumDescuento += $cptDescuento;

    // De la BD solo se toma qué impuestos y tasas lleva la línea; Base e Importe se calculan sobre
    // el concepto. Antes la Base era total_ht (ya redondeado a 2) y no cuadraba con Importe - Descuento.
    $ieps = $value2["vat_src_code"] == 'IVAIEPSXAC';
    $taxDtl = $ieps ? getTrasTaxIEPS($value2["fk_facture"], $value2["rowid"]) : getTrasTax($value2["fk_facture"], $value2["rowid"]);
    $taxRetDtl = getRetTax($value2["fk_facture"], $value2["rowid"]);
    $taxes = null;
    if (count($taxDtl) || count($taxRetDtl)) {
        $taxes = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Impuestos",$concept);//PAT
    }

    if (count($taxDtl)) {
        // IVAIEPSXAC: el IVA se calcula sobre el precio más el IEPS.
        $iepsImporte = 0;
        if ($ieps) {
            foreach ($taxDtl as $taxValue) {
                if ($taxValue['Impuesto'] == '003') {
                    $iepsImporte += cfdiMulRate($cptBase, cfdiMicros($taxValue['TasaOCuota']));
                }
            }
        }
        $transTax = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Traslados",$taxes);//PAT
        foreach ($taxDtl as $taxValue) {
            $tasa = cfdiMicros($taxValue['TasaOCuota']);
            $base = ($ieps && $taxValue['Impuesto'] == '002') ? $cptBase + $iepsImporte : $cptBase;
            $importe = cfdiMulRate($base, $tasa);
            $tranTax = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Traslado",$transTax);//PAT
            createAttribt($xml, $tranTax, 'Base', cfdiFmt($base, 6));
            createAttribt($xml, $tranTax, 'Impuesto', $taxValue['Impuesto']);
            createAttribt($xml, $tranTax, 'TipoFactor', $taxValue['TipoFactor']);
            createAttribt($xml, $tranTax, 'TasaOCuota', cfdiFmt($tasa, 6));
            createAttribt($xml, $tranTax, 'Importe', cfdiFmt($importe, 6));

            $grp = $taxValue['Impuesto'].'|'.$taxValue['TipoFactor'].'|'.$tasa;
            if (!isset($trasTot[$grp])) {
                $trasTot[$grp] = array('Impuesto' => $taxValue['Impuesto'], 'TipoFactor' => $taxValue['TipoFactor'], 'TasaOCuota' => $tasa, 'Base' => 0, 'Importe' => 0);
            }
            $trasTot[$grp]['Base'] += $base;
            $trasTot[$grp]['Importe'] += $importe;
        }
    }

    if (count($taxRetDtl)) {
        $retsTax = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Retenciones",$taxes);//PAT
        foreach ($taxRetDtl as $taxValue) {
            $tasa = cfdiMicros($taxValue['TasaOCuota']);
            $importe = cfdiMulRate($cptBase, $tasa);
            $retTax = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Retencion", $retsTax);//PAT
            createAttribt($xml, $retTax, 'Base', cfdiFmt($cptBase, 6));
            createAttribt($xml, $retTax, 'Impuesto', $taxValue['Impuesto']);
            createAttribt($xml, $retTax, 'TipoFactor', $taxValue['TipoFactor']);
            createAttribt($xml, $retTax, 'TasaOCuota', cfdiFmt($tasa, 6));
            createAttribt($xml, $retTax, 'Importe', cfdiFmt($importe, 6));

            $retTot[$taxValue['Impuesto']] = ($retTot[$taxValue['Impuesto']] ?? 0) + $importe;
        }
    }
 }

 //fin conceptos----

// Totales: el SAT valida cada uno contra el redondeo de la suma de los conceptos, así que se
// calculan de ahí y no de los totales de Dolibarr (que suman líneas ya redondeadas).
$totTraslados = 0;
$totRetenidos = 0;
foreach ($trasTot as $grp) {
    $totTraslados += cfdiRoundMicros($grp['Importe'], 2);
}
foreach ($retTot as $importe) {
    $totRetenidos += cfdiRoundMicros($importe, 2);
}
if (count($trasTot) || count($retTot)) {
    $totTax = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Impuestos",$mainElmnt);//PAT
    if (count($retTot)) {
        createAttribt($xml, $totTax, 'TotalImpuestosRetenidos', cfdiFmt($totRetenidos, 2));
    }
    if (count($trasTot)) {
        createAttribt($xml, $totTax, 'TotalImpuestosTrasladados', cfdiFmt($totTraslados, 2));
    }
    if (count($retTot)) {
        $totTRet = crtElmnt($xml,"http://www.sat.gob.mx/cfd/4","cfdi:Retenciones",$totTax);//PAT
        foreach ($retTot as $imp => $importe) {
            $totRet = crtElmnt($xml,"http://www.sat.gob.mx/cfd/4","cfdi:Retencion", $totTRet);//PAT
            createAttribt($xml, $totRet, 'Impuesto', $imp);
            createAttribt($xml, $totRet, 'Importe', cfdiFmt($importe, 2));
        }
    }
    if (count($trasTot)) {
        $totTTras = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Traslados",$totTax);//PAT
        foreach ($trasTot as $grp) {
            $totTras = crtElmnt($xml,"http://www.sat.gob.mx/cfd/4","cfdi:Traslado", $totTTras);//PAT
            createAttribt($xml, $totTras, 'Impuesto', $grp['Impuesto']);
            createAttribt($xml, $totTras, 'TipoFactor', $grp['TipoFactor']);
            createAttribt($xml, $totTras, 'TasaOCuota', cfdiFmt($grp['TasaOCuota'], 6));
            createAttribt($xml, $totTras, 'Importe', cfdiFmt($grp['Importe'], 2));
            createAttribt($xml, $totTras, 'Base', cfdiFmt($grp['Base'], 2));
        }
    }
}

$subTotal = cfdiRoundMicros($sumImporte, 2);
$descuento = cfdiRoundMicros($sumDescuento, 2);
$total = $subTotal - $descuento + $totTraslados - $totRetenidos;
createAttribt($xml, $mainElmnt, 'SubTotal', cfdiFmt($subTotal, 2));
createAttribt($xml, $mainElmnt, 'Descuento', cfdiFmt($descuento, 2));
createAttribt($xml, $mainElmnt, 'Total', cfdiFmt($total, 2));
// Para medir qué tan seguido el CFDI difiere por centavos de lo cobrado (subprice guardado a 5 decimales).
if ($total != cfdiRoundMicros(cfdiMicros($objGrl[0]['Total']), 2)) {
    dol_syslog('CFDI '.$object->ref.': Total del XML '.cfdiFmt($total, 2).' distinto al de la factura '.$objGrl[0]['Total'], LOG_WARNING);
}

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
