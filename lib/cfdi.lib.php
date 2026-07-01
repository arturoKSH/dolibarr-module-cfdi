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
	$head[$h][1] = 'Diagnóstico';
	$head[$h][2] = 'test';
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
 * Lee un .cer (DER o PEM) y devuelve info del certificado o array con error.
 *
 * @param  string $certName Nombre saneado del certificado (ver MAIN_INFO_CFDI_CERT_NAME)
 * @return array
 */
function cfdiParseCert($certName)
{
	$base = DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/cfdi_Cert/'.basename($certName).'/';

	// Intentar PEM primero, luego convertir DER
	$pemFile = $base.$certName.'.cer.pem';
	$derFile = $base.$certName.'.cer';

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
