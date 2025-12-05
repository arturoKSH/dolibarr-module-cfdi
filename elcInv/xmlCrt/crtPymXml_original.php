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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmargin.class.php';


include(DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/xmlCrt/stampCfdi.php'); //ehm

$objectt = new Facture($db);

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
	include('./pdf.php');
	$outputlangs = $langs;
	$pdfP= new pdf();
	$pdfP->__construct($db);
	$pdfP->generar($id,$outputlangs,$db,$ref);
}


if ($action=='pdfReg') {
	$carpeta = DOL_DATA_ROOT."/facture/".$reffobj[0]['ref'];
	$ruta=$carpeta.'/'.$reffobj[0]['ref'].".xml";
	
	$_SESSION['timbrar']='yes';
	include('../qr.php');
	 
	
	$db->begin();
	include('./pdf.php');
	$outputlangs = $langs;
	$pdfP= new pdf();
	$pdfP->__construct($db);
	$pdfP->generar($id,$outputlangs,$db,$ref);
	unset($_SESSION["timbrar"]);
}

$sql='select uuid from '.MAIN_DB_PREFIX.'paiement ';
$sql.='where rowid='.$id;

$ressql = $db->query($sql);
$objsql = $ressql->fetch_all(MYSQLI_ASSOC);

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
		$sql.= " where c.fk_soc  = ".$object->socid." ";
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
	include('../soap.php');
	 
	
	$db->begin();
	include('./pdf.php');
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
	$credentials = new Credentials('../crasa/00001000000410162134.cer.pem',
            '../crasa/CSD_CRASA_REPRESENTACIONES_CRE140120TI9_20180402_151519.key.pem',
            'SELLOSCRS2018');

	//cancelar
	$dataCancelCfdi = new Cancellation($RfcEmisor, [$result[0]['uuid']], new DateTimeImmutable());
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
            $societee = " SELECT 	c.nom,  c.siren,curdate() fecha,    DATE_FORMAT(DATE_SUB(NOW( ),INTERVAL 122 MINUTE), '%H:%i:%S' ) as hora,"
                    . " d.propouse,  zip, d.fiscalreg ";
            $societee.= " from 	".MAIN_DB_PREFIX."facture b inner join ".MAIN_DB_PREFIX."societe c ";
            $societee.= " on 		b.fk_soc = c.rowid inner join ".MAIN_DB_PREFIX."societe_extrafields d ";
            $societee.= " on    	c.rowid = d.fk_object inner join ".MAIN_DB_PREFIX."paiement_facture e ";
            $societee.= " on    	b.rowid = e.fk_facture ";
            $societee.= " where 	e.fk_paiement = ".$id;

            $ressociete = $db->query($societee);
            $objsociete = $ressociete ->fetch_all(MYSQLI_ASSOC);

            $dtl = "		select 	a.rowid NumOper, format(a.amount,2) monto, 'MXN' MonedaP, c.code_sat formpagop,a.ref pago, ";
            $dtl.= "	 	cast((concat(SUBSTRING(a.datep,1,10),  'T'  , SUBSTRING(a.datep,12,9))) as char) fechapago, d.ref  folio, ";
            $dtl.= "		f.serie,format(((d.total_ttc - k.saldant +b.amount)-ifnull(l.nc,0)),2) impsaldoanterior, ";
            $dtl.= "		format((((d.total_ttc - k.saldant + b.amount)-ifnull(l.nc,0)) - b.amount ),2) saldoinsoluto, ";
            $dtl.= "		        format(b.amount,2) impPagado, b.num_parcial partialnum , CASE e.mofpaymt WHEN 1 THEN 'PUE' WHEN 2 THEN 'PPD' END MetodoPago, ";
            $dtl.= "		        d.multicurrency_code MonedaDR, e.uuid IdDocumento, e.propouse ,a.ref folioP, '01' as export, '01' as ObjetoImp";
            $dtl.= "		from 	".MAIN_DB_PREFIX."paiement a inner join ".MAIN_DB_PREFIX."paiement_facture b ";
            $dtl.= "		on 		a.rowid = b.fk_paiement inner join ".MAIN_DB_PREFIX."c_paiement c ";
            $dtl.= "		on		a.fk_paiement=c.id inner join ".MAIN_DB_PREFIX."facture d ";
            $dtl.= "		on		b.fk_facture=d.rowid inner join ".MAIN_DB_PREFIX."facture_extrafields e ";
            $dtl.= "		on 		b.fk_facture=e.fk_object inner join ".MAIN_DB_PREFIX."serie f ";
            $dtl.= "		on 		e.serie=f.rowid ";
            $dtl.= "		inner join (select 	b.fk_facture, sum(b.amount) saldant ";
            $dtl.= "					from 	".MAIN_DB_PREFIX."paiement a left join ".MAIN_DB_PREFIX."paiement_facture b ";
            $dtl.= "					on 		a.rowid = b.fk_paiement "; 
            $dtl.= "					group by b.fk_facture) k ";
            $dtl.= "		on 		d.rowid = k.fk_facture ";
            $dtl.= "		left join (select fk_facture,sum(amount_ttc) nc ";
            $dtl.= "		from 	".MAIN_DB_PREFIX."societe_remise_except  ";
            $dtl.= "		group by fk_facture ) l ";
            $dtl.= "		on d.rowid=l.fk_facture ";
            $dtl.= "		where a.rowid= ".$id;
            $resdtl = $db->query($dtl);
            $objdtl = $resdtl->fetch_all(MYSQLI_ASSOC);

