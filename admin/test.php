<?php
/* Copyright (C) 2024 KSH
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    cfdi/admin/test.php
 * \ingroup cfdi
 * \brief   Página de diagnóstico del módulo CFDI
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user, $db;

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/cfdi.lib.php';

$langs->loadLangs(array("admin", "cfdi@cfdi"));
$hookmanager->initHooks(array('cfditest', 'globalsetup'));

if (!$user->admin) {
	accessforbidden();
}

$backtopage = GETPOST('backtopage', 'alpha');
$action     = GETPOST('action', 'aZ09');
$runTest    = ($action === 'run');

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Hace un GET al WSDL y devuelve array con keys: ok (bool), code (int), ms (int), error (string)
 */
function cfdiPingWsdl($url)
{
	if (empty($url)) {
		return array('ok' => false, 'code' => 0, 'ms' => 0, 'error' => 'URL vacía');
	}
	$start = microtime(true);
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Dolibarr-CFDI-Diag/1.0');
	$body = curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err  = curl_error($ch);
	curl_close($ch);
	$ms = (int) round((microtime(true) - $start) * 1000);

	$isWsdl = ($body && (strpos($body, 'wsdl') !== false || strpos($body, 'WSDL') !== false || strpos($body, 'definitions') !== false));
	$ok     = ($code >= 200 && $code < 400 && $isWsdl);

	return array('ok' => $ok, 'code' => $code, 'ms' => $ms, 'error' => $err, 'wsdl' => $isWsdl);
}

// cfdiParseCert() ahora vive en lib/cfdi.lib.php (compartida con admin/setup.php).

// ─── View ────────────────────────────────────────────────────────────────────

$page_name = "CfdiSetup";
llxHeader('', $langs->trans($page_name), '');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = cfdiAdminPrepareHead();
print dol_get_fiche_head($head, 'test', $langs->trans($page_name), -1, "cfdi@cfdi");

print '<h3>'.$langs->trans('CfdiDiagTitle').'</h3>';
print '<p class="opacitymedium">'.$langs->trans('CfdiDiagIntro').'</p>';

// ─── Configuración actual (siempre visible) ──────────────────────────────────

$currentEnv    = !empty($conf->global->CFDI_ENV) ? $conf->global->CFDI_ENV : 'test';
$stampUrlTest  = !empty($conf->global->CFDI_STAMP_URL_TEST)  ? $conf->global->CFDI_STAMP_URL_TEST  : 'https://develop.timbrado.com.mx/wsTimbrado.asmx?WSDL';
$stampUrlProd  = !empty($conf->global->CFDI_STAMP_URL_PROD)  ? $conf->global->CFDI_STAMP_URL_PROD  : 'https://cfdi33.timbrado.com.mx/wsTimbrado.asmx?WSDL';
$cancelUrlTest = !empty($conf->global->CFDI_CANCEL_URL_TEST) ? $conf->global->CFDI_CANCEL_URL_TEST : 'https://develop.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL';
$cancelUrlProd = !empty($conf->global->CFDI_CANCEL_URL_PROD) ? $conf->global->CFDI_CANCEL_URL_PROD : 'https://cfdi.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL';
$activeStamp   = ($currentEnv === 'prod') ? $stampUrlProd   : $stampUrlTest;
$activeCancel  = ($currentEnv === 'prod') ? $cancelUrlProd  : $cancelUrlTest;
$pacUser       = !empty($conf->global->CFDI_PAC_USER) ? dol_escape_htmltag($conf->global->CFDI_PAC_USER) : '<span style="color:red">'.$langs->trans('NotConfigured').'</span>';
$certName      = !empty($conf->global->MAIN_INFO_CFDI_CERT_NAME) ? $conf->global->MAIN_INFO_CFDI_CERT_NAME : '';

$envLabel = ($currentEnv === 'prod')
	? '<span style="color:#c8000a;font-weight:bold">&#9679; '.$langs->trans('CfdiEnvProd').'</span>'
	: '<span style="color:#0063cb;font-weight:bold">&#9679; '.$langs->trans('CfdiEnvTest').'</span>';

print '<h4>'.$langs->trans('CfdiCurrentConfig').'</h4>';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td width="220">'.$langs->trans('CfdiActiveEnv').'</td><td>'.$envLabel.'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('CfdiActiveStampUrl').'</td><td><code>'.dol_escape_htmltag($activeStamp).'</code></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('CfdiActiveCancelUrl').'</td><td><code>'.dol_escape_htmltag($activeCancel).'</code></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('CfdiPacUser').'</td><td>'.$pacUser.'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DigitalCertificate').'</td><td>';
if ($certName) {
	print '<span class="fa fa-check" style="color:green"></span> '.dol_escape_htmltag($certName);
} else {
	print '<span class="fa fa-times" style="color:red"></span> <span style="color:red">'.$langs->trans('NotConfigured').'</span>';
}
print '</td></tr>';
print '</table>';
print '<br>';

