<?php
/* Copyright (C) 2023 Admin SuperAdmin <orasles@orasles.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    cfdi/lib/cfdi.lib.php
 * \ingroup cfdi
 * \brief   Library files with common functions for Cfdi
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function cfdiAdminPrepareHead()
{
	global $langs, $conf;

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('myobject');

	$langs->load("cfdi@cfdi");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/custom/cfdi/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dol_buildpath("/cfdi/admin/myobject_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = is_countable($extrafields->attributes['myobject']['label']) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= ' <span class="badge">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;
	*/

	$head[$h][0] = dol_buildpath("/custom/cfdi/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	$head[$h][0] = dol_buildpath("/custom/cfdi/admin/test.php", 1);
	$head[$h][1] = $langs->trans('CfdiDiagTitle');
	$head[$h][2] = 'test';
	$h++;

	$head[$h][0] = dol_buildpath("/custom/cfdi/admin/errorlog.php", 1);
	$head[$h][1] = $langs->trans('CfdiErrorLogTitle');
	$head[$h][2] = 'errorlog';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@cfdi:/cfdi/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@cfdi:/cfdi/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'cfdi@cfdi');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'cfdi@cfdi', 'remove');

	return $head;
}

/**
 * Sanea un nombre de certificado. Whitelist: solo alfanumerico, guion y guion bajo.
 * Es la unica funcion que decide que es un nombre valido; el resultado se usa tanto
 * para el directorio como para el nombre de archivo, para que no puedan divergir.
 *
 * @param  string $certName Nombre crudo
 * @return string           Nombre saneado, cadena vacia si no queda nada utilizable
 */
function cfdiCertSafeName($certName)
{
	$safe = preg_replace('/[^A-Za-z0-9_-]/', '', basename((string) $certName));

	return (string) substr($safe, 0, 64);
}

/**
 * Directorio de certificados CFDI: DOL_DATA_ROOT/cfdi/certs/, no custom/.
 *
 * DOL_DATA_ROOT es donde Dolibarr espera los datos privados y en una instalacion
 * estandar queda fuera del DocumentRoot. OJO: no esta garantizado -- si
 * $dolibarr_main_data_root apunta dentro del arbol web (p.ej. /var/www/html/documents)
 * el directorio si es alcanzable por HTTP. Por eso cfdiEnsureCertDir() escribe
 * siempre un .htaccess, y el despliegue debe blindar documents/ aparte.
 *
 * @param  string $certName Nombre del certificado; vacio devuelve el directorio raiz
 * @return string           Ruta con separador final, cadena vacia si el nombre es invalido
 */
function cfdiCertDir($certName = '')
{
	$root = DOL_DATA_ROOT.'/cfdi/certs/';

	if ((string) $certName === '') {
		return $root;
	}

	$safe = cfdiCertSafeName($certName);

	return ($safe === '') ? '' : $root.$safe.'/';
}

/**
 * Ruta completa a un archivo de certificado.
 *
 * @param  string $certName Nombre del certificado
 * @param  string $suffix   Sufijo con punto: '.cer', '.cer.pem', '.key', '.key.pem'
 * @return string           Ruta absoluta, cadena vacia si el nombre es invalido
 */
function cfdiCertFile($certName, $suffix)
{
	$dir  = cfdiCertDir($certName);
	$safe = cfdiCertSafeName($certName);

	return ($dir === '' || $safe === '') ? '' : $dir.$safe.$suffix;
}

/**
 * Crea el directorio de certificados si no existe y lo blinda contra acceso web.
 * DOL_DATA_ROOT ya suele estar fuera del DocumentRoot; el .htaccess es defensa
 * en profundidad por si alguien lo expone por error.
 *
 * @param  string $certName Nombre del certificado
 * @return string           Directorio creado, cadena vacia si fallo
 */
