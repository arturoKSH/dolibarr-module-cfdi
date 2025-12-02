<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
use PhpCfdi\XmlCancelacion\Capsules\Cancellation;
/*
use PhpCfdi\XmlCancelacion\Capsules\ObtainRelated;
use PhpCfdi\XmlCancelacion\Capsules\CancellationAnswer;
use PhpCfdi\XmlCancelacion\Capsules\CapsuleInterface;
*/
use PhpCfdi\XmlCancelacion\Signers\DOMSigner;
use PhpCfdi\XmlCancelacion\Credentials;
require '../CancelSat/composer/vendor/autoload.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
//require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmargin.class.php';

include(DOL_DOCUMENT_ROOT.'/elcInv/xmlCrt/stampCfdi.php'); //ehm



$sql=' select uuid from '.MAIN_DB_PREFIX.'paiement ';
//$sql.=' where rowid = '.$id;
if (!empty($id)) {
	$sql.=" where rowid=".$id;
}else{
	$sql.=" where ref='".$ref."'";

}
$ressql = $db->query($sql);
$objsql = $ressql->fetch_all(MYSQLI_ASSOC);

$objectt = new Facture($db);

addBtn($objsql, $id); //ehm

$reff="select ref, rowid,fk_bank from ".MAIN_DB_PREFIX."paiement ";
if (!empty($id)) {
	$reff.=" where rowid=".$id;
}else{
	$reff.=" where ref='".$ref."'";

}
$reffres=$db->query($reff);
$reffobj=$reffres->fetch_all(MYSQLI_ASSOC); 

if (empty($id)) {
	$id=$reffobj[0]['rowid'];
}
/*
 * Actions
 */

if (empty($id)) $id=$id;
$trigger_name='BILL_SENTBYMAIL';
$paramname='id';
$autocopy='MAIN_MAIL_AUTOCOPY_INVOICE_TO';
$trackid='inv'.$id;
if ($action=='builddoc') {
	$db->begin();
        
	include(DOL_DOCUMENT_ROOT.'/elcInv/infPdf/pdf.php');
        //include('./pdf.php');
	$outputlangs = $langs;
	$pdfP= new pdf();
	$pdfP->__construct($db);
	$pdfP->generar($id,$outputlangs,$db,$ref);
    print "aaaaaaaaaaaaa/".$id."/".$outputlangs."/".$db."/".$ref;
}


if ($action=='pdfReg') {
	$carpeta = DOL_DATA_ROOT."/facture/".$reffobj[0]['ref'];
	$ruta=$carpeta.'/'.$reffobj[0]['ref'].".xml";
	
	$_SESSION['timbrar']='yes';
        
        include(DOL_DOCUMENT_ROOT.'/elcInv/phpqrcode/qr.php');
	
	 
	
	$db->begin();
	include(DOL_DOCUMENT_ROOT.'/elcInv/infPdf/pdf.php');
	$outputlangs = $langs;
	$pdfP= new pdf();
	$pdfP->__construct($db);
	$pdfP->generar($id,$outputlangs,$db,$ref);
	unset($_SESSION["timbrar"]);
}


$datepp='';
$notee='';
if (empty($objsql[0]['uuid'])) {
	$datepp='datep';
	$notee='note';
}


//action edit parcial_num
if (isset($_POST['actionmodify'])) {

	$sql= ' UPDATE '.MAIN_DB_PREFIX.'paiement_facture set num_parcial='.$_POST['parcialidad'];
	$sql.=' where fk_paiement='.$id.' and fk_facture='.$_GET['facid'];
	$execute= $db->query($sql);
}

