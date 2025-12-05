<?php 
	require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
//	require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

	 class pdf extends ModelePDFFactures {
		 /**
     * @var DoliDb Database handler
     */
    public $db;

	/**
     * @var string model name
     */
    public $name;

	/**
     * @var string model description (short text)
     */
    public $description;

    /**
     * @var int 	Save the name of generated file as the main doc when generating a doc with this template
     */
    public $update_main_doc_field;

	/**
     * @var string document type
     */
    public $type;

	/**
     * @var array Minimum version of PHP required by module.
     * e.g.: PHP ≥ 5.5 = array(5, 5)
     */
	public $phpmin = array(5, 5);

	/**
     * Dolibarr version of the loaded document
     * @var string
     */
	public $version = 'dolibarr';

	/**
     * @var int page_largeur
     */
    public $page_largeur;

	/**
     * @var int page_hauteur
     */
    public $page_hauteur;

	/**
     * @var array format
     */
    public $format;

	/**
     * @var int marge_gauche
     */
	public $marge_gauche;

	/**
     * @var int marge_droite
     */
	public $marge_droite;

	/**
     * @var int marge_haute
     */
	public $marge_haute;

	/**
     * @var int marge_basse
     */
	public $marge_basse;

	/**
	 * Issuer
	 * @var Societe object that emits
	 */
	public $emetteur;

	/**
	 * @var bool Situation invoice type
	 */
	public $situationinvoice;

	/**
	 * @var float X position for the situation progress column
	 */
	public $posxprogress;



		/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct()
	{
		global $conf, $langs, $mysoc;

		// Translations
		$langs->loadLangs(array("main", "bills"));

		$this->db = $db;
		$this->name = "crabe";
		$this->description = $langs->trans('PDFCrabeDescription');
		$this->update_main_doc_field = 1;		// Save the name of generated file as the main doc when generating a doc with this template

		// Dimensiont page
		$this->type = 'pdf';
		$formatarray=pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur,$this->page_hauteur);
		$this->marge_gauche=isset($conf->global->MAIN_PDF_MARGIN_LEFT)?$conf->global->MAIN_PDF_MARGIN_LEFT:10;
		$this->marge_droite=isset($conf->global->MAIN_PDF_MARGIN_RIGHT)?$conf->global->MAIN_PDF_MARGIN_RIGHT:10;
		$this->marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;
		$this->marge_basse =isset($conf->global->MAIN_PDF_MARGIN_BOTTOM)?$conf->global->MAIN_PDF_MARGIN_BOTTOM:10;

		$this->option_logo = 1;                    // Affiche logo
		$this->option_tva = 1;                     // Gere option tva FACTURE_TVAOPTION
		$this->option_modereg = 1;                 // Affiche mode reglement
		$this->option_condreg = 1;                 // Affiche conditions reglement
		$this->option_codeproduitservice = 1;      // Affiche code produit-service
		$this->option_multilang = 1;               // Dispo en plusieurs langues
		$this->option_escompte = 1;                // Affiche si il y a eu escompte
		$this->option_credit_note = 1;             // Support credit notes
		$this->option_freetext = 1;				   // Support add of a personalised text
		$this->option_draft_watermark = 1;		   // Support add of a watermark on drafts

		$this->franchise=!$mysoc->tva_assuj;

		// Get source company
		$this->emetteur=$mysoc;
		if (empty($this->emetteur->country_code)) $this->emetteur->country_code=substr($langs->defaultlang, -2);    // By default, if was not defined

		// Define position of columns
		$this->posxdesc=$this->marge_gauche+1;
		if (!empty($conf->global->PRODUCT_USE_UNITS))
		{
			$this->posxtva=106;
			$this->posxup=100;
			$this->posxqty=135;
			$this->posxunit=151;
		}
		else
		{	
			$this->posxpeso=115;
			$this->posxtva=120;
			$this->posxup=135;
			$this->posxqty=150;
			$this->posxunit=162;
		}
		$this->posxprogress=151; // Only displayed for situation invoices
		$this->posxdiscount=162;
		$this->posxprogress=174;
		$this->postotalht=174;
		if (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) || ! empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN)) $this->posxtva=$this->posxup;
		$this->posxpicture=$this->posxtva - (empty($conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH)?20:$conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH);	// width of images
		if ($this->page_largeur < 210) // To work with US executive format
		{
		    $this->posxpicture-=20;
		    $this->posxpeso-=20;
		    $this->posxtva-=20;
		    $this->posxup-=20;
		    $this->posxqty-=20;
		    $this->posxunit-=20;
		    $this->posxdiscount-=20;
		    $this->posxprogress-=20;
		    $this->postotalht-=20;
		}

		$this->tva=array();
		$this->tipotva=array();
		$this->localtax1=array();
		$this->localtax2=array();
		$this->atleastoneratenotnull=0;
		$this->atleastonediscount=0;
		$this->situationinvoice=false;
	}
	public  function generar($id,$outputlangs,$db,$ref)
	{
		$factuas = " SELECT e.fk_paiement, a.rowid,format(e.amount,2) amount,a.multicurrency_code,cast(datep as char) fechapago,g.code_sat,a.ref,c.uuid, f.ref pago, ";
		$factuas.= " CASE c.mofpaymt WHEN 1 THEN 'PUE' WHEN 2 THEN 'PPD' END MetodoPago, format(f.amount,2) ttc, ";
		$factuas.= " format((a.total_ttc - k.saldant +e.amount)-ifnull(l.nc,0),2) impsaldoanterior, ";
		$factuas.= " format((((a.total_ttc - k.saldant + e.amount)) - e.amount )-ifnull(l.nc,0),2) saldoinsoluto, ";
		$factuas.= " format(e.amount,2) impPagado,e.num_parcial partialnum ,count(a.rowid) countt ";
		$factuas.="  FROM llx_facture a inner join llx_facture_extrafields c ";
		$factuas.="  on a.rowid=c.fk_object inner join llx_serie d ";
		$factuas.="	 on c.serie=d.rowid left join llx_paiement_facture e ";
		$factuas.="	 on a.rowid=e.fk_facture left join llx_paiement f ";
		$factuas.="  on e.fk_paiement=f.rowid left join llx_c_paiement g ";
		$factuas.="  on f.fk_paiement=g.id inner join (select 	b.fk_facture, sum(b.amount) saldant ";
		$factuas.=" 									from llx_paiement a inner join llx_paiement_facture b  ";
		$factuas.=" 									on 		a.rowid = b.fk_paiement  ";
		$factuas.=" 									group by b.fk_facture) k  ";
		$factuas.=" on 		a.rowid = k.fk_facture  ";
		$factuas.="		left join (select fk_facture,sum(amount_ttc) nc ";
		$factuas.="		from 	".MAIN_DB_PREFIX."societe_remise_except  ";
		$factuas.="		group by fk_facture ) l ";
		$factuas.="		on 		a.rowid=l.fk_facture ";
		$factuas.="  where e.fk_paiement=".$id." group by e.fk_facture ";
		$resfactuas = $db->query($factuas);
		$objfactuas = $resfactuas->fetch_all(MYSQLI_ASSOC);
		
		$queryfacsRel= " SELECT b.ref,b.rowid,b.datep,a.rowid rowidksh, b.amount,b.uuid ";
		$queryfacsRel.=" from  ".MAIN_DB_PREFIX."kshpay_rel a left join  ".MAIN_DB_PREFIX."paiement b ";
		$queryfacsRel.=" on a.uuid=b.uuid ";
		$queryfacsRel.=" where fk_pay_parent='".$id."'";
		$resfacsrel=$db->query($queryfacsRel);
		$objectfacsrel=$resfacsrel->fetch_all(MYSQLI_ASSOC);


		if (! empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output='ISO-8859-1';
		$outputlangs->loadLangs(array("main", "bills", "products", "dict", "companies"));

		if(!function_exists("getTotPymInf"))
                                include(DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/dbExc/excFetch.php'); //ehm   


		//mkdir(DOL_DATA_ROOT."/facture/".$ref, 0700);
		$carpeta = DOL_DATA_ROOT."/facture/".$objfactuas[0]['pago'];
		if (!file_exists($carpeta)) {
   		 	mkdir($carpeta, 0777, true);
		}
		$dir =  DOL_DATA_ROOT."/facture/".$objfactuas[0]['pago'];
		$file = $dir.'/'.$objfactuas[0]['pago'].".pdf";
		$pdf=pdf_getInstance($this->format);
        $default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
        $pdf->SetAutoPageBreak(1, 0);
         if (class_exists('TCPDF'))
        {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }
        $pdf->SetFont(pdf_getPDFFont($outputlangs));
		$pdf->Open();
		$pagenb=0;
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities("PdfInvoiceTitle"));
		$pdf->SetCreator("Dolibarr ".DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset("peuba"));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("PdfInvoiceTitle")." ".$outputlangs->convToOutputCharset($object->thirdparty->name));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // 

		$pdf->AddPage();
		$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs,$db,$id,$objfactuas[0]['countt']);//nueva pagina
		$tab_top = 55+$top_shift;
		$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)?52+$top_shift:10);

		$iniY = $tab_top + 7;
		$curY = $tab_top + 7;
		$nexY = $tab_top + 7;

		$Mpaymentsat=array('02'=>'Cheque','03'=>'Transferencia bancaria','04'=>'Tarjeta de crédito','01'=>'Efectivo','99'=>'Por definir','17'=>'Compensación');
		$mpago="";
		foreach ($Mpaymentsat as $key => $value) {
				if ($key==$objfactuas[0]['code_sat']) {
					$mpago='('.$key.') '.$value;
				}
			}

		$pdf->Line(10, 55, 210-10, 55);



		$pdf->SetFont('', 'B', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc-7, 57);
		$pdf->MultiCell(30, 4, 'Forma de Pago: ', 0, 'R'); 

		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc+25, 57);
		$pdf->MultiCell(120, 4,$mpago, 0, 'L');

		$pdf->SetFont('', 'B', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc-1, 61);
		$pdf->MultiCell(30, 4, 'Documento: ', 0, 'L'); 

		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc+25, 61);
		$pdf->MultiCell(120, 4,$objfactuas[0]['pago'], 0, 'L'); 

		$pdf->SetFont('', 'B', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc-1, 65);
		$pdf->MultiCell(30, 4, 'Fecha aplicación: ', 0, 'L'); 

		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc+25, 65);
		$pdf->MultiCell(120, 4,substr($objfactuas[0]['fechapago'],0,10)."T".substr($objfactuas[0]['fechapago'], 11), 0, 'L'); 


		$tt=$objfactuas[0]['ttc'];

		$pdf->SetFont('', 'B', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc+80, 57);
		$pdf->MultiCell(30, 4, 'Importe: ', 0, 'L'); 

		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc+103, 57);
		$pdf->MultiCell(120, 4,$tt, 0, 'L');

		$pdf->SetFont('', 'B', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc+80, 61);
		$pdf->MultiCell(30, 4, 'Moneda: ', 0, 'L'); 

		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc+103, 61);
		$pdf->MultiCell(120, 4,$objfactuas[0]['multicurrency_code'], 0, 'L');

		$pdf->SetFont('', 'B', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc+80, 65);
		$pdf->MultiCell(30, 4, 'Tipo de cambio: ', 0, 'L'); 

		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->SetXY($this->posxdesc+103, 65);
		$pdf->MultiCell(120, 4,'1.00', 0, 'L');
		
		//pagos relacionados
		if(!empty($objectfacsrel)){
			
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+130, 57);
			$pdf->MultiCell(30, 4, 'Pagos relacionados: ', 0, 'L'); 
			$pay=61;
			foreach($objectfacsrel as $key => $value){
				$pdf->SetFont('', '', $default_font_size - 3);
				$pdf->SetXY($this->posxdesc+130, $pay);
				$pdf->MultiCell(120, 4,$value['uuid'], 0, 'L');
				$pay+=3;
			}
			
			$pdf->setxy($this->posxdesc+130,69);
		}
		$y=$pdf->GetY()+5;
			
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc, $y);
			$pdf->MultiCell(30, 4, 'Folio', 0, 'L'); 

			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+15, $y);
			$pdf->MultiCell(30, 4, 'Fecha', 0, 'L'); 


			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+50, $y);
			$pdf->MultiCell(30, 4, 'UUID', 0, 'L'); 
			

			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+98, $y);
			$pdf->MultiCell(30, 4, 'Saldo anterior', 0, 'R'); 

			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+114, $y);
			$pdf->MultiCell(30, 4, 'Importe', 0, 'R'); 

			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+136, $y);
			$pdf->MultiCell(30, 4, 'Saldo Insoluto', 0, 'R'); 

			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+160, $y);
			$pdf->MultiCell(30, 4, 'No. Parcialidad', 0, 'R'); 

			$pdf->SetTextColor(255);
			$pdf->Line(10, 78.8, 210-10, 78.8);


		$curY = $nexY+15;
		$up_excl_tax = pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails);
			
		$pdf->SetDrawColor(128, 128, 128);





		//info pago 

		// Description of product line
		$pdf->SetFont('', '', $default_font_size - 3);   // Into loop to work with multipage
		$pdf->SetTextColor(0, 0, 0);

		// Define size of image if we need it
		$imglinesize=array();
		if (! empty($realpatharray[$i])) $imglinesize=pdf_getSizeForImage($realpatharray[$i]);

		$pdf->setTopMargin($tab_top_newpage-10);
		$pdf->setPageOrientation('', 1, $heightforfooter+$heightforfreetext+$heightforinfotot);	// The only function to edit the bottom margin of current page to set it.
		$pageposbefore=$pdf->getPage();

		$showpricebeforepagebreak=1;
		$posYAfterImage=0;
		$posYAfterDescription=0;
		$curX = $this->posxdesc+27;
		/*$hidedesc='holaaa';*/
		$pdf->startTransaction();
		pdf_writelinedesc($pdf, null, 0, $outputlangs,50, 3, $curX, $curY, $hideref, $hidedesc);
		$pageposafter=$pdf->getPage();


	foreach ($objfactuas as $key => $value) {


			$y=$pdf->GetY();
			
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc, $y);
			$pdf->MultiCell(30, 4, $value['ref'], 0, 'L'); 

			$pdf->SetFont('', '', $default_font_size - 3);
			$pdf->SetXY($this->posxdesc+15, $y);
			$pdf->MultiCell(100, 3, substr($value['fechapago'], 0,10).'T'.substr($value['fechapago'], 11), 0, 'L');


			$pdf->SetFont('', '', $default_font_size - 3);
			$pdf->SetXY($this->posxdesc+48, $y);
			$pdf->MultiCell(100, 3, $value['uuid'], 0, 'L');

			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+98, $y);
			$pdf->MultiCell(30, 4, $value['impsaldoanterior'], 0, 'R'); 

			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+114, $y);
			$pdf->MultiCell(30, 4, $value['impPagado'], 0, 'R'); 

			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+136, $y);
			$pdf->MultiCell(30, 4, $value['saldoinsoluto'], 0, 'R'); 

			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($this->posxdesc+150, $y);
			$pdf->MultiCell(30, 4,  $value['partialnum'], 0, 'R');

			$nexY = $pdf->GetY()+2;

		// Show square
			if ($nexY>230)
				{
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
					$pdf->AddPage();
					
					$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs,$db,$id,$objfactuas[0]['countt']);//nueva pagina
					$tab_top = 55+$top_shift;
					$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)?52+$top_shift:10);
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1, $object->multicurrency_code);

					if ($nexY>260) {
						$nexY=$nexY+0.8-$nexY+0.8+55;
					}
					$nexY = $pdf->GetY()+1;
				}
				
			
		

			$factTx = getTotTaxPymInv($value['fk_paiement'],$value['rowid']); //ehm
			
			foreach ($factTx as $keyTx => $valueTx) {
			
				$y=$pdf->GetY();

				$pdf->SetFont('', '', $default_font_size - 3);
				$pdf->SetXY($this->posxdesc, $y);
				$pdf->MultiCell(30, 4, '', 0, 'L'); 

				$pdf->SetFont('', '', $default_font_size - 3);
				$pdf->SetXY($this->posxdesc+22, $y);
				$pdf->MultiCell(100, 3,'Impuesto: '. $valueTx['Impuesto'], 0, 'L');


				$pdf->SetFont('', '', $default_font_size - 3);
				$pdf->SetXY($this->posxdesc+48, $y);
				$pdf->MultiCell(100, 3, 'Base: '.price($valueTx['Base']), 0, 'L');

				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($this->posxdesc+68, $y);
				$pdf->MultiCell(30, 4,'Tipo/Factor: '. $valueTx['TipoFactor'], 0, 'R'); 

				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($this->posxdesc+85, $y);
				$pdf->MultiCell(50, 4, 'Tasa/Cuota: '.price($valueTx['TasaOCuota']*100), 0, 'R'); 

				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($this->posxdesc+136, $y);
				$pdf->MultiCell(30, 4, 'Importe: '.price($valueTx['Importe']), 0, 'R'); 

	//                            $pdf->SetFont('', '', $default_font_size - 2);
	//                            $pdf->SetXY($this->posxdesc+150, $y);
	//                            $pdf->MultiCell(30, 4,  $valueTx['saldoinsoluto'], 0, 'R');

				$nexY = $pdf->GetY()+2;                                                      
				
			}
	    }
		// Show square
		if ($pagenb == 1)
		{
			$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0, $object->multicurrency_code);
			$bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
		}
		else
		{
			$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0, $object->multicurrency_code);
			$bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
			
		}

		$posy=$this->_tableau_tot($pdf, $object, $deja_regle, $bottomlasttab, $outputlangs,$objfactuas[0]['pago'],$objfactuas[0]['countt']);

		$this->_pagefoot($pdf, $object, $outputlangs);
		$pdf->Close();

		$pdf->Output($file, 'F');
		return 0;


	}


	private function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs,$ref,$count)
	{
        // phpcs:enable
		global $conf,$mysoc;

        $sign=1;
        if ($object->type == 2 && ! empty($conf->global->INVOICE_POSITIVE_CREDIT_NOTE)) $sign=-1;

        $default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy+13;
		$tab2_hl = 4;
		/*$pdf->SetFont('', 'B', $default_font_size - 2);*/

		// Tableau total
		$col1x = 120; $col2x = 170;
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$col2x-=20;
		}
		$largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

		$useborder=0;
		$index = 0;


	if (!empty($_SESSION['timbrar'])) {
			
				$dir =  DOL_DATA_ROOT."/facture/".$ref; 
				$file = $dir.'/'.$ref.".xml";
				$doc = new DOMDocument('1.0', 'UTF-8');
				$doc->load($file);

				$Nocertificado= $doc->getElementsByTagName('Comprobante')->item(0)->getAttribute('NoCertificado');;

				$comprobante = $doc->getElementsByTagName('Comprobante')->item(0);
				$count = 0;
				$RfcEmisor = $comprobante->getElementsByTagName('Complemento')->item($count);
				$complemento=$RfcEmisor->getElementsByTagName('TimbreFiscalDigital')->item(0);
				$sellosatr=$complemento->getAttribute('SelloSAT');
				$sellocfd=$complemento->getAttribute('SelloCFD');
				$UUID=$complemento->getAttribute('UUID');
				$FechaTimbrado=$complemento->getAttribute('FechaTimbrado');
				$RfcProvCertif=$complemento->getAttribute('RfcProvCertif'); 
				$NoCertificadoSAT=$complemento->getAttribute('NoCertificadoSAT');   	

				$CadenaOrginalSat="||1.1|".$UUID."|".$FechaTimbrado."|".$RfcProvCertif."|".$sellocfd."|".$NoCertificadoSAT."||";
				//
				$pdf->SetXY($tab3_posx+65, 227.5);
				$pdf->SetFont('','B',6);
				$pdf->MultiCell(100, 3, "Este documento es una representación impresa de un CFDI.", 0, 'L', 0);

				$pdf->SetXY($tab3_posx+32, 231);
				$pdf->SetFont('','B',6);
				$pdf->MultiCell(100, 3, "Folio fiscal:", 0, 'L', 0);
				$pdf->SetXY($tab3_posx+45, 231);
				$pdf->SetFont('','',6);
				$pdf->MultiCell(120, 3,$UUID, 0, 'L', 0);
				
				$pdf->SetXY($tab3_posx+32, 235);
				$pdf->SetFont('','B',6);
				$pdf->MultiCell(100, 3, "Fecha y hora de certificación:", 0, 'L', 0);
				$pdf->SetXY($tab3_posx+65, 235);
				$pdf->SetFont('','',6);
				$pdf->MultiCell(120, 3,$FechaTimbrado, 0, 'L', 0);

				$pdf->SetXY($tab3_posx+5, 256);
				$pdf->SetFont('','B',6);
				$pdf->MultiCell(100, 3, "Número de serie del Certificado de Sello Digital:", 0, 'L', 0);
				$pdf->SetXY($tab3_posx+5, 258);
				$pdf->SetFont('','',6);
				$pdf->MultiCell(120, 3,$Nocertificado, 0, 'L', 0);

				$pdf->SetXY($tab3_posx+65, 256);
				$pdf->SetFont('','B',6);
				$pdf->MultiCell(100, 3, "Número de serie del Certificado de Sello Digital del SAT:", 0, 'L', 0);
				$pdf->SetXY($tab3_posx+65, 258);
				$pdf->SetFont('','',6);
				$pdf->MultiCell(120, 3,$NoCertificadoSAT, 0, 'L', 0);

				$pdf->SetXY($tab3_posx+32, 238);
				$pdf->SetFont('','B',6);
				$pdf->MultiCell(100, 3, "Sello digital del CFDI:", 0, 'L', 0);
				$pdf->SetXY($tab3_posx+32, 240);
				$pdf->SetFont('','',6);
				$pdf->MultiCell(140, 3, $sellocfd, 0, 'L', 0);

				$pdf->SetXY($tab3_posx+5, 262);
				$pdf->SetFont('','B',6);
				$pdf->MultiCell(100, 3, "Sello digital del SAT:", 0, 'L', 0);
				$pdf->SetXY($tab3_posx+5, 264);
				$pdf->SetFont('','',6);
				$pdf->MultiCell(150, 3,$sellosatr, 0, 'L', 0);

				$pdf->SetXY($tab3_posx+5, 230);
				$pdf->MultiCell(500, 3, $pdf->Image(DOL_DATA_ROOT."/facture/".$object->ref."/".$object->ref.".png", $pdf->GetX(), $pdf->GetY(),25,25), 0, 'C', 0);

				$pdf->SetXY($tab3_posx+5, 274);
				$pdf->SetFont('','B',6);
				$oper = $outputlangs->transnoentitiesnoconv("Cadena original del complemento de certificación digital del SAT:");
				$pdf->MultiCell(100, 3, $oper, 0, 'L', 0);

				$pdf->SetXY($tab3_posx+5, 276);
				$pdf->SetFont('','',6);
				$pdf->MultiCell(150, 3,$CadenaOrginalSat, 0, 'L', 0);
			unset($_SESSION['timbrar']);
	}
			
		$search  = array('-', ',');
   		$replace = array('', '');
		


		$index++;
		
		

		return ($tab2_top + ($tab2_hl * $index));

	}
			/**
	 *  Show top header of page.
	 *
	 *  @param	PDF			$pdf     		Object PDF
	 *  @param  Object		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	void
	 */
	private function _pagehead(&$pdf, $object, $showaddress, $outputlangs,$db,$id,$count)
	{
		global $conf, $langs;

		// Load traductions files requiredby by page
		$outputlangs->loadLangs(array("main", "bills", "propal", "companies"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
/*if ($showaddress)
		{*/
		// Show Draft Watermark
		if($object->statut==Facture::STATUS_DRAFT && (! empty($conf->global->FACTURE_DRAFT_WATERMARK)) )
        {
		      pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $conf->global->FACTURE_DRAFT_WATERMARK);
        }

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 110;

		$posy=$this->marge_haute;
        $posx=$this->page_largeur-$this->marge_droite-$w;

		$pdf->SetXY($this->marge_gauche, $posy);

		
		$factuas = " SELECT a.rowid,a.fk_mode_reglement, c.mofpaymt, d.serie,f.ref ";
 		$factuas.= " FROM ".MAIN_DB_PREFIX."facture a inner join ".MAIN_DB_PREFIX."facturedet b";
 		$factuas.="  on a.rowid=b.fk_facture inner join ".MAIN_DB_PREFIX."facture_extrafields c ";
 		$factuas.="  on a.rowid=c.fk_object inner join ".MAIN_DB_PREFIX."serie d ";
 		$factuas.="  on c.serie=d.rowid left join ".MAIN_DB_PREFIX."paiement_facture e";
 		$factuas.="	 on a.rowid=e.fk_facture left join ".MAIN_DB_PREFIX."paiement f";
 		$factuas.="	 on e.fk_paiement=f.rowid "; 		
 		$factuas.="  where e.fk_paiement=".$id;

 		$resfactuas = $db->query($factuas);
 		$objfactuas = $resfactuas->fetch_all(MYSQLI_ASSOC);
 		
		$dir =  DOL_DATA_ROOT."/facture/".$objfactuas[0]['ref']; 
		$file = $dir.'/'.$objfactuas[0]['ref'].".xml";
			
			$doc = new DOMDocument('1.0', 'UTF-8');
			$doc->load($file);
			$comprobante = $doc->getElementsByTagName('Comprobante')->item(0);
			$count = 0;
			if (empty($comprobante->getElementsByTagName('Complemento')->item($count))) {
				unset($_SESSION["timbrar"]);
			}else{
				$RfcEmisor = $comprobante->getElementsByTagName('Complemento')->item($count);
				$complemento=$RfcEmisor->getElementsByTagName('TimbreFiscalDigital')->item(0);
				$FechaTimbrado=$complemento->getAttribute('FechaTimbrado');
			}
			
	
 		

		// //show Comprobante fiscal
		$pdf->SetFont('', 'B', $default_font_size -2);
		$pdf->SetXY(125, $posy+17);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell($w, 3, 'Comprobante fiscal digital'.$count, '', 'L');
		$pdf->SetFont('', '', $default_font_size -2);
		$pdf->SetXY(166, $posy+17);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell($w, 3, "(P) Pago", '', 'L');

		$pdf->SetFont('', 'B', $default_font_size +1);
		$pdf->SetXY(132, $posy+10);
		$pdf->SetTextColor(0, 0, 60);
		$title="COMPROBANTE DE PAGOS";
		$pdf->MultiCell($w, 3, $title, '', 'L');

	

		$posy+=22;
	
		
		// show serie 
		
		$posy+=3;
		$pdf->SetFont('', 'B', $default_font_size -3);
		$pdf->SetXY(125, $posy-4);
		$pdf->SetTextColor(0, 0, 60);
		
		$pdf->MultiCell($widthrecbox, 4, 'serie: ', '', 'L');

		$pdf->SetFont('', 'B', $default_font_size -3);
		$pdf->SetXY(125, $posy-1);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell($widthrecbox, 4, 'Folio: ', '', 'L');
		$pdf->SetFont('', '', $default_font_size -3);
		$pdf->SetXY(138, $posy-1);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell($widthrecbox, 4, $objfactuas[0]['ref'], '', 'L');

		$pdf->SetFont('', 'B', $default_font_size -3);
		$pdf->SetXY(125, $posy+2);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell($widthrecbox, 4, 'Fecha: ', '', 'L');
		$pdf->SetFont('', '', $default_font_size -3);
		$pdf->SetXY(138, $posy+2);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell($widthrecbox, 4, $FechaTimbrado, '', 'L');
		

		$posy+=1;

		$top_shift = 0;
		// Show list of linked objects
		$current_y = $pdf->getY();
		//$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
		if ($current_y < $pdf->getY())
		{
			$top_shift = $pdf->getY() - $current_y;
		}
/*}*/
		/*if ($showaddress)
		{*/
			// Sender properties
			$carac_emetteur = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

			// Show sender
			$posy=!empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 40 : 42;
			$posy+=$top_shift;
			$posx=$this->marge_gauche;
			if (! empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx=$this->page_largeur-$this->marge_droite-80;

			$hautcadre=!empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 38 : 40;
			$widthrecbox=!empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 92 : 82;


			// Show sender frame
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx, $posy-38);
			/*$pdf->MultiCell(66, 5, "", 0, 'L');*/
			$pdf->SetXY($posx, $posy-15);
			/*$pdf->SetFillColor(230, 230, 230);*/
			/*$pdf->MultiCell($widthrecbox, $hautcadre-5, "", 0, 'R');*/
			/*$pdf->Rect($posx, $posy-20, $widthrecbox, $hautcadre-12);*/
			$pdf->SetTextColor(0, 0, 60);

			// Show sender name customer
				$datosempresa = " SELECT name,value ";
 				$datosempresa.= " FROM ".MAIN_DB_PREFIX."const where name like 'MAIN_INFO%'";
 				$resdatosempresa = $db->query($datosempresa);
 				$objdatosempresa = $resdatosempresa  ->fetch_all(MYSQLI_ASSOC);
 				$datoss=array('MAIN_INFO_SIREN','MAIN_INFO_SOCIETE_OBJECT','MAIN_INFO_SOCIETE_ADDRESS','MAIN_INFO_SOCIETE_ZIP','MAIN_INFO_SOCIETE_TOWN');
 				$cont=0;
 				$NameCompany="";
 				$Rfcc="";
 				$CodigoPostal="";
 				foreach ($objdatosempresa as $key => $value) {
 		
 					if ($value['name']==$datoss[0]) {
 						$Rfcc=$value['value'];
 					}
 					if ($value['name']==$datoss[1]) {
 						$RegimenFiscal=$value['value'];
 					}
 					if ($value['name']==$datoss[2]) {
 						$addres=$value['value'];
 					}
 					if ($value['name']==$datoss[3]) {
 						$cp=$value['value'];
 					}
 					if ($value['name']==$datoss[4]) {
 						$town=$value['value'];
 					}
 					$cont++;
 				}
 			$info=$addres.'C.P. '.$cp.$town.', Morelos.';
 			$exped=$addres.'C.P. '.$cp.", Mexico";
			$pdf->SetXY($posx+55, $posy-35);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->MultiCell($widthrecbox-1, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
			

			//show rfc
			$pdf->SetXY($posx+2, $posy-30);
			$pdf->SetFont('', 'B', $default_font_size-3);
			$pdf->MultiCell($widthrecbox-1, 4, 'R.F.C. :', 0, 'L');

			$pdf->SetXY($posx+12, $posy-30);
			$pdf->SetFont('', '', $default_font_size-3);
			$pdf->MultiCell($widthrecbox-1, 4, $Rfcc, 0, 'L');
			$posy=$pdf->getY();

			//show Regimen fiscal
				

			$pdf->SetXY($posx-2, $posy);
			$pdf->SetFont('', 'B', $default_font_size - 3);
			$pdf->MultiCell($widthrecbox, 4,'Régimen Fiscal: ', 0, 'L');
			$pdf->SetXY($posx+25, $posy);
			$pdf->SetFont('', '', $default_font_size - 3);
			$pdf->MultiCell($widthrecbox, 4,"(".$RegimenFiscal.') General de Ley Personas Morales', 0, 'L');


			$posy=$pdf->getY();

			// Show sender information
			$pdf->SetXY($posx+2, $posy);
			$pdf->SetFont('', 'B', $default_font_size - 3);
			$pdf->MultiCell($widthrecbox+10, 4, 'Domicilio fiscal:', 0, 'L');

			$pdf->SetXY($posx+25, $posy);
			$pdf->SetFont('', '', $default_font_size - 3);
			$pdf->MultiCell($widthrecbox+10, 4, $info, 0, 'L');

			$posy=$pdf->getY();

			// Show expeded int
			$pdf->SetXY($posx+2, $posy);
			$pdf->SetFont('', 'B', $default_font_size - 3);
			$pdf->MultiCell($widthrecbox+10, 4, 'Expedido en :', 0, 'L');

			$pdf->SetXY($posx+25, $posy);
			$pdf->SetFont('', '', $default_font_size - 3);
			$pdf->MultiCell($widthrecbox+10, 4, $exped, 0, 'L');
			$posy=$pdf->getY();

			/*// Show lugar expeded
			$pdf->SetXY($posx+2, $posy);
			$pdf->SetFont('', 'B', $default_font_size - 3);
			$pdf->MultiCell($widthrecbox+10, 4, 'Lugar de expedición :', 0, 'L');

			$pdf->SetXY($posx+30, $posy);
			$pdf->SetFont('', '', $default_font_size - 3);
			$pdf->MultiCell($widthrecbox+10, 4, $cp, 0, 'L');*/
			
			// If BILLING contact defined on invoice, we use it
			$usecontact=false;
			

		

			// Show recipient
			$widthrecbox=!empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 92 : 100;
			if ($this->page_largeur < 210) $widthrecbox=84;	// To work with US executive format
			$posy=!empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 40 : 42;
			$posy+=$top_shift;
			$posx=$this->page_largeur-$this->marge_droite-$widthrecbox;
			if (! empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx=$this->marge_gauche;

			// Show recipient frame
			/*$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx-90, $posy-1);
			$pdf->MultiCell($widthrecbox, 5, "", 0, 'L');*/
			/*$pdf->Rect($posx, $posy-20, $widthrecbox, $hautcadre-12);*/

			$objsirenorig = getCustInfPym($id);
			
				$siren = "select 'CP01' propouse, d.code_client,d.tva_intra,f.Descripcion,d.siren,d.nom,d.address,d.zip,d.town ";//AOZ/e.propouse
			    $siren.= " FROM ".MAIN_DB_PREFIX."facturedet a ";
				$siren.= " inner join ".MAIN_DB_PREFIX."facture b ";
				$siren.= " on a.fk_facture=b.rowid inner join ".MAIN_DB_PREFIX."societe_extrafields c ";
				$siren.= " on b.fk_soc=c.fk_object  inner join ".MAIN_DB_PREFIX."societe d ";
				$siren.= " on c.fk_object=d.rowid inner join ".MAIN_DB_PREFIX."facture_extrafields e ";
				$siren.= " on b.rowid=e.fk_object inner join ".MAIN_DB_PREFIX."usocfdi f";
				$siren.= " on 'CP01'=f.usocfdi_id left join ".MAIN_DB_PREFIX."paiement_facture g";//AOZ remplazo e.propouse por 'CP01'
		 		$siren.="  on b.rowid=g.fk_facture";
		 		$siren.="  where g.fk_paiement=".$id;
				$siren.= "  group by a.fk_facture";
				$ressiren = $db->query($siren);
				$objsiren = $ressiren->fetch_all(MYSQLI_ASSOC);

			//show code_client
			$pdf->SetXY($posx-90, $posy-9);
			$pdf->SetFont('', 'B', $default_font_size-3);
			$pdf->MultiCell($widthrecbox, 2, 'Cliente:', 0, 'L');
			$pdf->SetXY($posx-79, $posy-9);
			$pdf->SetFont('', '', $default_font_size-3);
			$pdf->MultiCell($widthrecbox, 2, $objsiren[0]['code_client'] , 0, 'L');

			//show rfc
			$pdf->SetXY($posx-48, $posy-9);
			$pdf->SetFont('', 'B', $default_font_size-3);
			$pdf->MultiCell($widthrecbox, 2, 'RFC:', 0, 'L');

			$pdf->SetXY($posx-40, $posy-9);
			$pdf->SetFont('', '', $default_font_size-3);
			$pdf->MultiCell($widthrecbox, 2, $objsiren[0]['siren'] , 0, 'L');

			// Show recipient name
			$pdf->SetXY($posx-90, $posy-5);
			$pdf->SetFont('', '', $default_font_size-3);
			$pdf->MultiCell($widthrecbox, 2, $objsirenorig[0]['nom'], 0, 'L');

			$posy = $pdf->getY();

			// Show recipient information
			$pdf->SetFont('', '', $default_font_size -4);
			$pdf->SetXY($posx-90, $posy);
			$pdf->MultiCell($widthrecbox-17, 2, $objsiren[0]['address'].' '.$objsiren[0]['zip'].' '.$objsiren[0]['town'], 0, 'L');
				

			// show siren
			$posy = $pdf->getY();
			$pdf->SetFont('', 'B', $default_font_size - 3);
			$pdf->SetXY($posx-90, $posy+3);
			$pdf->MultiCell($widthrecbox, 4, 'Uso del CFDI', 0, 'L');
			$pdf->SetFont('', '', $default_font_size - 3);
			$pdf->SetXY($posx-70, $posy+3);
			$pdf->MultiCell($widthrecbox, 4,  '('.$objsiren[0]['propouse'].') '.$objsiren[0]['Descripcion'], 0, 'L');


			// Show recipient name
			$pdf->SetXY($posx-90, $posy);
			$pdf->SetFont('', '', $default_font_size-3);
			$pdf->MultiCell($widthrecbox, 3, '('.$objsiren[0]['nom'].')', 0, 'L');
		
			
		$pdf->SetTextColor(0, 0, 0);
		return $top_shift;
	}

	private function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $currency = '')
	{
		global $conf;
		$tab_height=$tab_height-15;
		// Force to disable hidetop and hidebottom
		$hidebottom=0;
		if ($hidetop) $hidetop=-1;

		
	}
		private function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
		{
			global $conf;
			
			return pdf_pagefoot($pdf, $outputlangs, 'INVOICE_FREE_TEXT', '', $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
		}
	}


 ?>