function cfdiEnsureCertDir($certName)
{
	$root = cfdiCertDir();
	$dir  = cfdiCertDir($certName);

	if ($dir === '') {
		return '';
	}

	// El .htaccess se escribe antes de mover archivos, no despues.
	if (!is_dir($root) && !dol_mkdir($root)) {
		return '';
	}
	if (!file_exists($root.'.htaccess')) {
		file_put_contents($root.'.htaccess', "Require all denied\n");
	}
	if (!is_dir($dir) && !dol_mkdir($dir)) {
		return '';
	}

	return $dir;
}

/**
 * Mueve certificados que quedaron en la ruta antigua dentro de custom/ (web-accessible)
 * hacia DOL_DATA_ROOT. Idempotente: no hace nada si ya no existe la ruta antigua.
 *
 * @param  string $certName Nombre del certificado configurado
 * @return bool             True si movio algo
 */
function cfdiMigrateLegacyCertDir($certName)
{
	$safe = cfdiCertSafeName($certName);
	if ($safe === '') {
		return false;
	}

	$legacyDir = DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/cfdi_Cert/'.$safe.'/';
	$newDir    = cfdiCertDir($safe);

	if (!is_dir($legacyDir) || $newDir === '' || is_dir($newDir)) {
		return false;
	}
	if (cfdiEnsureCertDir($safe) === '') {
		return false;
	}

	$moved = false;
	foreach (array('.cer', '.cer.pem', '.key', '.key.pem') as $suffix) {
		$src = $legacyDir.$safe.$suffix;
		if (file_exists($src) && @rename($src, $newDir.$safe.$suffix)) {
			$moved = true;
		}
	}
	if ($moved) {
		dol_delete_dir_recursive($legacyDir);
	}

	return $moved;
}

/**
 * Envuelve un archivo DER (.cer o .key del SAT) en formato PEM.
 *
 * @param  string $der   Contenido binario
 * @param  string $label Etiqueta PEM, ej. 'CERTIFICATE' o 'ENCRYPTED PRIVATE KEY'
 * @return string
 */
function cfdiDerToPem($der, $label)
{
	return "-----BEGIN ".$label."-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END ".$label."-----\n";
}

/**
 * Normalize certificate content received as DER or PEM.
 *
 * @param string $content Certificate bytes
 * @return string
 */
function cfdiCertificateToPem($content)
{
	$content = (string) $content;
	return strpos($content, '-----BEGIN CERTIFICATE-----') !== false
		? rtrim($content)."\n"
		: cfdiDerToPem($content, 'CERTIFICATE');
}

/**
 * Load a private key from encrypted/unencrypted DER or PEM and export it as owner-only PEM.
 *
 * @param string $content Private key bytes
 * @param string $password Optional private key password
 * @return array{resource|OpenSSLAsymmetricKey|false,string}
 */
function cfdiPrivateKeyToPem($content, $password = '')
{
	$content = (string) $content;
	$candidates = array();

	if (strpos($content, '-----BEGIN ') !== false) {
		$candidates[] = $content;
	} else {
		$candidates[] = cfdiDerToPem($content, 'ENCRYPTED PRIVATE KEY');
		$candidates[] = cfdiDerToPem($content, 'PRIVATE KEY');
		$candidates[] = cfdiDerToPem($content, 'RSA PRIVATE KEY');
	}

	foreach ($candidates as $candidate) {
		$key = @openssl_pkey_get_private($candidate, $password);
		if ($key === false) {
			continue;
		}

		$keyPem = '';
		if (@openssl_pkey_export($key, $keyPem)) {
			return array($key, $keyPem);
		}
	}

	return array(false, '');
}

/**
 * Lee un .cer (DER o PEM) y devuelve info del certificado o array con error.
 *
 * @param  string $certName Nombre saneado del certificado (ver MAIN_INFO_CFDI_CERT_NAME)
 * @return array
 */