if ($action == 'Timbrar')
{
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans("Confirmar tibrado"), $langs->trans("¿Esta seguro de timbrar este pago?"), 'confirm_timbrar', '', 0, 2);
}
if ($action == 'Cancelcfdi') {
    
    $form_question = array();
		$reason = array(
			"01" => "01 Comprobantes emitidos con errores con relación",
			"02" => "02 Comprobantes emitidos con errores sin relación",
			"03" => "03 No se llevó a cabo la operación",
			"04" => "04 Operación nominativa relacionada en una factura global");                
		
		
		$form_question['reason'] = array(
		'name' => 'reason',
		'type' => 'select',
		'label' => 'Motivo',
		'values' => $reason,
		'default' => '0'
				);
	   
		
		$sql= " select a.ref, a.uuid ";
		$sql.= " from ".MAIN_DB_PREFIX."paiement a inner join ".MAIN_DB_PREFIX."paiement_facture b ";
		$sql.= " on      a.rowid = b.fk_paiement inner join ".MAIN_DB_PREFIX."facture c ";
		$sql.= " on      b.fk_facture = c.rowid ";
		$sql.= " where c.fk_soc  = ".$objp->socid." ";
		$sql.= " and 	c.rowid not in(select rowid from ".MAIN_DB_PREFIX."paiement where rowid = ".$object->id.") ";
		$sql.= " and a.uuid is not null ";
        $sql.= " group by a.uuid, a.ref ";
		$sql.= " order by a.rowid desc ";
		$uuid=$db->query($sql);
		$objUuid=$uuid->fetch_all(MYSQLI_ASSOC);		                
		$uuidArray = array();
		
		foreach ($objUuid as $key => $value) {                    
			$uuidArray[$value['uuid']] = "|".$value['ref']."|".$value['uuid'];
		}
		$form_question['uuid'] = array(
		'name' => 'UUID',
		'type' => 'select',
		'label' => 'Sustituye a UUID',
		'values' => $uuidArray,
		'default' => ''
		);

		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, "¿Esta seguro de Cancelar CFDI de este pago?", 'Enviada la solicitud de cancelación, ya no es posible revertirla en Dolibarr.', 'confirm_Cancel_CFDI', $form_question, 0, 1, 300);
		
	
    
    //print $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans("Confirmar Cancelado CFDI"), $langs->trans("¿Esta seguro de Cancelar CFDI de este pago?"), 'confirm_Cancel_CFDI', '', 0, 2);
        
        
        
}


if ($action == 'confirm_timbrar' && $confirm == 'yes') {
	
	$carpeta = DOL_DATA_ROOT."/facture/".$reffobj[0]['ref'];
	$ruta=$carpeta.'/'.$reffobj[0]['ref'].".xml";
	
	$_SESSION['timbrar']='yes';
    $table = 'paiement';
    $field = 'rowid';    
        
        include(DOL_DOCUMENT_ROOT.'/elcInv/stamp/ateb.php'); //ehm
	//include('../soap.php');
	 
	
	$db->begin();
      include(DOL_DOCUMENT_ROOT.'/elcInv/infPdf/pdf.php');  
	//include(DOL_DOCUMENT_ROOT.'/elcInv/infPdf/pdf.php'); //ehm
	$outputlangs = $langs;
	$pdfP= new pdf();
	$pdfP->__construct($db);
	$pdfP->generar($id,$outputlangs,$db,$ref);
}
if ($action == 'abandono'){
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans("DeletePayment"), $langs->trans("El pago perdera la relacion con las facturas, ¿desea clasificarlo como abandonado? "), 'confirm_delete_relation', '', 0, 2);
}
if ($action == 'confirm_delete_relation' && $confirm == 'yes') {

	$sql= ' UPDATE '.MAIN_DB_PREFIX.'paiement_facture set fk_fac_delete= fk_facture, fk_facture = NULL ,status=1';
	$sql.=' where fk_paiement='.$object->id;
	$execute= $db->query($sql);
}


