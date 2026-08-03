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

	$head[$h][0] = dol_buildpath("/cfdi/admin/setup.php", 1);
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

	$head[$h][0] = dol_buildpath("/cfdi/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	$head[$h][0] = dol_buildpath("/cfdi/admin/test.php", 1);
	$head[$h][1] = $langs->trans('CfdiDiagTitle');
	$head[$h][2] = 'test';
	$h++;

	$head[$h][0] = dol_buildpath("/cfdi/admin/errorlog.php", 1);
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
			$pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
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