function cfdiParseCert($certName)
{
	$base = cfdiCertDir($certName);
	if ($base === '') {
		return array('error' => 'Nombre de certificado invalido');
	}

	// Intentar PEM primero, luego convertir DER
	$pemFile = cfdiCertFile($certName, '.cer.pem');
	$derFile = cfdiCertFile($certName, '.cer');

	$pem = '';
	if (file_exists($pemFile)) {
		$pem = file_get_contents($pemFile);
	} elseif (file_exists($derFile)) {
		$der = file_get_contents($derFile);
		if ($der !== false) {
			$pem = cfdiDerToPem($der, 'CERTIFICATE');
		}
	}

	if (empty($pem)) {
		return array('error' => 'Archivo de certificado no encontrado en '.$base);
	}

	$info = @openssl_x509_parse($pem);
	if (!$info) {
		return array('error' => 'No se pudo leer el certificado (openssl_x509_parse)');
	}

	$validTo   = isset($info['validTo_time_t'])   ? (int) $info['validTo_time_t']   : 0;
	$validFrom = isset($info['validFrom_time_t']) ? (int) $info['validFrom_time_t'] : 0;
	$now       = time();
	$daysLeft  = $validTo ? (int) round(($validTo - $now) / 86400) : 0;
	$expired   = ($validTo && $now > $validTo);
	$subject   = isset($info['subject']) ? $info['subject'] : array();
	$cn        = isset($subject['CN']) ? $subject['CN'] : (isset($subject['O']) ? $subject['O'] : '');

	return array(
		'ok'        => !$expired && $validTo > 0,
		'expired'   => $expired,
		'daysLeft'  => $daysLeft,
		'validFrom' => $validFrom ? date('d/m/Y', $validFrom) : '?',
		'validTo'   => $validTo   ? date('d/m/Y', $validTo)   : '?',
		'cn'        => $cn,
		'error'     => '',
	);
}

/**
 * Registra un intento fallido de timbrado/cancelacion CFDI, para diagnostico.
 * Solo se llama en las rutas de error -- no registra intentos exitosos.
 *
 * @param  DoliDB $db          Database handler
 * @param  string $action      'timbrado' o 'cancelacion'
 * @param  string $invoiceRef  Referencia de la factura (puede ser vacio si no se pudo determinar)
 * @param  string $errmsg      Mensaje de error a registrar
 * @param  int    $fkFacture   Id de la factura si se conoce, 0 si no
 * @return void
 */
function cfdiLogError($db, $action, $invoiceRef, $errmsg, $fkFacture = 0)
{
	global $conf, $user;

	$sql = "INSERT INTO ".MAIN_DB_PREFIX."kshcfdierrorlog";
	$sql .= " (entity, datec, action, invoice_ref, fk_facture, fk_user, errmsg)";
	$sql .= " VALUES (";
	$sql .= (int) $conf->entity;
	$sql .= ", '".$db->idate(dol_now())."'";
	$sql .= ", '".$db->escape($action)."'";
	$sql .= ", ".(empty($invoiceRef) ? "NULL" : "'".$db->escape($invoiceRef)."'");
	$sql .= ", ".((int) $fkFacture > 0 ? (int) $fkFacture : "NULL");
	$sql .= ", ".(!empty($user->id) ? (int) $user->id : "NULL");
	$sql .= ", '".$db->escape($errmsg)."'";
	$sql .= ")";

	$db->query($sql);
}

/**
 * Extrafields que el módulo necesita para timbrar. Única fuente: la usan modCfdi::init()
 * para crearlos/corregirlos y admin/test.php para el diagnóstico.
 *
 * @return array<string, array<string, array{label:string, type:string, pos:int, size:int, param:array|string, default:string, list:int, alwayseditable:int}>>
 */