if ($action == 'confirm_Cancel_CFDI' && $confirm == 'yes') {
    $reason = GETPOST('reason', 'alpha');
    $uuidRpl = "";
    if($reason == "01")
    {
        $uuidRpl = GETPOST('UUID', 'alpha');
        if($uuidRpl == "-1")
        {
            $langs->load("errors");
            setEventMessages($object->error, "Cuando el motivo es “01”, deben seleccionar el UUID a sustituir.", 'errors');
            $action = '';        
        }
    }
    
	include('../CancelSat/soapcancel.php');
	$object->fetch($id);

	$sql = "select  siren RFC, a.uuid
			from    llx_paiement a inner join llx_paiement_facture b
			on      a.rowid = b.fk_paiement inner join llx_facture c
			on      b.fk_facture = c.rowid inner join llx_societe d
			on      c.fk_soc = d.rowid
			where   a.rowid =" . $id;
	$ressql = $db->query($sql);
	$resultC = $ressql->fetch_all(MYSQLI_ASSOC);

	//uuid
	$sql = "select  a.uuid
			from    llx_paiement a 
			where   a.rowid =" . $id;

            
	$ressql = $db->query($sql);
	$result = $ressql->fetch_all(MYSQLI_ASSOC);

	$datosempresa = " SELECT name,value ";
	$datosempresa .= " FROM " . MAIN_DB_PREFIX . "const where name like 'MAIN_INFO%'";
	$resdatosempresa = $db->query($datosempresa);
	$objdatosempresa = $resdatosempresa->fetch_all(MYSQLI_ASSOC);
	$datoss = array('MAIN_INFO_SOCIETE_NOM', 'MAIN_INFO_SIREN', 'MAIN_INFO_SOCIETE_ZIP', 'MAIN_INFO_SOCIETE_OBJECT', 'MAIN_INFO_SOCIETE_CERTIFICATE', 'MAIN_INFO_SOCIETE_CERTIFICATE_VALUE');
	$NameCompany = "";
	$RfcEmisor = "";
	$CodigoPostal = "";
	foreach ($objdatosempresa as $key => $value) {
		if ($value['name'] == $datoss[1]) {
			$RfcEmisor = $value['value'];
		}
	}
	/* 
		Generacion de XML 
	*/
    createPem($localPht, $certName, $certPsw);

    $certN = $conf->global->MAIN_INFO_CFDI_CERT_NAME;
	$credentials = new Credentials(
    DOL_DOCUMENT_ROOT.'/elcInv/'.$certN.'/'.$certN.'.cer.pem',
    DOL_DOCUMENT_ROOT.'/elcInv/'.$certN.'/'.$certN.'.key.pem',
    $conf->global->MAIN_INFO_CFDI_CERT_PSW);          
            
            
	//cancelar
    
    $uuidRpl =  $result[0]['uuid'];// ehm se agrego
	$dataCancelCfdi = new Cancellation($RfcEmisor, $result[0]['uuid'], new DateTimeImmutable(),$reason,$uuidRpl);
	$Rute = DOL_DATA_ROOT."/facture/".$reffobj[0]['ref']."/".$reffobj[0]['ref']."-SolicitudCancel.xml";
	$xml = (new DOMSigner())->signCapsule($dataCancelCfdi, $credentials, $Rute);
	$ruta = DOL_DATA_ROOT . "/facture/" . $reffobj[0]['ref'] . "/" . $reffobj[0]['ref'] . "-SolicitudCancel.xml";
	$xmlCancelCFDI = CancelaCfdi($db, $ruta);

	//aceptadaorechazada
	// $dataAccept = new CancellationAnswer($RfcEmisor, $resultC[0]['uuid'], "Aceptacion", $resultC[0]['RFC'], new DateTimeImmutable());
	// $Rute = "../../../../../dolibarr_documents/facture/" . $reffobj[0]['ref'] . "/" . $reffobj[0]['ref'] . "-FinalCancelAceptadasRechazadas.xml";
	// $xml = (new DOMSigner())->signCapsule($dataAccept, $credentials, $Rute);
	// $ruta = DOL_DATA_ROOT."/facture/".$reffobj[0]['ref']."/".$reffobj[0]['ref']."-FinalCancelAceptadasRechazadas.xml";
	// $xmldataAccept=AcepRechazoCfdi($db,$ruta);

	// //relacionadas
	// $data3 = new ObtainRelated($resultC[0]['uuid'], $RfcEmisor, '', $resultC[0]['RFC']);
	// $Rute = "../../../../../dolibarr_documents/facture/" . $reffobj[0]['ref'] . "/" . $reffobj[0]['ref'] . "-Relacion.xml";
	// $xml = (new DOMSigner())->signCapsule($data3, $credentials, $Rute);
	// $ruta = DOL_DATA_ROOT . "/facture/" . $reffobj[0]['ref'] . "/" . $reffobj[0]['ref'] . "-Relacion.xml";
	// $xmlrelCFDI = RelacionadosCfdi($db, $ruta);

	$outputlangs = $langs;


	include('../CancelSat/pdfCancel.php');
	$CancelCfdiPDF = new pdf();
	$CancelCfdiPDF->__construct($db);
	$CancelCfdiPDF->generar($id, $outputlangs, $db, $reffobj[0]['ref'], $xmlCancelCFDI);
	//$CancelCfdiPDF->generar($id, $outputlangs, $db, $reffobj[0]['ref'], $xmldataAccept);

	// include('../CancelSat/pdfCancelAR.php');
	// $AcepRechazoPDF = new pdfAR();
	// $AcepRechazoPDF->__construct($db);
	// $AcepRechazoPDF->generar2($id, $outputlangs, $db, $reffobj[0]['ref'], $xmldataAccept);
	//$AcepRechazoPDF->generar2($id, $outputlangs, $db, $reffobj[0]['ref'], $xmlCancelCFDI);
}

    if ($action=='xml') {
                    //timbrar Pago
            include(DOL_DOCUMENT_ROOT.'/elcInv/dbExc/excFetch.php'); //ehm
            
            $objsociete = getCustInfPym($id);            
            $objdtl = getDtlPym($id);

            $NameCompany = $conf->global->MAIN_INFO_SOCIETE_NOM;
            $Rfcc = $conf->global->MAIN_INFO_SIREN;
            $CodigoPostal = $conf->global->MAIN_INFO_SOCIETE_ZIP;
            $RegimenFiscal = $conf->global->MAIN_INFO_SOCIETE_OBJECT;
            $certificate = $conf->global->MAIN_INFO_CFDI_CERT;
            $certificatevalue = $conf->global->MAIN_INFO_CFDI_CERT_VAL;


            $search  = array('-', ',');
            $replace = array('', '');

            //************************ehm
            $xml = new DOMdocument('1.0', 'UTF-8');
            
            $fact =  crtElmnt($xml, "http://www.sat.gob.mx/cfd/4", "cfdi:Comprobante", $xml);
            createAttribt($xml, $fact, "Total", "0");
            createAttribt($xml, $fact, "Moneda", "XXX");
            createAttribt($xml, $fact, "SubTotal", "0");
            createAttribt($xml, $fact, "TipoDeComprobante", "P");
            createAttribt($xml, $fact, "Fecha", $objsociete[0]['Fecha']);            
            createAttribt($xml, $fact, "Folio", substr($objdtl[0]['folioP'], -4));
            createAttribt($xml, $fact, "Serie", "P");
            createAttribt($xml, $fact, "LugarExpedicion", $CodigoPostal);
            createAttribt($xml, $fact, "Sello", "Sello");
            createAttribt($xml, $fact, "Certificado", $certificatevalue);
            createAttribt($xml, $fact, "NoCertificado", $certificate);
            createAttribt($xml, $fact, "Version", "4.0");
            createAttribt($xml, $fact, "Exportacion", "01");
            createAttribt($xml, $fact, "Exportacion", "01");
            createAttribtDNS($xml, $fact, 'http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', "http://www.sat.gob.mx/Pagos20 http://www.sat.gob.mx/sitio_internet/cfd/Pagos/Pagos20.xsd http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd");
            
            //docs relacionados
            $relextra = "SELECT * ";
            $relextra.= " FROM ".MAIN_DB_PREFIX."kshpay_rel ";
            $relextra.= " where fk_pay_parent= '".$id."'";
            
            $resrelextra = $db->query($relextra);
            $objsrelextra = $resrelextra ->fetch_all(MYSQLI_ASSOC);

            if (!empty($objsrelextra) ) {
                
                $cfdiRels = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionados", $fact);
                createAttribt($xml, $fact, "TipoRelacion", $objsrelextra[0]["reltype"]);
                
                foreach ($objsrelextra as $key => $value) {                    
                    $cfdiRel = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionado", $cfdiRels);
                    
                    createAttribt($xml, $cfdiRels, "UUID", $value["uuid"]);

                    //     $cfdiRel->appendChild($UIDDR); 
                    //     $cfdiRels->appendChild($TipoRelacion);

                }
                
            }


        //emisor------

        $emisor =  crtElmnt($xml, "http://www.sat.gob.mx/cfd/4","cfdi:Emisor",$fact);        
        createAttribt($xml, $emisor, "Rfc", $Rfcc);
        createAttribt($xml, $emisor, "Nombre", $NameCompany);
        createAttribt($xml, $emisor, "RegimenFiscal", $RegimenFiscal);        

        //Fin emisor------


         //Receptor------

        $Receptor =  crtElmnt($xml, "http://www.sat.gob.mx/cfd/4","cfdi:Receptor",$fact);

        createAttribt($xml, $Receptor, "Rfc", $objsociete[0]['siren']);
        //$Rfc->value=$objsociete[0]['siren']; 
        createAttribt($xml, $Receptor, "Nombre", $objsociete[0]['nom']);
        //$Nombrer->value=$objsociete[0]['nom'];
        createAttribt($xml, $Receptor, "DomicilioFiscalReceptor", $objsociete[0]['zip']);
        //$zipCd->value=$objsociete[0]['zip'];
        
        createAttribt($xml, $Receptor, "RegimenFiscalReceptor", $objsociete[0]['fiscalreg']);
        // $Receptor->appendChild($regimen);           //aqui edite
        createAttribt($xml, $Receptor, "UsoCFDI", "CP01");      
        
        //fin receptor

        //conceptos-------
        $factbody = crtElmnt($xml,"http://www.sat.gob.mx/cfd/4","cfdi:Conceptos",$fact);

        $conceptos= array('ClaveProdServ', 'Cantidad','ClaveUnidad', 'Descripcion', 'ValorUnitario', 'Importe','ObjetoImp' );
        $conceptosAtr=array('84111506', '1','ACT', 'Pago', '0', '0','01' );
        $cont=0;

        $concep = crtElmnt($xml,'http://www.sat.gob.mx/cfd/4',"cfdi:Concepto",$factbody);//aqui edite

        foreach ($conceptos as $key => $value) 
        {
            createAttribt($xml, $concep, $value, $conceptosAtr[$cont]);
            $cont++;
        }
        
        $Complement = crtElmnt($xml,"http://www.sat.gob.mx/cfd/4","cfdi:Complemento",$fact);

        $pagos = crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:Pagos",$Complement);
        
        createAttribt($xml, $pagos, "Version", "2.0");

        //Nodo Totales
/*
        ehm decimales

        $Arraytotales=array('TotalRetencionesIVA'=>'TotalRetencionesIVA',
                            //'TotalRetencionesISR'=>'TotalRetencionesISR',
                            'TotalRetencionesIEPS'=>'TotalRetencionesIEPS',
                            'TotalTrasladosBaseIVA16'=>'TotalTrasladosBaseIVA16',
                            'TotalTrasladosImpuestoIVA16'=>'TotalTrasladosImpuestoIVA16',
                            //'TotalTrasladosBaseIVA8'=>'TotalTrasladosBaseIVA8',
                            //'TotalTrasladosImpuestoIVA8'=>'TotalTrasladosImpuestoIVA8',
                            'TotalTrasladosBaseIVA0'=>'TotalTrasladosBaseIVA0',
                            'TotalTrasladosImpuestoIVA0'=>'TotalTrasladosImpuestoIVA0',
                            //'TotalTrasladosBaseIVAExento'=>'TotalTrasladosBaseIVAExento',
                            'MontoTotalPagos'=>'MontoTotalPagos'
        );
     
        $objTaxTot = getTotPymInf($id);
        
        $i=0;
        $totales = crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:Totales",$pagos);        
        foreach ($Arraytotales as $key => $value) {
            if(!empty($objTaxTot[$i][$key]))
                createAttribt($xml, $totales, $value, str_replace(",", "",$objTaxTot[$i][$key]));
        }
*/

        $totales = crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:Totales",$pagos);
        $Arraytotales=array('importe'=>'TotalRetencionesIVA');
        $objTaxTot = getTotPymInfRETIVA($id);
        $i=0;
        foreach ($Arraytotales as $key => $value) {
            if(!empty($objTaxTot[$i][$key]))
                //createAttribt($xml, $totales, $value, str_replace(",", "",$objTaxTot[$i][$key]));
                createAttribt($xml, $totales, $value, bcdiv($objTaxTot[$i][$key], '1', 2));
                
        }

        
        $Arraytotales=array('importe'=>'TotalRetencionesIEPS');
        $objTaxTot = getTotPymInfRETIEPS($id);        
        $i=0;        
        foreach ($Arraytotales as $key => $value) {
            if(!empty($objTaxTot[$i][$key]))
                //createAttribt($xml, $totales, $value, str_replace(",", "",$objTaxTot[$i][$key]));
                createAttribt($xml, $totales, $value, bcdiv($objTaxTot[$i][$key], '1', 2));
                
        }

        $Arraytotales=array('base'=>'TotalTrasladosBaseIVA16','importe'=>'TotalTrasladosImpuestoIVA16');
        $objTaxTot = getTotPymInfIVA16($id);        
        $i=0;        
        foreach ($Arraytotales as $key => $value) {
            if(!empty($objTaxTot[$i][$key]))
                //createAttribt($xml, $totales, $value, str_replace(",", "",$objTaxTot[$i][$key]));
                createAttribt($xml, $totales, $value, bcdiv($objTaxTot[$i][$key], '1', 2));
                
        }

        $Arraytotales=array('base'=>'TotalTrasladosBaseIVA0','importe'=>'TotalTrasladosImpuestoIVA0');
        $objTaxTot = getTotPymInfIVA0($id);        
        $i=0;        
        foreach ($Arraytotales as $key => $value) {
            if(!empty($objTaxTot[$i][$key]))
                //createAttribt($xml, $totales, $value, str_replace(",", "",$objTaxTot[$i][$key]));
                createAttribt($xml, $totales, $value, bcdiv($objTaxTot[$i][$key], '1', 2));
                
        }

        $Arraytotales=array('amount'=>'MontoTotalPagos');
        $objTaxTot = getTotalsPymInf($id);        
        $i=0;        
        foreach ($Arraytotales as $key => $value) {
            if(!empty($objTaxTot[$i][$key]))
                //createAttribt($xml, $totales, $value, str_replace(",", "",$objTaxTot[$i][$key]));
                createAttribt($xml, $totales, $value, bcdiv($objTaxTot[$i][$key], '1', 2));
                
        }
        
        //Nodo Totales

        $Arraypagos=array(  'fechapago'=>'FechaPago',
                            'formpagop'=>'FormaDePagoP',
                            'MonedaP'=>'MonedaP',
                            //'monto'=>'Monto',
                            'NumOper'=>'NumOperacion',
                            'TipoCambioP'=>'TipoCambioP'
                            
            );
        $i=0;           

        $pagoo = crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:Pago",$pagos);//aqui edite
    
        foreach ($Arraypagos as $key => $value) {
            if(!empty($objdtl[$i][$key]))
                createAttribt($xml, $pagoo, $value, str_replace(",", "",$objdtl[$i][$key]));
        }

        /* ehm monto */
        $monto=array(  'amount'=>'Monto');
        $i=0;                  
    
        $objTot = getTotalsPymInf($id);        
        
        foreach ($monto as $key => $value) {
            if(!empty($objTot[$i][$key]))                
                createAttribt($xml, $pagoo, $value, bcdiv($objTot[$i][$key], '1', 2));
                
        }       
        /*ehm monto  */


        


        $arrayDoct=array('IdDocumento' =>'IdDocumento',
            'serie'=> 'Serie',
            'folio'=> 'Folio',
            'MonedaDR'=>'MonedaDR', 
            'partialnum'=> 'NumParcialidad',
            'impsaldoanterior' => 'ImpSaldoAnt',
            'impPagado'=> 'ImpPagado',
            'saldoinsoluto'=> 'ImpSaldoInsoluto',
            'objetoimpdr'=>'ObjetoImpDR',
            'EquivalenciaDR'=>'EquivalenciaDR');
        
        $arrayDoctT=array('Base' =>'BaseDR',
                        'Impuesto' =>'ImpuestoDR',
                        'TipoFactor'=> 'TipoFactorDR',
                        'TasaOCuota'=> 'TasaOCuotaDR',
                        'Importe'=>'ImporteDR');
        
        foreach ($objdtl as $keyp => $valuep) {
            $Doctorel =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:DoctoRelacionado",$pagoo);
        
            foreach ($arrayDoct as $key => $value){               
                
                if ($key!='IdDocumento') {
                    createAttribt($xml, $Doctorel, $value, str_replace($search, $replace, $valuep[$key]));
                }else{
                    createAttribt($xml, $Doctorel, $value, str_replace(",", "", $valuep[$key]));
                }                    
            }            
            /*
            $transTax = getTotTaxPymInv($id, $valuep['invRowId']);
            $transTaxRet = getTotTaxRetPymInv($id, $valuep['invRowId']);
            */
            $transTax = getTotTaxTrasInv($id, $valuep['invRowId']);
            $transTaxRet = getTotalTaxRetnInv($id, $valuep['invRowId']);

            if($transTax || $transTaxRet)
                $taxPym =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:ImpuestosDR",$Doctorel);
        
            if($transTaxRet)
            {   
                $trasTTaxR =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:RetencionesDR",$taxPym);
                foreach ($transTaxRet as $keyT => $valueT) {
                    
                    $trasTax =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:RetencionDR",$trasTTaxR);
                    foreach ($arrayDoctT as $keyTArr => $valueTArr){
                        if($keyTArr == 'Base' || $keyTArr == 'Importe')
                            createAttribt($xml, $trasTax, $valueTArr, bcdiv($valueT[$keyTArr], '1', 2) );
                        else
                            createAttribt($xml, $trasTax, $valueTArr, str_replace(",", "", $valueT[$keyTArr]));
                    }
                    
                }
                
            }
            if($transTax)
            {                
                
                $trasTTax =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:TrasladosDR",$taxPym);
                foreach ($transTax as $keyT => $valueT) {
                    
                    $trasTax =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:TrasladoDR",$trasTTax);
                    foreach ($arrayDoctT as $keyTArr => $valueTArr){
                        if($keyTArr == 'Base' || $keyTArr == 'Importe')
                            createAttribt($xml, $trasTax, $valueTArr, bcdiv($valueT[$keyTArr], '1', 2));
                        else
                            createAttribt($xml, $trasTax, $valueTArr, str_replace(",", "", $valueT[$keyTArr]));
                    }
                    
                }
                
            }
           
            
            
         
        }
        /*
        $transTax = getTotTaxPymInf($id);
        $transTaxRet = getTotTaxRetPymInf($id);
       */
        $transTax = getTotalTaxTransPym($id);
        $transTaxRet = getTotalTaxRetnPym($id);
        if($transTax || $transTaxRet)
            $taxPymP =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:ImpuestosP",$pagoo);
        
        if($transTaxRet)
        {                
            $arrayDoctPR=array(
            'Impuesto' =>'ImpuestoP',            
            'Importe'=>'ImporteP');

            $trasTTaxRetP =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:RetencionesP",$taxPymP);
            foreach ($transTaxRet as $keyTRP => $valueTRP) {                    
                $trasTaxRP =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:RetencionP",$trasTTaxRetP);
                foreach ($arrayDoctPR as $keyTArrPR => $valueTArrPR){
                    if($keyTArrPR == 'Base' || $keyTArrPR == 'Importe')
                        createAttribt($xml, $trasTaxRP, $valueTArrPR, bcdiv($valueTRP[$keyTArrPR], '1', 2));
                    else
                        createAttribt($xml, $trasTaxRP, $valueTArrPR, str_replace(",", "", $valueTRP[$keyTArrPR]));
                }
                
            }
            
        }

        if($transTax)
        {                
            $arrayDoctP=array('Base' =>'BaseP',
            'Impuesto' =>'ImpuestoP',
            'TipoFactor'=> 'TipoFactorP',
            'TasaOCuota'=> 'TasaOCuotaP',
            'Importe'=>'ImporteP');

            $trasTTaxP =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:TrasladosP",$taxPymP);
            foreach ($transTax as $keyTP => $valueTP) {                    
                $trasTaxP =crtElmnt($xml,'http://www.sat.gob.mx/Pagos20',"pago20:TrasladoP",$trasTTaxP);
                foreach ($arrayDoctP as $keyTArrP => $valueTArrP){
                    if($keyTArrP == 'Base' || $keyTArrP == 'Importe')
                        createAttribt($xml, $trasTaxP, $valueTArrP, bcdiv($valueTP[$keyTArrP], '1', 2));
                    else
                        createAttribt($xml, $trasTaxP, $valueTArrP, str_replace(",", "", $valueTP[$keyTArrP]));
                }
                
            }
            
        }
        
        
            
        
        

        createPem($localPht, $certName, $certPsw);
        $orgStr = getOrigStr($localPht, $xml, $xlst);
        //print $orgStr;//aoz
        $linea = stampStr($localPht, $certName, $orgStr);

        $Sello=$xml->createAttribute('Sello');
        $Sello->value=$linea;
        $fact->appendChild($Sello);

        $xml->formatOutput = true;
        $el_xml = $xml->saveXML();

        $carpeta = DOL_DATA_ROOT."/facture/".$objdtl[0]['pago'];
        if (!file_exists($carpeta)) {
                mkdir($carpeta, 0777, true);
        }
        $ruta=$carpeta.'/'.$objdtl[0]['pago'].".xml";
        $xml->save($ruta);

            unlink($nomarchiv);
            unlink('./sello.txt');

            // $db->begin();
             include(DOL_DOCUMENT_ROOT.'/elcInv/infPdf/pdf.php');
            //  $outputlangs = $langs;
            //  $pdfP= new pdf();
            //  $pdfP->__construct($db);
            //  $pdfP->generar($id,$outputlangs,$db,$ref);
}