// ─── Botón Ejecutar Test ──────────────────────────────────────────────────────

print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="run">';
print '<button type="submit" class="butAction"><span class="fa fa-play"></span> '.$langs->trans('RunDiagnostic').'</button>';
print '</form>';
print '<br>';

// ─── Resultados (solo si se presionó el botón) ────────────────────────────────

if ($runTest) {

	// ── 1. Ping a servicios ────────────────────────────────────────────────

	print '<h4>'.$langs->trans('StampServiceConnectivity').'</h4>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('CfdiServiceCol').'</td>';
	print '<td>URL</td>';
	print '<td class="center" width="80">'.$langs->trans('CfdiStatusCol').'</td>';
	print '<td class="center" width="80">HTTP</td>';
	print '<td class="center" width="80">'.$langs->trans('CfdiTimeCol').'</td>';
	print '</tr>';

	$pingTargets = array(
		$langs->trans('CfdiStamping').' ('.$currentEnv.')' => $activeStamp,
		$langs->trans('CfdiCancellation').' ('.$currentEnv.')' => $activeCancel,
	);

	foreach ($pingTargets as $label => $url) {
		$ping = cfdiPingWsdl($url);

		if ($ping['ok']) {
			$statusIcon = '<span class="fa fa-check" style="color:green"></span> <span style="color:green">'.$langs->trans('CfdiAccessible').'</span>';
		} elseif ($ping['code'] > 0) {
			$statusIcon = '<span class="fa fa-exclamation-triangle" style="color:orange"></span> <span style="color:orange">'.$langs->trans('CfdiRespondsNoWsdl').'</span>';
		} else {
			$statusIcon = '<span class="fa fa-times" style="color:red"></span> <span style="color:red">'.$langs->trans('CfdiNoResponse').'</span>';
		}

		$codeHtml = $ping['code'] ? $ping['code'] : '-';
		$msHtml   = $ping['ms']   ? $ping['ms'].'ms' : '-';
		if (!empty($ping['error'])) {
			$msHtml = '<span style="color:red" title="'.dol_escape_htmltag($ping['error']).'">'.$langs->trans('CfdiErrorCol').'</span>';
		}

		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($label).'</td>';
		print '<td><code style="font-size:11px">'.dol_escape_htmltag($url).'</code></td>';
		print '<td class="center">'.$statusIcon.'</td>';
		print '<td class="center">'.$codeHtml.'</td>';
		print '<td class="center">'.$msHtml.'</td>';
		print '</tr>';
	}

	print '</table>';
	print '<br>';

	// ── 2. Certificado digital ─────────────────────────────────────────────

	print '<h4>'.$langs->trans('CfdiCertStatusTitle').'</h4>';
	print '<table class="noborder centpercent">';

	if (empty($certName)) {
		print '<tr class="oddeven"><td>';
		print '<span class="fa fa-times" style="color:red"></span> <span style="color:red">'.$langs->trans('CfdiNoCertConfigured').'</span>';
		print '</td></tr>';
	} else {
		$cert = cfdiParseCert($certName);

		if (!empty($cert['error'])) {
			print '<tr class="liste_titre"><td>'.$langs->trans('CfdiFieldCol').'</td><td>'.$langs->trans('CfdiValueCol').'</td></tr>';
			print '<tr class="oddeven"><td>'.$langs->trans('CfdiErrorCol').'</td><td><span style="color:red">'.dol_escape_htmltag($cert['error']).'</span></td></tr>';
		} else {
			$daysLeft  = $cert['daysLeft'];
			$expired   = $cert['expired'];

			if ($expired) {
				$certStatusIcon = '<span class="fa fa-times" style="color:red"></span>';
				$daysHtml = '<span style="color:red;font-weight:bold">'.sprintf($langs->trans('CfdiCertExpiredStatus'), abs($daysLeft)).'</span>';
			} elseif ($daysLeft <= 30) {
				$certStatusIcon = '<span class="fa fa-exclamation-triangle" style="color:orange"></span>';
				$daysHtml = '<span style="color:orange;font-weight:bold">'.sprintf($langs->trans('CfdiCertExpiringStatus'), $daysLeft).'</span>';
			} else {
				$certStatusIcon = '<span class="fa fa-check" style="color:green"></span>';
				$daysHtml = '<span style="color:green">'.sprintf($langs->trans('CfdiCertValidStatus'), $daysLeft).'</span>';
			}

			print '<tr class="liste_titre"><td width="220">'.$langs->trans('CfdiFieldCol').'</td><td>'.$langs->trans('CfdiValueCol').'</td></tr>';
			print '<tr class="oddeven"><td>'.$langs->trans('CfdiStatusCol').'</td><td>'.$certStatusIcon.' '.$daysHtml.'</td></tr>';
			print '<tr class="oddeven"><td>'.$langs->trans('CfdiCertHolder').'</td><td>'.dol_escape_htmltag($cert['cn']).'</td></tr>';
			print '<tr class="oddeven"><td>'.$langs->trans('CfdiValidFrom').'</td><td>'.dol_escape_htmltag($cert['validFrom']).'</td></tr>';
			print '<tr class="oddeven"><td>'.$langs->trans('CfdiValidTo').'</td><td>'.dol_escape_htmltag($cert['validTo']).'</td></tr>';
		}
	}

	print '</table>';
	print '<br>';

	// ── 3. Tablas de catálogo ──────────────────────────────────────────────

	$catalogTables = array(
		'llx_usocfdi'                => $langs->trans('CfdiCatalogUsoCfdi'),
		'llx_FiscalRegimen'          => $langs->trans('CfdiCatalogFiscalRegimen'),
		'llx_kshtyperelsat'          => $langs->trans('CfdiCatalogTypeRelSat'),
		'llx_serie'                  => $langs->trans('CfdiCatalogSerie'),
		'llx_kshCancelcfdi'          => $langs->trans('CfdiCatalogCancelaciones'),
		'llx_kshacceptordeclinecfdi' => $langs->trans('CfdiCatalogAceptaciones'),
		'llx_kshcfdirelations'       => $langs->trans('CfdiCatalogRelaciones'),
	);

	print '<h4>'.$langs->trans('CfdiCatalogTablesTitle').'</h4>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('CfdiTableCol').'</td>';
	print '<td>'.$langs->trans('CfdiDescriptionCol').'</td>';
	print '<td class="center" width="80">'.$langs->trans('CfdiExists').'</td>';
	print '<td class="center" width="100">'.$langs->trans('CfdiRecords').'</td>';
	print '</tr>';

	foreach ($catalogTables as $tableName => $desc) {
		$resql  = $db->query("SHOW TABLES LIKE '".$db->escape($tableName)."'");
		$exists = ($resql && $db->num_rows($resql) > 0);

		$count = 0;
		if ($exists) {
			$resCount = $db->query("SELECT COUNT(*) as nb FROM ".$tableName);
			if ($resCount) {
				$row   = $db->fetch_array($resCount);
				$count = (int) $row['nb'];
			}
		}

		$iconExists = $exists
			? '<span class="fa fa-check" style="color:green"></span>'
			: '<span class="fa fa-times" style="color:red"></span>';

		$countHtml = !$exists ? '-'
			: ($count > 0 ? '<span style="color:green">'.$count.'</span>' : '<span style="color:orange">0</span>');

		print '<tr class="oddeven">';
		print '<td><code>'.$tableName.'</code></td>';
		print '<td>'.dol_escape_htmltag($desc).'</td>';
		print '<td class="center">'.$iconExists.'</td>';
		print '<td class="center">'.$countHtml.'</td>';
		print '</tr>';
	}

	print '</table>';
	print '<br>';

	// ── 4. Extrafields en facturas ─────────────────────────────────────────

	print '<h4>'.$langs->trans('CfdiExtrafieldsFacturasTitle').'</h4>';

	$resTable      = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.'facture_extrafields')."'");
	$extraTableExists = ($resTable && $db->num_rows($resTable) > 0);
	$resql         = $db->query("SELECT name, label, type FROM ".MAIN_DB_PREFIX."extrafields WHERE elementtype = 'facture' ORDER BY pos, name");

	if ($resql && $db->num_rows($resql) > 0) {
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('CfdiNameCol').'</td>';
		print '<td>'.$langs->trans('CfdiLabelCol').'</td>';
		print '<td>'.$langs->trans('CfdiTypeCol').'</td>';
		print '<td class="center" width="120">'.$langs->trans('CfdiDbColumn').'</td>';
		print '</tr>';

		while ($obj = $db->fetch_object($resql)) {
			$colExists = false;
			if ($extraTableExists) {
				$resCol    = $db->query("SHOW COLUMNS FROM ".MAIN_DB_PREFIX."facture_extrafields LIKE '".$db->escape($obj->name)."'");
				$colExists = ($resCol && $db->num_rows($resCol) > 0);
			}

			$icon = $colExists
				? '<span class="fa fa-check" style="color:green"></span>'
				: '<span class="fa fa-times" style="color:red"></span>';

			print '<tr class="oddeven">';
			print '<td><code>'.dol_escape_htmltag($obj->name).'</code></td>';
			print '<td>'.dol_escape_htmltag($obj->label).'</td>';
			print '<td>'.dol_escape_htmltag($obj->type).'</td>';
			print '<td class="center">'.$icon.'</td>';
			print '</tr>';
		}

		print '</table>';
	} else {
		print '<p class="opacitymedium">'.$langs->trans('CfdiNoExtrafieldsDefined').'</p>';
	}
}

print dol_get_fiche_end();
llxFooter();
$db->close();
