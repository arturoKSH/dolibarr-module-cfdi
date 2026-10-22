<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
use PhpCfdi\XmlCancelacion\Capsules\Cancellation;
use PhpCfdi\XmlCancelacion\Capsules\ObtainRelated;
use PhpCfdi\XmlCancelacion\Capsules\CancellationAnswer;
use PhpCfdi\XmlCancelacion\Capsules\CapsuleInterface;
use PhpCfdi\XmlCancelacion\Signers\DOMSigner;
use PhpCfdi\XmlCancelacion\Credentials;

    
if ($action=="Timbrar") {        
        $formconfirm = "";
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?facid=' . $object->id, "Timbrar", "¿Desea Timbrar esta factura?", 'confirm_timbre', '', "yes", 2);
        //$_SESSION["timbrar"]='timbrar';    
}
if($action == 'confirm_CancelSat' && $confirm == 'yes'){
    $valid = TRUE;
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
            $valid = FALSE;
        }
    }
    if($valid == TRUE)
    {
                
        include(DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/CancelSat/composer/vendor/autoload.php');
        include(DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/CancelSat/soapcancel.php');
        $datosempresa = " SELECT name,value ";
        $datosempresa.= " FROM ".MAIN_DB_PREFIX."const where name like 'MAIN_INFO%'";
        $resdatosempresa = $db->query($datosempresa);
        $objdatosempresa = $resdatosempresa  ->fetch_all(MYSQLI_ASSOC);
        $datoss=array('MAIN_INFO_SOCIETE_NOM','MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_ZIP','MAIN_INFO_SOCIETE_OBJECT','MAIN_INFO_SOCIETE_CERTIFICATE','MAIN_INFO_SOCIETE_CERTIFICATE_VALUE');
        $cont=0;
        $NameCompany="";
        $RfcEmisor="";
        $CodigoPostal="";
        foreach ($objdatosempresa as $key => $value) {
            if ($value['name']==$datoss[1]) {
                $RfcEmisor=$value['value'];
            }
            $cont++;
        }
    
        $sql="select siren from ".MAIN_DB_PREFIX."societe where rowid=".$object->socid;
        $client=$db->query($sql);
        $objCliente=$client->fetch_all(MYSQLI_ASSOC);
        $_SESSION['CancelStatus']="";
        $uuid= $object->array_options['options_uuid'];
                        
        $uuidRpl = $uuid;
        //$credentials = new Credentials(DOL_DOCUMENT_ROOT.'/elcInv/abc/abc.cer.pem',DOL_DOCUMENT_ROOT.'/elcInv/abc/abc.key.pem','Blgstcscv89');
        $certName = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
        $certPsw  = dolDecrypt(getDolGlobalString('MAIN_INFO_CFDI_CERT_PSW'));

        require_once DOL_DOCUMENT_ROOT.'/custom/cfdi/lib/cfdi.lib.php';

        $localPht = cfdiCertDir($certName);

        $cerPem = cfdiCertFile($certName, '.cer.pem');
        $keyPem = cfdiCertFile($certName, '.key.pem');

        if (empty($certName) || !file_exists($cerPem) || !file_exists($keyPem)) {
            setEventMessages('Certificados SAT no encontrados. Suba el .cer y .key en CFDI > Configuración.', null, 'errors');
            $action = '';
            return;
        }

        $credentials = new Credentials(
                $cerPem,  // Ruta al CER PEM
                $keyPem,  // Ruta al KEY PEM
                $certPsw  // Contraseña correcta
            );
        $dataCancelCfdi = new Cancellation($RfcEmisor, [$uuid], new DateTimeImmutable(), $reason, $uuidRpl);
        $rutaCancelCfdi=DOL_DATA_ROOT."/facture/".$object->ref."/".$object->ref."-SolicitudCancel.xml";
        $xmlok = (new DOMSigner())->signCapsule($dataCancelCfdi, $credentials,$rutaCancelCfdi);
        $ruta = DOL_DATA_ROOT."/facture/".$object->ref."/".$object->ref."-SolicitudCancel.xml";
        $xmlCancelCFDI =CancelaCfdi($db,$ruta);
            
        if($_SESSION['CancelStatus']=="yes" and !empty($_SESSION['CancelStatus'])){
          
            $updstatut = " 	update 	llx_facture a
            set 	fk_statut = 3, paye = 0
            where	a.rowid = '".$id."'";//AOZ EHM paye =0
                            
            $execupdstt = $db->query($updstatut);

            $result = $object->fetch($id);
            
            //$result = $this->StockCfdi($user,$langs,$object->array_options['options_warehouse']);
            $close_code = "abandon";
            $close_note ="CFDI Cancelado en SAT"; 
            if ($close_code) {
                $result = $object->set_canceled($user, $close_code, $close_note);
                if ($result<0) setEventMessages($object->error, $object->errors, 'errors');
            } else {
                setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Reason")), null, 'errors');
            }
            unset($_SESSION['CancelStatus']);
        }
      
        include(DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/CancelSat/pdfCancel.php'); //ehm

        $outputlangs = $langs;
        $CancelCfdiPDF= new pdf();
        $CancelCfdiPDF->__construct($db);
        $CancelCfdiPDF->generar($id,$outputlangs,$db,$object->ref, $xmlCancelCFDI);
    
        if(empty($object->thirdparty)){
            $object->fetch_thirdparty();
        }        
        
    }

}
if (!empty($object->array_options['options_uuid']) ) {
    
    if($conf->global->MAIN_INFO_CFDI_DEL_CFDI == 0)
    {
        
       // $usercancreate = FALSE;
        $usercandelete = FALSE;
        $usercanreopen = FALSE;
        $usercanunvalidate = FALSE;
        $permission = FALSE;
        $permtoedit = FALSE;
    }
    
}


function addBtn($object)
{
    global $db, $langs, $conf;
    
    if (empty($object->array_options['options_uuid'])) {
        if (($object->statut == Facture::STATUS_VALIDATED 
                || $object->statut == Facture::STATUS_CLOSED) 
                || ! empty($conf->global->FACTURE_SENDBYEMAIL_FOR_ALL_STATUS)
           ) 
        {
            print '<div class="inline-block divButAction"><a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="' . $_SERVER["PHP_SELF"] . '?facid=' . $object->id . '&amp;action=confirm_valid2&amp;token=' . newToken() . '">' . $langs->trans('Generar CFDI ') . '</a></div>';
            print '<div class="inline-block divButAction"><a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="' . $_SERVER["PHP_SELF"] . '?facid=' . $object->id . '&amp;action=Timbrar&amp;token=' . newToken() . '">' . $langs->trans('Timbrar') . '</a></div>';
                
        }
    }
    function StockCfdi($user,$langs,$idwarehouse){

        		$this->db->begin();
        		dol_syslog(get_class($this)."::returnStockCfdi", LOG_DEBUG);
        		// If we decrement stock on invoice validation, we increment
        		// if ($this->type != self::TYPE_DEPOSIT && $result >= 0 && ! empty($conf->stock->enabled) && ! empty($conf->global->STOCK_CALCULATE_ON_BILL) && $idwarehouse!=-1)
        		// {
        			require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
        			$langs->load("agenda");			
        			$num=count($this->lines);
        			for ($i = 0; $i < $num; $i++)
        			{
        				if ($this->lines[$i]->fk_product > 0)
        				{
        					
        					$mouvP = new MouvementStock($this->db);
        					$mouvP->origin = &$this;
        					// We decrease stock for product
        					if ($this->type == Facture::TYPE_CREDIT_NOTE) $result=$mouvP->livraison($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, $this->lines[$i]->subprice,"Factura ".$this->ref." Cancelada en SAT");
        					else $result=$mouvP->reception($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, 0, "Factura ".$this->ref." Cancelada en SAT" );	// we use 0 for price, to not change the weighted average value					
        				}
        			}
        			$this->db->commit();
        			
        		// }
            return 0;
            }
     

}