//            $datosempresa = " SELECT name,value ";
//            $datosempresa.= " FROM ".MAIN_DB_PREFIX."const where name like 'MAIN_INFO%'";
//            $resdatosempresa = $db->query($datosempresa);
//            $objdatosempresa = $resdatosempresa  ->fetch_all(MYSQLI_ASSOC);

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
            
            $fact =  crtElmnt($xml, "http://www.sat.gob.mx/cfd/4", "cfdi:Comprobante",$xml);
            createAttribt($xml, $fact, "Total", "0");
            createAttribt($xml, $fact, "Moneda", "XXX");
            createAttribt($xml, $fact, "SubTotal", "0");
            createAttribt($xml, $fact, "TipoDeComprobante", "P");
            createAttribt($xml, $fact, "Fecha", $objsociete[0]['fecha']."T".$objsociete[0]['hora']);
            createAttribt($xml, $fact, "Folio",substr($objdtl[0]['folioP'], -4));
            createAttribt($xml, $fact, "Serie","P");
            /*$fact = $xml->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:Comprobante");
            $fact =$xml->appendChild($fact);*/

            /*$att1= $xml->createAttribute('Total');
            $att1->value=0;*/


//            $moneda=$xml->createAttribute('Moneda');
//            $moneda->value="XXX";


//            $SubTotal=$xml->createAttribute('SubTotal');
//            $SubTotal->value=0;



//            $TPOPago=$xml->createAttribute('TipoDeComprobante');
//            $TPOPago->value='P';

//            $Fecha=$xml->createAttribute('Fecha');
//            $Fecha->value=$objsociete[0]['fecha']."T".$objsociete[0]['hora'];

//            $foliofact=substr($objdtl[0]['folioP'], -4);
//            $Folio=$xml->createAttribute('Folio');
//            $Folio->value=$foliofact;

//            $Serie=$xml->createAttribute('Serie');
//            $Serie->value="P";

            $LugarExpedicion=$xml->createAttribute('LugarExpedicion');
            $LugarExpedicion->value=$CodigoPostal;

            $Sello=$xml->createAttribute('Sello');
            $Sello->value="Sello";

            $Certificado=$xml->createAttribute('Certificado');
            $Certificado->value=$certificatevalue;

            $NoCertificado=$xml->createAttribute('NoCertificado');
            $NoCertificado->value=$certificate;

            $Version=$xml->createAttribute('Version');
            $Version->value="4.0";

            $Export=$xml->createAttribute('Exportacion');
            $Export->value="01";

            $xsi=$xml->createAttributeNS('http://www.w3.org/2001/XMLSchema-instance','xsi:schemaLocation');
            $xsi->value="http://www.sat.gob.mx/Pagos20 http://www.sat.gob.mx/sitio_internet/cfd/Pagos/Pagos20.xsd http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd ";
            
            
            $fact->appendChild($xsi);
            $fact->appendChild($Version);
            $fact->appendChild($Export);            
