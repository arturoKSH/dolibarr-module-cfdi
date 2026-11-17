<?php
/**
 * Bloque de datos fiscales del CFDI timbrado al final de la última hoja del PDF de la factura.
 *
 * Funciona con los modelos estándar que tienen el hook afterPDFTotalTable (sponge, octopus):
 *  1. beforePDFCreation: si la factura está timbrada, sube MAIN_PDF_FREETEXT_HEIGHT para que el
 *     modelo reserve el alto del bloque en la última hoja (lo lee justo después del hook).
 *  2. afterPDFTotalTable: dibuja el bloque en ese espacio, entre los totales y el pie de página.
 *  3. afterPDFCreation: restaura MAIN_PDF_FREETEXT_HEIGHT.
 * Los modelos personalizados (abc, crasa, zakili) pintan su propio bloque y no se tocan.
 */
class CfdiPdfBlock
{
	/** Modelos de factura que llaman afterPDFTotalTable y leen MAIN_PDF_FREETEXT_HEIGHT */
	const MODELS = array('sponge', 'octopus');

	/** Alto del bloque en mm */
	const HEIGHT = 50;

	/** @var array|null Datos del timbre de la factura que se está generando */
	private static $stamp = null;

	/** @var array{0:bool,1:mixed}|null Valor original de MAIN_PDF_FREETEXT_HEIGHT */
	private static $savedFreeText = null;