function cfdiRequiredExtrafields()
{
	return array(
		'facture' => array(
			// Cadena original: dato interno, no debe aparecer como campo editable al usuario.
			'stampcfdi' => array('label' => 'ExtrafieldCfdiOriginalString', 'type' => 'text', 'pos' => 100, 'size' => 8000, 'param' => '', 'default' => '', 'list' => 0, 'alwayseditable' => 1),
			// Almacen (rowid) usado para el movimiento de stock al cancelar en el SAT
			'warehouse' => array('label' => 'ExtrafieldCfdiWarehouse', 'type' => 'int', 'pos' => 100, 'size' => 10, 'param' => '', 'default' => '', 'list' => 0, 'alwayseditable' => 1),
			'typerelsat' => array('label' => 'ExtrafieldCfdiRelationType', 'type' => 'sellist', 'pos' => 100, 'size' => 2, 'param' => array('options' => array('kshtyperelsat:description:idsat::' => 'N')), 'default' => '', 'list' => 1, 'alwayseditable' => 0),
			'mofpaymt' => array('label' => 'ExtrafieldCfdiPaymentMethod', 'type' => 'select', 'pos' => 101, 'size' => 1, 'param' => array('options' => array('1' => 'PUE', '2' => 'PPD')), 'default' => '', 'list' => 1, 'alwayseditable' => 0),
			// Uso de CFDI (catalogo SAT en llx_usocfdi)
			'propouse' => array('label' => 'ExtrafieldCfdiPropouse', 'type' => 'sellist', 'pos' => 102, 'size' => 20, 'param' => array('options' => array('usocfdi:Descripcion:usocfdi_id::' => 'N')), 'default' => '', 'list' => 1, 'alwayseditable' => 0),
			'serie' => array('label' => 'ExtrafieldCfdiSerie', 'type' => 'sellist', 'pos' => 103, 'size' => 10, 'param' => array('options' => array('serie:serie:rowid::status=1' => 'N')), 'default' => '', 'list' => 1, 'alwayseditable' => 0),
			'export' => array('label' => 'ExtrafieldCfdiExportacion', 'type' => 'select', 'pos' => 104, 'size' => 2, 'param' => array('options' => array('01' => 'No aplica', '02' => 'Definitiva', '03' => 'Temporal')), 'default' => '01', 'list' => 1, 'alwayseditable' => 0),
			// Factura global (catalogos SAT c_Periodicidad y c_Meses)
			'periodic' => array('label' => 'ExtrafieldCfdiPeriodicity', 'type' => 'select', 'pos' => 105, 'size' => 2, 'param' => array('options' => array(
				'01' => 'Diario', '02' => 'Semanal', '03' => 'Quincenal', '04' => 'Mensual', '05' => 'Bimestral',
			)), 'default' => '', 'list' => 1, 'alwayseditable' => 0),
			'mth' => array('label' => 'ExtrafieldCfdiMonths', 'type' => 'select', 'pos' => 106, 'size' => 2, 'param' => array('options' => array(
				'01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
				'07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
				'13' => 'Enero-Febrero', '14' => 'Marzo-Abril', '15' => 'Mayo-Junio', '16' => 'Julio-Agosto', '17' => 'Septiembre-Octubre', '18' => 'Noviembre-Diciembre',
			)), 'default' => '', 'list' => 1, 'alwayseditable' => 0),
			'peranio' => array('label' => 'ExtrafieldCfdiYear', 'type' => 'int', 'pos' => 107, 'size' => 10, 'param' => '', 'default' => '', 'list' => 1, 'alwayseditable' => 0),
			// UUID del CFDI timbrado: lo llena el timbrado, solo se muestra en ficha y lista
			'uuid' => array('label' => 'ExtrafieldCfdiUuid', 'type' => 'text', 'pos' => 108, 'size' => 2000, 'param' => '', 'default' => '', 'list' => 4, 'alwayseditable' => 0),
		),
		'societe' => array(
			// Uso de CFDI por defecto del cliente; el trigger BILL_CREATE lo copia a la factura
			'propouse' => array('label' => 'ExtrafieldCfdiPropouse', 'type' => 'sellist', 'pos' => 100, 'size' => 20, 'param' => array('options' => array('usocfdi:Descripcion:usocfdi_id::' => 'N')), 'default' => '', 'list' => 1, 'alwayseditable' => 1),
			// Regimen fiscal del receptor (RegimenFiscalReceptor)
			'fiscalreg' => array('label' => 'ExtrafieldCfdiFiscalRegimen', 'type' => 'sellist', 'pos' => 2, 'size' => 10, 'param' => array('options' => array('FiscalRegimen:description:fiscalreg::' => 'N')), 'default' => '', 'list' => 1, 'alwayseditable' => 1),
			// Receptor alterno: excFetch.php toma nombre, RFC, CP y regimen de este tercero si esta lleno
			'invto' => array('label' => 'ExtrafieldCfdiInvoiceTo', 'type' => 'sellist', 'pos' => 3, 'size' => 10, 'param' => array('options' => array('societe:nom:rowid::' => 'N')), 'default' => '', 'list' => 1, 'alwayseditable' => 1),
			'mofpaymt' => array('label' => 'ExtrafieldCfdiPaymentMethod', 'type' => 'select', 'pos' => 100, 'size' => 1, 'param' => array('options' => array('1' => 'PUE', '2' => 'PPD')), 'default' => '', 'list' => 1, 'alwayseditable' => 1),
		),
		'product' => array(
			// ClaveProdServ del SAT; si esta vacio el XML manda 01010101
			'prodservid' => array('label' => 'ExtrafieldCfdiProductService', 'type' => 'varchar', 'pos' => 100, 'size' => 8, 'param' => '', 'default' => '', 'list' => 1, 'alwayseditable' => 1),
			// ClaveUnidad y Unidad salen de c_units (UOMId y code) por el rowid guardado
			'udm' => array('label' => 'ExtrafieldCfdiUnit', 'type' => 'sellist', 'pos' => 100, 'size' => 10, 'param' => array('options' => array('c_units:label:rowid::' => 'N')), 'default' => '', 'list' => 1, 'alwayseditable' => 1),
			'taxobjct' => array('label' => 'ExtrafieldCfdiTaxObject', 'type' => 'select', 'pos' => 100, 'size' => 2, 'param' => array('options' => array('01' => 'No objeto de impuesto', '02' => 'Sí objeto de impuesto', '03' => 'Sí objeto de impuesto y no obligado al desglose')), 'default' => '02', 'list' => 1, 'alwayseditable' => 1),
		),
		'facturedet' => array(
			// ObjetoImp de la linea (lo lee el PDF)
			'taxobjct' => array('label' => 'ExtrafieldCfdiTaxObject', 'type' => 'select', 'pos' => 100, 'size' => 2, 'param' => array('options' => array('01' => 'No objeto de impuesto', '02' => 'Sí objeto de impuesto', '03' => 'Sí objeto de impuesto y no obligado al desglose')), 'default' => '02', 'list' => 1, 'alwayseditable' => 1),
		),
	);
}

/**
 * Columnas que el módulo agrega a tablas de Dolibarr (no son extrafields). Única fuente: la usan
 * modCfdi::init() para crearlas y admin/test.php para el diagnóstico.
 *
 * @return array<string, array<string, string>> tabla (sin prefijo) => columna => definición SQL
 */
function cfdiRequiredColumns()
{
	return array(
		// UUID del complemento de pago
		'paiement' => array(
			'uuid' => 'varchar(40) NULL',
		),
		'paiement_facture' => array(
			'num_parcial' => 'int NULL DEFAULT 1',
			'fk_fac_delete' => 'int NULL DEFAULT NULL',
			'status' => 'tinyint NOT NULL DEFAULT 0',
		),
		// Claves SAT de forma de pago y unidad: los códigos de Dolibarr (VIR, KG...) no son claves SAT.
		'c_paiement' => array(
			'code_sat' => 'varchar(2) NULL',
		),
		'c_units' => array(
			'UOMId' => 'varchar(5) NULL',
		),
	);
}