//            $fact->appendChild($Serie);
//            $fact->appendChild($Folio);
//            $fact->appendChild($Fecha);
            $fact->appendChild($Sello);
            $fact->appendChild($NoCertificado);
            $fact->appendChild($Certificado);
            //$fact->appendChild($SubTotal);
           // $fact->appendChild($moneda);
            //$fact->appendChild($att1);
            //$fact->appendChild($TPOPago);
            $fact->appendChild($LugarExpedicion);

            //docs relacionados
            $relextra = "SELECT * ";
            $relextra.= " FROM ".MAIN_DB_PREFIX."kshpay_rel ";
            $relextra.= " where fk_pay_parent= '".$id."'";
            $resrelextra = $db->query($relextra);
            $objsrelextra = $resrelextra ->fetch_all(MYSQLI_ASSOC);

            if (!empty($objsrelextra) ) {
                    foreach ($objsrelextra as $key => $value) {
                            $cfdiRels = $xml->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionados");
                    $cfdiRels=$fact->appendChild($cfdiRels);

                            $cfdiRel = $xml->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:CfdiRelacionado");
                    $cfdiRel=$cfdiRels->appendChild($cfdiRel);

                    $TipoRelacion=$xml->createAttribute('TipoRelacion');
                            $TipoRelacion->value=$value['reltype'];

                            $UIDDR=$xml->createAttribute('UUID');
                            $UIDDR->value=$value['uuid'];

                            $cfdiRel->appendChild($UIDDR); 
                            $cfdiRels->appendChild($TipoRelacion);

                    }
            }


        //emisor------

//        $emisor = $xml->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:Emisor");
//        $emisor=$fact->appendChild($emisor);
        $emisor =  crtElmnt($xml, "http://www.sat.gob.mx/cfd/4","cfdi:Emisor",$fact);
        
        $regimen=$xml->createAttribute('RegimenFiscal');
        $regimen->value=$RegimenFiscal;

        $Nombre=$xml->createAttribute('Nombre');
        $Nombre->value=$NameCompany;

        $Rfc=$xml->createAttribute('Rfc');
        $Rfc->value=$Rfcc;

        $emisor->appendChild($Rfc); 
        $emisor->appendChild($Nombre);
        $emisor->appendChild($regimen);

        //Fin emisor------


         //Receptor------