// file generated
//print '<div class="fichecenter"><div class="fichehalfleft">';
//print '<a name="builddoc"></a>'; // ancre
	
$filename =$reffobj[0]['ref'];
$filedir =  DOL_DATA_ROOT."/facture/".$reffobj[0]['ref'];
$urlsource = $_SERVER['PHP_SELF'] . '?id=' . $id;
$genallowed = $usercanread;
$delallowed = $usercancreate;

$formfile = new FormFile($db);
//$formmargin = new FormMargin($db);

print $formfile->showdocuments('facture', $filename, $filedir, $urlsource, $genallowed, $delallowed, $object->modelpdf, 1, 0, 0, 28, 0, '', '', '', $soc->default_lang);
$somethingshown = $formfile->numoffiles;

// Select mail models is same action as presend
if (GETPOST('modelselected', 'alpha')) {
	$action = 'presend';
}


$object->fetch($object->id);
$object->fetch_thirdparty();
// Presend form
$modelmail='facture_send';
$defaulttopic='PDF PAGO';
$diroutput = $conf->facture->dir_output;
$trackid = 'inv'.$object->id;
$KSHC = true;
include DOL_DOCUMENT_ROOT . '/core/actions_sendmails.inc.php';
include DOL_DOCUMENT_ROOT . '/core/tpl/card_presend.tpl.php';



