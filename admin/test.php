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

/*
 * View
 */

$page_name = "CfdiSetup";

llxHeader('', $langs->trans($page_name), '');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = cfdiAdminPrepareHead();
print dol_get_fiche_head($head, 'test', $langs->trans($page_name), -1, "cfdi@cfdi");

print '<h3>Diagnóstico del Módulo CFDI</h3>';
print '<p class="opacitymedium">Verifica que las tablas de catálogo y los extrafields requeridos estén correctamente instalados.</p>';

// ─── Tablas de catálogo ─────────────────────────────────────────────────────

$catalogTables = array(
	'llx_usocfdi'       => 'Usos de CFDI (SAT)',
	'llx_FiscalRegimen' => 'Regímenes Fiscales',
	'llx_kshtyperelsat' => 'Tipos de Relación SAT',
	'llx_serie'         => 'Series de Folio',
);

print '<h4>Tablas de Catálogo</h4>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>Tabla</td>';
print '<td>Descripción</td>';
print '<td class="center" width="80">Existe</td>';
print '<td class="center" width="100">Registros</td>';
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
		? '<span class="fa fa-check" style="color:green" title="Existe"></span>'
		: '<span class="fa fa-times" style="color:red" title="No encontrada"></span>';

	$countHtml = !$exists ? '-'
		: ($count > 0 ? '<span style="color:green">'.$count.'</span>' : '<span style="color:red">0</span>');

	print '<tr class="oddeven">';
	print '<td><code>'.$tableName.'</code></td>';
	print '<td>'.dol_escape_htmltag($desc).'</td>';
	print '<td class="center">'.$iconExists.'</td>';
	print '<td class="center">'.$countHtml.'</td>';
	print '</tr>';
}

print '</table>';
print '<br>';

// ─── Extrafields en facturas ────────────────────────────────────────────────

print '<h4>Extrafields en Facturas</h4>';

// Check if llx_facture_extrafields exists before querying columns
$resTable = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.'facture_extrafields')."'");
$extraTableExists = ($resTable && $db->num_rows($resTable) > 0);

$resql = $db->query("SELECT name, label, type FROM ".MAIN_DB_PREFIX."extrafields WHERE elementtype = 'facture' ORDER BY pos, name");

if ($resql && $db->num_rows($resql) > 0) {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>Nombre</td>';
	print '<td>Etiqueta</td>';
	print '<td>Tipo</td>';
	print '<td class="center" width="120">Columna en BD</td>';
	print '</tr>';

	while ($obj = $db->fetch_object($resql)) {
		$colExists = false;
		if ($extraTableExists) {
			$resCol   = $db->query("SHOW COLUMNS FROM ".MAIN_DB_PREFIX."facture_extrafields LIKE '".$db->escape($obj->name)."'");
			$colExists = ($resCol && $db->num_rows($resCol) > 0);
		}

		$icon = $colExists
			? '<span class="fa fa-check" style="color:green" title="Columna presente"></span>'
			: '<span class="fa fa-times" style="color:red" title="Columna ausente"></span>';

		print '<tr class="oddeven">';
		print '<td><code>'.dol_escape_htmltag($obj->name).'</code></td>';
		print '<td>'.dol_escape_htmltag($obj->label).'</td>';
		print '<td>'.dol_escape_htmltag($obj->type).'</td>';
		print '<td class="center">'.$icon.'</td>';
		print '</tr>';
	}

	print '</table>';
} else {
	print '<p class="opacitymedium">No hay extrafields definidos para facturas en llx_extrafields.</p>';
}

print '<br>';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'">&#8635; Actualizar</a>';

print dol_get_fiche_end();

llxFooter();
$db->close();
