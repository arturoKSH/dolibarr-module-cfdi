<?php
require_once DOL_DOCUMENT_ROOT . '/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';

class pdfAR extends ModelePDFFactures
{
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
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = isset($conf->global->MAIN_PDF_MARGIN_LEFT) ? $conf->global->MAIN_PDF_MARGIN_LEFT : 10;
		$this->marge_droite = isset($conf->global->MAIN_PDF_MARGIN_RIGHT) ? $conf->global->MAIN_PDF_MARGIN_RIGHT : 10;
		$this->marge_haute = isset($conf->global->MAIN_PDF_MARGIN_TOP) ? $conf->global->MAIN_PDF_MARGIN_TOP : 10;
		$this->marge_basse = isset($conf->global->MAIN_PDF_MARGIN_BOTTOM) ? $conf->global->MAIN_PDF_MARGIN_BOTTOM : 10;

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

		$this->franchise = !$mysoc->tva_assuj;

		// Get source company
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) $this->emetteur->country_code = substr($langs->defaultlang, -2);    // By default, if was not defined

		// Define position of columns
		$this->posxdesc = $this->marge_gauche + 1;
		if (!empty($conf->global->PRODUCT_USE_UNITS)) {
			$this->posxtva = 106;
			$this->posxup = 100;
			$this->posxqty = 135;
			$this->posxunit = 151;
		} else {
			$this->posxpeso = 115;
			$this->posxtva = 120;
			$this->posxup = 135;
			$this->posxqty = 150;
			$this->posxunit = 162;
		}
		$this->posxprogress = 151; // Only displayed for situation invoices
		$this->posxdiscount = 162;
		$this->posxprogress = 174;
		$this->postotalht = 174;
		if (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) || !empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN)) $this->posxtva = $this->posxup;
		$this->posxpicture = $this->posxtva - (empty($conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH) ? 20 : $conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH);	// width of images
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$this->posxpicture -= 20;
			$this->posxpeso -= 20;
			$this->posxtva -= 20;
			$this->posxup -= 20;
			$this->posxqty -= 20;
			$this->posxunit -= 20;
			$this->posxdiscount -= 20;
			$this->posxprogress -= 20;
			$this->postotalht -= 20;
		}

		$this->tva = array();
		$this->tipotva = array();
		$this->localtax1 = array();
		$this->localtax2 = array();
		$this->atleastoneratenotnull = 0;
		$this->atleastonediscount = 0;
		$this->situationinvoice = false;
	}
	public  function generar($id, $outputlangs, $db, $ref, $xml)
	{
		if (!empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output = 'ISO-8859-1';
		$outputlangs->loadLangs(array("main", "bills", "products", "dict", "companies", "RelCFDI"));

		//mkdir(DOL_DATA_ROOT."/facture/".$ref, 0700);
		// $carpeta = DOL_DATA_ROOT . "/PDFRelCFDI";
		// if (!file_exists($carpeta)) {
		// 	mkdir($carpeta, 0777, true);
		// }

		$dir =  DOL_DATA_ROOT."/facture/".$ref;
		$file = $dir.'/'.$ref."-FinalCancel.pdf";

		$pdf = pdf_getInstance($this->format);
		$default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
		$pdf->SetAutoPageBreak(1, 0);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
		$pdf->Open();
		$pagenb = 0;
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities("PdfInvoiceTitle"));
		$pdf->SetCreator("Dolibarr " . DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset("prueba"));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref) . " " . $outputlangs->transnoentities("PdfInvoiceTitle") . " " . $outputlangs->convToOutputCharset($object->thirdparty->name));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // 

		$pdf->AddPage();
		$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs, $db, $id); //nueva pagina
		$tab_top = 55 + $top_shift;
		$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD) ? 52 + $top_shift : 10);

		$iniY = $tab_top + 7;
		$curY = $tab_top + 7;
		$nexY = $tab_top + 7;
		//Fecha y hora de solicitud:
		$pdf->SetXY($tab3_posx + 36, 45);
		$pdf->SetFont('', 'B', 10);
		$pdf->MultiCell(100, 3, "Fecha y hora de solicitud:", 0, 'L', 0);
		$pdf->SetXY($tab3_posx + 96, 45);
		$pdf->SetFont('', '', 10);
		$fecha = substr($xml['Fecha'], 0, 10) . ' ' . substr($xml['Fecha'], 11, 8);
		$pdf->MultiCell(120, 3, $fecha, 0, 'L', 0);

		//emisor
		$pdf->SetXY($tab3_posx + 36, 55);
		$pdf->SetFont('', 'B', 10);
		$pdf->MultiCell(100, 3, "RFC Emisor:", 0, 'L', 0);
		$pdf->SetXY($tab3_posx + 96, 55);
		$pdf->SetFont('', '', 10);
		$pdf->MultiCell(120, 3, $xml['RfcPac'], 0, 'L', 0);

		//folio fiscal
		$pdf->SetXY($tab3_posx + 36, 65);
		$pdf->SetFont('', 'B', 10);
		$pdf->MultiCell(100, 3, "Folio fiscal:", 0, 'L', 0);

		$pdf->SetXY($tab3_posx + 99, 65);
		$pdf->SetFont('', 'B', 10);
		$pdf->MultiCell(100, 3, "Estatus de Proceso de Cancelacion:", 0, 'L', 0);
		// Show sender
		$posy = 85;
		$posx = $tab3_posx + 96;

		$widthrecbox = 63;
		$hautcadre =  5;
		//cuadro
		//folio
		$pdf->Rect($tab3_posx + 36, 65, $widthrecbox, $hautcadre);
		$pdf->Rect($tab3_posx + 36, 70, $widthrecbox, $hautcadre + 8);
		//estatus
		$pdf->Rect($tab3_posx + 99, 65, $widthrecbox + 15, $hautcadre);
		$pdf->Rect($tab3_posx + 99, 70, $widthrecbox + 15, $hautcadre + 8);
		// Show sender frame
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->SetXY($tab3_posx + 36, 70);
		// var_dump($xml->Folios->UUID);
		$pdf->MultiCell($widthrecbox, $hautcadre, $xml->Folios->UUID, 0, 'L');
		$pdf->SetXY($tab3_posx + 99, 70);
		$pdf->MultiCell($widthrecbox + 15, $hautcadre, $xml->Folios['Respuesta'], 0, 'L');

		$pdf->SetTextColor(0, 0, 60);

		$pdf->SetXY($tab3_posx + 36, 85);
		$pdf->SetFont('', 'B', 10);
		$pdf->MultiCell(100, 3, "Sello digital SAT:", 0, 'L', 0);
		$pdf->SetXY($tab3_posx + 36, 90);
		$pdf->SetFont('', '', 10);
		$pdf->MultiCell(140, 3, $xml->Signature->SignatureValue, 0, 'L', 0);

		// $pdf->Line(10, 55, 210-10, 55);
		//info pago 

		// Description of product line
		$pdf->SetFont('', '', $default_font_size - 3);   // Into loop to work with multipage
		$pdf->SetTextColor(0, 0, 0);

		// Define size of image if we need it
		$imglinesize = array();
		if (!empty($realpatharray[$i])) $imglinesize = pdf_getSizeForImage($realpatharray[$i]);

		$pdf->setTopMargin($tab_top_newpage - 10);
		$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext + $heightforinfotot);	// The only function to edit the bottom margin of current page to set it.
		$pageposbefore = $pdf->getPage();

		$showpricebeforepagebreak = 1;
		$posYAfterImage = 0;
		$posYAfterDescription = 0;
		$curX = $this->posxdesc + 27;
		/*$hidedesc='holaaa';*/
		$pdf->startTransaction();
		pdf_writelinedesc($pdf, null, 0, $outputlangs, 50, 3, $curX, $curY, $hideref, $hidedesc);
		$pageposafter = $pdf->getPage();

		// Show square
		if ($pagenb == 1) {
			$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0, $object->multicurrency_code);
			$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
		} else {
			$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0, $object->multicurrency_code);
			$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
		}

		$posy = $this->_tableau_tot($pdf, $object, $deja_regle, $bottomlasttab, $outputlangs, $objfactuas[0]['pago'], $objfactuas[0]['countt']);

		$this->_pagefoot($pdf, $object, $outputlangs);
		$pdf->Close();

		$pdf->Output($file, 'F');
		return 0;
	}


	private function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs, $ref, $count)
	{
		// phpcs:enable
		global $conf, $mysoc;

		$sign = 1;
		if ($object->type == 2 && !empty($conf->global->INVOICE_POSITIVE_CREDIT_NOTE)) $sign = -1;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy + 13;
		$tab2_hl = 4;
		/*$pdf->SetFont('', 'B', $default_font_size - 2);*/

		// Tableau total
		$col1x = 120;
		$col2x = 170;
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$col2x -= 20;
		}
		$largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

		$useborder = 0;
		$index = 0;
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
	private function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $db, $id)
	{
		global $conf, $langs;

		// Load traductions files requiredby by page
		$outputlangs->loadLangs(array("main", "bills", "propal", "companies"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
		/*if ($showaddress)
		{*/
		// Show Draft Watermark
		if ($object->statut == Facture::STATUS_DRAFT && (!empty($conf->global->FACTURE_DRAFT_WATERMARK))) {
			pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $conf->global->FACTURE_DRAFT_WATERMARK);
		}

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 110;

		$posy = $this->marge_haute;
		$posx = $this->page_largeur - $this->marge_droite - $w;

		$pdf->SetXY($this->marge_gauche, $posy);

		$posy += 1;

		$top_shift = 0;
		// Show list of linked objects
		$current_y = $pdf->getY();
		//$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
		if ($current_y < $pdf->getY()) {
			$top_shift = $pdf->getY() - $current_y;
		}
		/*}*/
		/*if ($showaddress)
		{*/
		// Sender properties
		$carac_emetteur = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

		// Show sender
		$posy = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 40 : 42;
		$posy += $top_shift;
		$posx = $this->marge_gauche;
		if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx = $this->page_largeur - $this->marge_droite - 80;

		$hautcadre = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 38 : 40;
		$widthrecbox = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 92 : 82;


		// Show sender frame
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 2);
		$pdf->SetXY($posx, $posy - 38);
		/*$pdf->MultiCell(66, 5, "", 0, 'L');*/
		$pdf->SetXY($posx, $posy - 15);
		/*$pdf->SetFillColor(230, 230, 230);*/
		/*$pdf->MultiCell($widthrecbox, $hautcadre-5, "", 0, 'R');*/
		/*$pdf->Rect($posx, $posy-20, $widthrecbox, $hautcadre-12);*/
		$pdf->SetTextColor(0, 0, 60);

		// Show sender name customer
		$datosempresa = " SELECT name,value ";
		$datosempresa .= " FROM " . MAIN_DB_PREFIX . "const where name like 'MAIN_INFO%'";
		$resdatosempresa = $db->query($datosempresa);
		$objdatosempresa = $resdatosempresa->fetch_all(MYSQLI_ASSOC);
		$datoss = array('MAIN_INFO_SIREN', 'MAIN_INFO_SOCIETE_OBJECT', 'MAIN_INFO_SOCIETE_ADDRESS', 'MAIN_INFO_SOCIETE_ZIP', 'MAIN_INFO_SOCIETE_TOWN');
		$cont = 0;
		$NameCompany = "";
		$Rfcc = "";
		$CodigoPostal = "";
		foreach ($objdatosempresa as $key => $value) {

			if ($value['name'] == $datoss[0]) {
				$Rfcc = $value['value'];
			}
			if ($value['name'] == $datoss[1]) {
				$RegimenFiscal = $value['value'];
			}
			if ($value['name'] == $datoss[2]) {
				$addres = $value['value'];
			}
			if ($value['name'] == $datoss[3]) {
				$cp = $value['value'];
			}
			if ($value['name'] == $datoss[4]) {
				$town = $value['value'];
			}
			$cont++;
		}


		$pdf->SetXY($posx + 60, $posy - 30);
		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->MultiCell($widthrecbox + 10, 4, 'Servicio de Administración Tributaria', 0, 'L');
		$pdf->SetXY($posx + 58, $posy - 20);
		$pdf->SetFont('', 'B', $default_font_size + 2);
		$pdf->MultiCell($widthrecbox + 10, 4, 'Acuse de Solicitud de Cancelacion de CFDI', 0, 'L');

		$pdf->SetXY($tab3_posx + 8, $posy - 30);
		$pdf->MultiCell($widthrecbox + 10, 4, $pdf->Image("./hacienda.png", $pdf->GetX(), $pdf->GetY(), 55, 22), 0, 'C', 0);

		// //show rfc
		// $pdf->SetXY($posx+2, $posy-30);
		// $pdf->SetFont('', 'B', $default_font_size-3);
		// $pdf->MultiCell($widthrecbox-1, 4, 'R.F.C. :', 0, 'L');

		// $pdf->SetXY($posx+10, $posy-30);
		// $pdf->SetFont('', '', $default_font_size-3);
		// $pdf->MultiCell($widthrecbox-1, 4, $Rfcc, 0, 'L');
		// $posy=$pdf->getY();

		// //show Regimen fiscal


		// $pdf->SetXY($posx+2, $posy);
		// $pdf->SetFont('', 'B', $default_font_size - 3);
		// $pdf->MultiCell($widthrecbox, 4,'Régimen Fiscal: ', 0, 'L');
		// $pdf->SetXY($posx+25, $posy);
		// $pdf->SetFont('', '', $default_font_size - 3);
		// $pdf->MultiCell($widthrecbox, 4,"(".$RegimenFiscal.') General de Ley Personas Morales', 0, 'L');


		// $posy=$pdf->getY();

		// // Show sender information
		// $pdf->SetXY($posx+2, $posy);
		// $pdf->SetFont('', 'B', $default_font_size - 3);
		// $pdf->MultiCell($widthrecbox+10, 4, 'Domicilio fiscal:', 0, 'L');

		// $pdf->SetXY($posx+25, $posy);
		// $pdf->SetFont('', '', $default_font_size - 3);
		// $pdf->MultiCell($widthrecbox+10, 4, $info, 0, 'L');

		// $posy=$pdf->getY();

		// // Show expeded int
		// $pdf->SetXY($posx+2, $posy);
		// $pdf->SetFont('', 'B', $default_font_size - 3);
		// $pdf->MultiCell($widthrecbox+10, 4, 'Expedido en :', 0, 'L');

		// $pdf->SetXY($posx+25, $posy);
		// $pdf->SetFont('', '', $default_font_size - 3);
		// $pdf->MultiCell($widthrecbox+10, 4, $exped, 0, 'L');
		// $posy=$pdf->getY();

		/*// Show lugar expeded
			$pdf->SetXY($posx+2, $posy);
			$pdf->SetFont('', 'B', $default_font_size - 3);
			$pdf->MultiCell($widthrecbox+10, 4, 'Lugar de expedición :', 0, 'L');

			$pdf->SetXY($posx+30, $posy);
			$pdf->SetFont('', '', $default_font_size - 3);
			$pdf->MultiCell($widthrecbox+10, 4, $cp, 0, 'L');*/

		// If BILLING contact defined on invoice, we use it
		$usecontact = false;




		// Show recipient
		$widthrecbox = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 92 : 100;
		if ($this->page_largeur < 210) $widthrecbox = 84;	// To work with US executive format
		$posy = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 40 : 42;
		$posy += $top_shift;
		$posx = $this->page_largeur - $this->marge_droite - $widthrecbox;
		if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx = $this->marge_gauche;

		// Show recipient frame
		/*$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx-90, $posy-1);
			$pdf->MultiCell($widthrecbox, 5, "", 0, 'L');*/
		/*$pdf->Rect($posx, $posy-20, $widthrecbox, $hautcadre-12);*/
		$siren = "select e.propouse, d.code_client,d.tva_intra,f.Descripcion,d.siren,d.nom,d.address,d.zip,d.town ";
		$siren .= " FROM " . MAIN_DB_PREFIX . "facturedet a ";
		$siren .= " inner join " . MAIN_DB_PREFIX . "facture b ";
		$siren .= " on a.fk_facture=b.rowid inner join " . MAIN_DB_PREFIX . "societe_extrafields c ";
		$siren .= " on b.fk_soc=c.fk_object  inner join " . MAIN_DB_PREFIX . "societe d ";
		$siren .= " on c.fk_object=d.rowid inner join " . MAIN_DB_PREFIX . "facture_extrafields e ";
		$siren .= " on b.rowid=e.fk_object inner join " . MAIN_DB_PREFIX . "usocfdi f";
		$siren .= " on e.propouse=f.usocfdi_id left join " . MAIN_DB_PREFIX . "paiement_facture g";
		$siren .= "  on b.rowid=g.fk_facture";
		$siren .= "  where g.fk_paiement=" . $id;
		$siren .= "  group by a.fk_facture";
		$ressiren = $db->query($siren);
		$objsiren = $ressiren->fetch_all(MYSQLI_ASSOC);



		//show rfc
		// $pdf->SetXY($posx-48, $posy-9);
		// $pdf->SetFont('', 'B', $default_font_size-3);
		// $pdf->MultiCell($widthrecbox, 2, 'RFC:', 0, 'L');

		// $pdf->SetXY($posx-40, $posy-9);
		// $pdf->SetFont('', '', $default_font_size-3);
		// $pdf->MultiCell($widthrecbox, 2, $objsiren[0]['siren'] , 0, 'L');



		$pdf->SetTextColor(0, 0, 0);
		return $top_shift;
	}

	private function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $currency = '')
	{
		global $conf;
		$tab_height = $tab_height - 15;
		// Force to disable hidetop and hidebottom
		$hidebottom = 0;
		if ($hidetop) $hidetop = -1;
	}
	private function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		global $conf;

		return pdf_pagefoot($pdf, $outputlangs, 'INVOICE_FREE_TEXT', '', $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
	}
}