function addBtn($objsql, $id)
{
    global $db, $langs, $conf;
     
    $cancelcfdi = "SELECT a.UUID ";
    $cancelcfdi.= " FROM ".MAIN_DB_PREFIX."kshCancelcfdi a ";
    $cancelcfdi.= " where a.UUID= '".$objsql[0]['uuid']."'";
    $resCancelcfdi = $db->query($cancelcfdi);
    $objCancelcfdi = $resCancelcfdi ->fetch_all(MYSQLI_ASSOC);
    
    
    if (!empty($objsql[0]['uuid'])) {
        print '<a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;action=pdfReg" title="'.$title_button.'">'.$langs->trans('Regenerar pdf').'</a>';
    }

    print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=presend&mode=init#formmailbeforetitle">' . $langs->trans('SendMail') . '</a></div>';

    print '<a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;action=abandono" title="'.$title_button.'">'.$langs->trans('Clasificar cancelado').'</a>';

    if (empty($objsql[0]['uuid'])) {
        print '<a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;action=xml" title="'.$title_button.'">'.$langs->trans('Generar CFDI').'</a>';
    }
    if (empty($objsql[0]['uuid'])) {
    print '<a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;action=Timbrar" title="'.$title_button.'">'.$langs->trans('Timbrar').'</a>';
    } else if (!empty($objsql[0]['uuid']) and empty($objCancelcfdi[0]['UUID'])) {
        print '<a class="butAction' . ($conf->use_javascript_ajax ? ' reposition' : '') . '" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&amp;action=Cancelcfdi" title="' . $title_button . '">' . $langs->trans('Cancelar CFDI') . '</a>';
    }
}
function crtElmnt($xml, $nameSpace, $elmName, $parentNd )//edit1
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
 function createAttribtDNS($xml, $element, $dns, $attName,  $val)
 {
     $attrbt= $xml->createAttributeNS($dns, $attName);
     $attrbt->value=$val;
     $element->appendChild($attrbt);       
 }