	/**
	 * Reserva el espacio si la factura está timbrada y el modelo lo soporta.
	 *
	 * @param  CommonObject $object Factura
	 * @return void
	 */
	public static function reserve($object)
	{
		global $conf;

		self::restore();
		self::$stamp = null;
		if (!is_object($object) || $object->element != 'facture') {
			return;
		}
		$model = explode(':', $object->model_pdf ?: getDolGlobalString('FACTURE_ADDON_PDF'))[0];
		if (!in_array($model, self::MODELS)) {
			return;
		}
		$stamp = self::readStamp($object->ref);
		if (!$stamp) {
			return;
		}
		self::$stamp = $stamp;
		self::$savedFreeText = array(isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT), $conf->global->MAIN_PDF_FREETEXT_HEIGHT ?? null);
		$conf->global->MAIN_PDF_FREETEXT_HEIGHT = getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5) + self::HEIGHT;
	}

	/**
	 * Restaura MAIN_PDF_FREETEXT_HEIGHT.
	 *
	 * @return void
	 */
	public static function restore()
	{
		global $conf;

		if (self::$savedFreeText === null) {
			return;
		}
		if (self::$savedFreeText[0]) {
			$conf->global->MAIN_PDF_FREETEXT_HEIGHT = self::$savedFreeText[1];
		} else {
			unset($conf->global->MAIN_PDF_FREETEXT_HEIGHT);
		}
		self::$savedFreeText = null;
	}

	/**
	 * Dibuja el bloque en el espacio reservado de la página actual (la última).
	 *
	 * @param  TCPDF             $pdf     PDF en construcción
	 * @param  CommonObject      $object  Factura
	 * @param  ModelePDFFactures $model   Modelo que genera el PDF
	 * @return void
	 */
	public static function draw($pdf, $object, $model)
	{
		$stamp = self::$stamp;
		// Sin reserva (otro modelo, o el modelo real no es el que se esperaba) se dibujaría encima de otra cosa.
		if (!$stamp || self::$savedFreeText === null || $stamp['ref'] !== $object->ref || !in_array($model->name ?? '', self::MODELS)) {
			return;
		}

		$left = $model->marge_gauche;
		$width = $model->page_largeur - $model->marge_gauche - $model->marge_droite;
		$top = $model->page_hauteur - $model->heightforfooter - $model->heightforfreetext + 2;
		$qrSize = 28;
		$textWidth = $width - $qrSize - 4;
		$fontSize = 6;

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetDrawColor(128, 128, 128);
		$pdf->Rect($left, $top, $width, self::HEIGHT - 4);

		$y = $top + 1;
		// El UUID (36 caracteres) necesita la columna más ancha.
		$colWidths = array($textWidth * 0.31, $textWidth * 0.25, $textWidth * 0.24, $textWidth * 0.20);
		$pairs = array(
			array('Folio fiscal (UUID)', $stamp['uuid']),
			array('No. de serie del CSD del emisor', $stamp['noCertificado']),
			array('No. de serie del CSD del SAT', $stamp['noCertificadoSat']),
			array('Fecha y hora de certificación', $stamp['fechaTimbrado']),
			array('Régimen fiscal del emisor', $stamp['regimenEmisor']),
			array('Uso del CFDI', $stamp['usoCfdi']),
			array('Método de pago', $stamp['metodoPago']),
			array('Forma de pago', $stamp['formaPago']),
		);
		foreach ($pairs as $i => $pair) {
			$col = $i % 4;
			$x = $left + 1 + array_sum(array_slice($colWidths, 0, $col));
			$rowY = $y + intdiv($i, 4) * 6;
			$pdf->SetXY($x, $rowY);
			$pdf->SetFont('', 'B', $fontSize - 0.5);
			$pdf->Cell($colWidths[$col], 2.5, $pair[0], 0, 0, 'L');
			$pdf->SetXY($x, $rowY + 2.5);
			$pdf->SetFont('', '', $fontSize);
			$pdf->Cell($colWidths[$col], 2.5, $pair[1], 0, 0, 'L');
		}
		$y += 13;

		$texts = array(
			array('Sello digital del CFDI', $stamp['selloCfd']),
			array('Sello digital del SAT', $stamp['selloSat']),
			array('Cadena original del complemento de certificación digital del SAT', $stamp['cadenaOriginal']),
		);
		foreach ($texts as $text) {
			$pdf->SetXY($left + 1, $y);
			$pdf->SetFont('', 'B', $fontSize - 0.5);
			$pdf->Cell($textWidth, 2.5, $text[0], 0, 0, 'L');
			$pdf->SetXY($left + 1, $y + 2.5);
			$pdf->SetFont('', '', $fontSize - 1.5);
			$pdf->MultiCell($textWidth, 2, $text[1], 0, 'L', false, 1, '', '', true, 0, false, true, 0, 'T', false);
			$y = $pdf->GetY() + 0.8;
		}

		$pdf->SetXY($left + 1, $y + 0.5);
		$pdf->SetFont('', 'I', $fontSize - 0.5);
		$pdf->Cell($textWidth, 3, 'Este documento es una representación impresa de un CFDI.', 0, 0, 'L');

		$pdf->write2DBarcode($stamp['qr'], 'QRCODE,M', $left + $width - $qrSize - 1, $top + 6, $qrSize, $qrSize, array('border' => false, 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false), 'N');
	}

	/**
	 * Lee del XML timbrado los datos del bloque.
	 *
	 * @param  string     $ref Ref de la factura
	 * @return array|null      null si no hay XML o no está timbrado
	 */
	private static function readStamp($ref)
	{
		$file = DOL_DATA_ROOT.'/facture/'.dol_sanitizeFileName($ref).'/'.dol_sanitizeFileName($ref).'.xml';
		if (!is_readable($file)) {
			return null;
		}
		$doc = new DOMDocument('1.0', 'UTF-8');
		if (!@$doc->load($file)) {
			return null;
		}
		$cfdi = $doc->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Comprobante')->item(0);
		$tfd = $doc->getElementsByTagNameNS('http://www.sat.gob.mx/TimbreFiscalDigital', 'TimbreFiscalDigital')->item(0);
		if (!$cfdi || !$tfd || $tfd->getAttribute('UUID') === '') {
			return null;
		}
		$emisor = $doc->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Emisor')->item(0);
		$receptor = $doc->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/4', 'Receptor')->item(0);

		$uuid = $tfd->getAttribute('UUID');
		$selloCfd = $tfd->getAttribute('SelloCFD');
		$cadena = '||'.implode('|', array_filter(array(
			$tfd->getAttribute('Version'), $uuid, $tfd->getAttribute('FechaTimbrado'), $tfd->getAttribute('RfcProvCertif'),
			$tfd->getAttribute('Leyenda'), $selloCfd, $tfd->getAttribute('NoCertificadoSAT'),
		), 'strlen')).'||';

		return array(
			'ref' => $ref,
			'uuid' => $uuid,
			'noCertificado' => $cfdi->getAttribute('NoCertificado'),
			'noCertificadoSat' => $tfd->getAttribute('NoCertificadoSAT'),
			'fechaTimbrado' => $tfd->getAttribute('FechaTimbrado'),
			'regimenEmisor' => $emisor ? $emisor->getAttribute('RegimenFiscal') : '',
			'usoCfdi' => $receptor ? $receptor->getAttribute('UsoCFDI') : '',
			'metodoPago' => $cfdi->getAttribute('MetodoPago'),
			'formaPago' => $cfdi->getAttribute('FormaPago'),
			'selloCfd' => $selloCfd,
			'selloSat' => $tfd->getAttribute('SelloSAT'),
			'cadenaOriginal' => $cadena,
			// URL de verificación del SAT (Anexo 20): fe = últimos 8 caracteres del sello del emisor.
			'qr' => 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?id='.$uuid
				.'&re='.($emisor ? $emisor->getAttribute('Rfc') : '')
				.'&rr='.($receptor ? $receptor->getAttribute('Rfc') : '')
				.'&tt='.$cfdi->getAttribute('Total')
				.'&fe='.substr($selloCfd, -8),
		);
	}
}