//        $Receptor = $xml->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:Receptor");
//        $Receptor=$fact->appendChild($Receptor);
        $Receptor =  crtElmnt($xml, "http://www.sat.gob.mx/cfd/4","cfdi:Receptor",$fact);
        $Rfc=$xml->createAttribute('Rfc');
        $Rfc->value="EKU9003173C9";
        //$Rfc->value=$objsociete[0]['siren'];        
        $Receptor->appendChild($Rfc);         
        
        $Nombrer=$xml->createAttribute('Nombre');
        $Nombrer->value="ESCUELA KEMPER URGATE SA DE CV";
        //$Nombrer->value=$objsociete[0]['nom'];
        $Receptor->appendChild($Nombrer);
        
        $zipCd=$xml->createAttribute('DomicilioFiscalReceptor');
        $zipCd->value="21000";
        //$zipCd->value=$objsociete[0]['zip'];
        $Receptor->appendChild($zipCd);

        $regimen=$xml->createAttribute('RegimenFiscalReceptor');
        $regimen->value=$objsociete[0]['fiscalreg'];
        $Receptor->appendChild($regimen);
        
        
        $usocfd="P01";
        $regimen=$xml->createAttribute('UsoCFDI');
        $regimen->value=$usocfd;
        $Receptor->appendChild($regimen);
        
        //fin receptor

        //conceptos-------
        $factbody = $xml->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:Conceptos");
        $factbody = $fact->appendChild($factbody);

            $conceptos= array('ClaveProdServ', 'Cantidad','ClaveUnidad', 'Descripcion', 'ValorUnitario', 'Importe','ObjetoImp' );
            $conceptosAtr=array('84111506', '1','ACT', 'Pago', '0', '0','01' );
            
            $cont=0;

            $concep = $xml->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:Concepto");

            foreach ($conceptos as $key => $value) {
                    $atrubuto= $xml-> createAttribute($value);
                    $atrubuto->value=$conceptosAtr[$cont];
                    $concep->appendChild($atrubuto);
                    $cont++;
            }
            $concep = $factbody->appendChild($concep);
            $Complemento = $xml->createElementNS('http://www.sat.gob.mx/cfd/4',"cfdi:Complemento");
            $Complemento = $fact->appendChild($Complemento);

            $pagos = $xml->createElementNS('http://www.sat.gob.mx/Pagos20',"pago20:Pagos");
            $pagos = $Complemento->appendChild($pagos);

            $at= $xml-> createAttribute("Version");
            $at->value="2.0";
            $pagos->appendChild($at);
            
            //Nodo Totales

            $Arraytotales=array('TotalRetencionesIVA'=>'TotalRetencionesIVA',
                                'TotalRetencionesISR'=>'TotalRetencionesISR',
                                'TotalRetencionesIEPS'=>'TotalRetencionesIEPS',
                                'TotalTrasladosBaseIVA16'=>'TotalTrasladosBaseIVA16',
                                'TotalTrasladosImpuestoIVA16'=>'TotalTrasladosImpuestoIVA16',
                                'TotalTrasladosBaseIVA8'=>'TotalTrasladosBaseIVA8',
                                'TotalTrasladosImpuestoIVA8'=>'TotalTrasladosImpuestoIVA8',
                                'TotalTrasladosBaseIVA0'=>'TotalTrasladosBaseIVA0',
                                'TotalTrasladosImpuestoIVA0'=>'TotalTrasladosImpuestoIVA0',
                                'TotalTrasladosBaseIVAExento'=>'TotalTrasladosBaseIVAExento',
                                'MontoTotalPagos'=>'MontoTotalPagos'
            );
            $i=0;
            $totales = $xml->createElementNS('http://www.sat.gob.mx/Pagos20',"pago20:Totales");
            $totales = $pagos->appendChild($totales);
            foreach ($Arraytotales as $key => $value) {
                    $atrubutop= $xml-> createAttribute($value);
                    $atrubutop->value=str_replace(",", "",$objdtl[$i][$key]);
                    $totales->appendChild($atrubutop);

            }
            $totales=$pagos->appendChild($totales);
            //Nodo Totales
            
            
            $Arraypagos=array('fechapago'=>'FechaPago','formpagop'=>'FormaDePagoP','MonedaP'=>'MonedaP','monto'=>'Monto','NumOper'=>'NumOperacion');
            $i=0;
            
            $pagoo = $xml->createElementNS('http://www.sat.gob.mx/Pagos20',"pago20:Pago");
            $pagoo = $pagos->appendChild($pagoo);
            foreach ($Arraypagos as $key => $value) {
                    $atrubutop= $xml->createAttribute($value);
                    $atrubutop->value=str_replace(",", "",$objdtl[$i][$key]);
                    $pagoo->appendChild($atrubutop);

            }
            $pagoo=$pagos->appendChild($pagoo);

            //$arrayDoct=array('IdDocumento' =>'IdDocumento','MonedaDR'=>'MonedaDR','MetodoPago'=> 'MetodoDePagoDR', 'partialnum'=> 'NumParcialidad','impsaldoanterior' => 'ImpSaldoAnt','impPagado'=> 'ImpPagado','saldoinsoluto'=> 'ImpSaldoInsoluto','serie'=> 'Serie', 'folio'=> 'Folio');            
            $arrayDoct=array('IdDocumento' =>'IdDocumento',
                'serie'=> 'Serie',
                'folio'=> 'Folio',
                'MonedaDR'=>'MonedaDR', 
                'partialnum'=> 'NumParcialidad',
                'impsaldoanterior' => 'ImpSaldoAnt',
                'impPagado'=> 'ImpPagado',
                'saldoinsoluto'=> 'ImpSaldoInsoluto',
                'objetoimpdr','ObjetoImpDR');
            foreach ($objdtl as $key => $value) {	
                    $Doctorel = $xml->createElementNS('http://www.sat.gob.mx/Pagos20',"pago20:DoctoRelacionado");
                $Doctorel = $pagoo->appendChild($Doctorel);
                    foreach ($arrayDoct as $key => $value){
                            $atributo = $xml->createAttribute($value);
                            if ($key!='IdDocumento') {
                                    $atributo->value = str_replace($search, $replace, $objdtl[$i][$key]);
                            }else{
                                    $atributo->value = str_replace(",", "", $objdtl[$i][$key]);
                            }

                            $Doctorel->appendChild($atributo);
                    }
                    $Doctorel = $pagoo->appendChild($Doctorel);
                    $i++;
            }



        createPem($localPht, $certName, $certPsw);
        $orgStr = getOrigStr($localPht, $xml, $xlst);
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

            $db->begin();
            include('./pdf.php');
            $outputlangs = $langs;
            $pdfP= new pdf();
            $pdfP->__construct($db);
            $pdfP->generar($id,$outputlangs,$db,$ref);
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
$formmargin = new FormMargin($db);

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
function crtElmnt($xml, $nameSpace, $elmName, $parentNd )
 {
     $element = $xml->createElementNS($nameSpace,$elmName);
     //if($parentNd == null)
   //     $xml->appendChild($element);
   // else 
        $parentNd->appendChild($element);
    
     return $element;
 }

 function createAttribt($xml, $element,$attName, $val)
 {
     $attrbt= $xml->createAttribute($attName);
     $attrbt->value=$val;
     $element->appendChild($attrbt);       
 }