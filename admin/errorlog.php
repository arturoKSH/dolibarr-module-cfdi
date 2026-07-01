<?php
/* Copyright (C) 2024 KSH
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    cfdi/admin/errorlog.php
 * \ingroup cfdi
 * \brief   Registro de intentos fallidos de timbrado/cancelacion CFDI
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
require_once DOL_DOCUMENT_ROOT."/user/class/user.class.php";
require_once '../lib/cfdi.lib.php';

$langs->loadLangs(array("admin", "cfdi@cfdi"));
$hookmanager->initHooks(array('cfdierrorlog', 'globalsetup'));

if (!$user->admin) {
	accessforbidden();
}

$backtopage = GETPOST('backtopage', 'alpha');

$page_name = "CfdiSetup";
llxHeader('', $langs->trans($page_name), '');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = cfdiAdminPrepareHead();
print dol_get_fiche_head($head, 'errorlog', $langs->trans($page_name), -1, "cfdi@cfdi");

print '<h3>'.$langs->trans('CfdiErrorLogTitle').'</h3>';
print '<p class="opacitymedium">'.$langs->trans('CfdiErrorLogIntro').'</p>';

$maxLines = 200;
$sql = "SELECT rowid, datec, action, invoice_ref, fk_facture, fk_user, errmsg";
$sql .= " FROM ".MAIN_DB_PREFIX."kshcfdierrorlog";
$sql .= " WHERE entity = ".(int) $conf->entity;
$sql .= " ORDER BY datec DESC";
$sql .= " LIMIT ".(int) $maxLines;

$resql = $db->query($sql);

if (!$resql) {
	// Tabla no existe todavia (p.ej. modulo no reactivado desde que se agrego este log)
	print '<p class="opacitymedium">'.$langs->trans('CfdiErrorLogTableMissing').'</p>';
} else {
	$num = $db->num_rows($resql);

	if ($num == 0) {
		print '<p class="opacitymedium">'.$langs->trans('CfdiErrorLogEmpty').'</p>';
	} else {
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td width="140">'.$langs->trans('CfdiErrorLogDateCol').'</td>';
		print '<td width="100">'.$langs->trans('CfdiErrorLogActionCol').'</td>';
		print '<td width="140">'.$langs->trans('CfdiErrorLogInvoiceCol').'</td>';
		print '<td width="100">'.$langs->trans('CfdiErrorLogUserCol').'</td>';
		print '<td>'.$langs->trans('CfdiErrorLogMessageCol').'</td>';
		print '</tr>';

		while ($obj = $db->fetch_object($resql)) {
			$invoiceLink = dol_escape_htmltag($obj->invoice_ref);
			if (!empty($obj->fk_facture)) {
				$invoiceLink = '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $obj->fk_facture.'">'.$invoiceLink.'</a>';
			}

			$userLink = '-';
			if (!empty($obj->fk_user)) {
				$userStatic = new User($db);
				if ($userStatic->fetch($obj->fk_user) > 0) {
					$userLink = $userStatic->getNomUrl(1);
				}
			}

			$actionLabel = ($obj->action === 'timbrado') ? $langs->trans('CfdiStamping') : $langs->trans('CfdiCancellation');

			print '<tr class="oddeven">';
			print '<td>'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';
			print '<td>'.$actionLabel.'</td>';
			print '<td>'.$invoiceLink.'</td>';
			print '<td>'.$userLink.'</td>';
			print '<td style="word-break:break-word;">'.dol_escape_htmltag($obj->errmsg).'</td>';
			print '</tr>';
		}

		print '</table>';
	}

	$db->free($resql);
}

print dol_get_fiche_end();
llxFooter();
$db->close();
