<?php
/* Copyright (C) 2004		Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2018	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2004		Benoit Mortier			<benoit.mortier@opensides.be>
 * Copyright (C) 2005-2017	Regis Houssin			<regis.houssin@inodbox.com>
 * Copyright (C) 2010-2016	Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2011-2019	Philippe Grand			<philippe.grand@atoo-net.com>
 * Copyright (C) 2011		Remy Younes				<ryounes@gmail.com>
 * Copyright (C) 2012-2015	Marcos García			<marcosgdf@gmail.com>
 * Copyright (C) 2012		Christophe Battarel		<christophe.battarel@ltairis.fr>
 * Copyright (C) 2011-2016	Alexandre Spangaro		<aspangaro@open-dsi.fr>
 * Copyright (C) 2015		Ferran Marcet			<fmarcet@2byte.es>
 * Copyright (C) 2016		Raphaël Doursenaud		<rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2019       Frédéric France         <frederic.france@netlogic.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	    \file       htdocs/admin/dict.php
 *		\ingroup    setup
 *		\brief      Page to administer data tables
 */

require '../../main.inc.php';
// Class
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formadmin.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmargin.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/accounting.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formaccounting.class.php';

require_once DOL_DOCUMENT_ROOT . '/core/lib/accounting.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT . '/accountancy/class/accountancyexport.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formaccounting.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
// Load translation files required by the page
$langs->loadLangs(array("accountancy"));

$page = GETPOST("page", 'int');
$sortorder = GETPOST("sortorder", 'alpha');
$sortfield = GETPOST("sortfield", 'alpha');
$action = GETPOST('action', 'aZ09');

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOST('page', 'int');
$nivel = GETPOST('level', 'array');
if (empty($page) || $page == -1 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha') || (empty($toselect) && $massaction === '0')) {
    $page = 0;
}     // If $page is not defined, or '' or -1 or if we click on clear filters or if we select empty mass action
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
//if (! $sortfield) $sortfield="p.date_fin";
//if (! $sortorder) $sortorder="DESC";


$search_date_start = dol_mktime(0, 0, 0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
$search_date_end = dol_mktime(23, 59, 59, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

$search_accountancy_code_start = GETPOST('search_accountancy_code_start', 'alpha');
if ($search_accountancy_code_start == -1) {
    $search_accountancy_code_start = '';
}
$search_accountancy_code_end = GETPOST('search_accountancy_code_end', 'alpha');
if ($search_accountancy_code_end == -1) {
    $search_accountancy_code_end = '';
}

$object = new BookKeeping($db);

$formaccounting = new FormAccounting($db);
$formother = new FormOther($db);
$form = new Form($db);

if (empty($search_date_start) && !GETPOSTISSET('formfilteraction')) {
    $sql = "SELECT date_start, date_end from " . MAIN_DB_PREFIX . "accounting_fiscalyear ";
    $sql .= " where date_start < '" . $db->idate(dol_now()) . "' and date_end > '" . $db->idate(dol_now()) . "'";
    $sql .= $db->plimit(1);
    $res = $db->query($sql);
    if ($res->num_rows > 0) {
        $fiscalYear = $db->fetch_object($res);
        $search_date_start = strtotime($fiscalYear->date_start);
        $search_date_end = strtotime($fiscalYear->date_end);
    } else {
        $month_start = ($conf->global->SOCIETE_FISCAL_MONTH_START ? ($conf->global->SOCIETE_FISCAL_MONTH_START) : 1);
        $year_start = dol_print_date(dol_now(), '%Y');
        $year_end = $year_start + 1;
        $month_end = $month_start - 1;
        if ($month_end < 1) {
            $month_end = 12;
            $year_end--;
        }
        $search_date_start = dol_mktime(0, 0, 0, $month_start, 1, $year_start);
        $search_date_end = dol_get_last_day($year_end, $month_end);
    }
}
if ($sortorder == "")
    $sortorder = "ASC";
if ($sortfield == "")
    $sortfield = "t.numero_compte";


$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"])
    $param .= '&contextpage=' . urlencode($contextpage);
if ($limit > 0 && $limit != $conf->liste_limit)
    $param .= '&limit=' . urlencode($limit);

$filter = array();
if (!empty($search_date_start)) {
    $filter['t.doc_date>='] = $search_date_start;
    $param .= '&amp;date_startmonth=' . GETPOST('date_startmonth', 'int') . '&amp;date_startday=' . GETPOST('date_startday', 'int') . '&amp;date_startyear=' . GETPOST('date_startyear', 'int');
}
if (!empty($search_date_end)) {
    $filter['t.doc_date<='] = $search_date_end;
    $param .= '&amp;date_endmonth=' . GETPOST('date_endmonth', 'int') . '&amp;date_endday=' . GETPOST('date_endday', 'int') . '&amp;date_endyear=' . GETPOST('date_endyear', 'int');
}
if (!empty($search_accountancy_code_start)) {
    $filter['t.numero_compte>='] = $search_accountancy_code_start;
    $param .= '&amp;search_accountancy_code_start=' . $search_accountancy_code_start;
}
if (!empty($search_accountancy_code_end)) {
    $filter['t.numero_compte<='] = $search_accountancy_code_end;
    $param .= '&amp;search_accountancy_code_end=' . $search_accountancy_code_end;
}
/*
 * Actions
 */

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
{
    $search_accountancy_code_start = '';
    $search_accountancy_code_end = '';
    $search_date_start = '';
    $search_date_end = '';
    $filter = array();
}

if ($action == 'pdfReg') {
    $page = GETPOST('page');
    $niveles = ['Nivel 1 ', 'Nievel 2', 'Nievel 3'];
    $formquestion = array(
        array('type' => 'select', 'name' => 'level', 'label' => $langs->trans("Nivel") . ' ', 'values' => $niveles, 'default' => ''),
    );
    $url = '';
    $url .= '&date_startmonth=' . GETPOST('date_startmonth', 'int') . '&date_startday=' . GETPOST('date_startday', 'int') . '&date_startyear=' . GETPOST('date_startyear', 'int');
    $url .= '&date_endmonth=' . GETPOST('date_endmonth', 'int') . '&date_endday=' . GETPOST('date_endday', 'int') . '&date_endyear=' . GETPOST('date_endyear', 'int');
    $url .= '&date_start=' . GETPOST('date_start');
    $url .= '&date_end=' . GETPOST('date_end');
    $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $id . $url, 'Niveles de cuenta', 'Seleccionar el nivel de cuenta solicitado', 'pdfReg1', $formquestion, '', 1, 300);
}
if ($action == 'pdfReg1') {
    // echo 'aaaaaa';
    ini_set('memory_limit', '-1'); // Aumentar el límite de memoria a 256 MB (ajusta según tus necesidades)
    set_time_limit(0);
    $carpeta = DOL_DATA_ROOT . "/facture/" . $reffobj[0]['ref'];
    include (DOL_DOCUMENT_ROOT . '/custom/pdfs_docs/pdf_MOD_THINK_BCOMP.modules.php');
    $db->begin();
    $nivel = GETPOST('level');
    // echo ' / / / / / / ' . $nivel . ' / / / / / / / / / ';
    $outputlangs = $langs;
    $pdfP = new pdf_MOD_THINK_BCOMP($db);
    if (!empty(GETPOST('date_start') && !empty(GETPOST('date_end')))) {
        $pdfP->datestart = substr(GETPOST('date_start'), 6, 4) . "-" . substr(GETPOST('date_start'), 3, 2) . "-" . substr(GETPOST('date_start'), 0, 2);
        $pdfP->dateend = substr(GETPOST('date_end'), 6, 4) . "-" . substr(GETPOST('date_end'), 3, 2) . "-" . substr(GETPOST('date_end'), 0, 2);

        $pdfP->write_file($object, $outputlangs);
        setEventMessages($langs->trans('Se generó correctamente.'), '', 'mesgs');

    } else {
        setEventMessages($langs->trans("Por favor, complete ambos campos de fechay asegurese de presionar el botón de búsqueda."), null, 'warnings');
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'pdfBalanceGeneral') {
    $carpeta = DOL_DATA_ROOT . "/facture/" . $reffobj[0]['ref'];
    include (DOL_DOCUMENT_ROOT . '/custom/pdfs_docs/pdf_BALANCE_GENE.modules.php');
    $db->begin();

    $outputlangs = $langs;
    $pdfP = new pdf_BALANCE_GENE($db);
    if (!empty(GETPOST('date_start') && !empty(GETPOST('date_end')))) {
        $pdfP->datestart = substr(GETPOST('date_start'), 6, 4) . "-" . substr(GETPOST('date_start'), 3, 2) . "-" . substr(GETPOST('date_start'), 0, 2);
        $pdfP->dateend = substr(GETPOST('date_end'), 6, 4) . "-" . substr(GETPOST('date_end'), 3, 2) . "-" . substr(GETPOST('date_end'), 0, 2);

        $pdfP->write_file($object, $outputlangs);
        setEventMessages($langs->trans('Se generó correctamente.'), '', 'mesgs');

    } else {
        setEventMessages($langs->trans("Por favor, complete ambos campos de fechay asegurese de presionar el botón de búsqueda."), null, 'warnings');
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


if ($action == 'pdfAuxiliares') {
    $carpeta = DOL_DATA_ROOT . "/facture/" . $reffobj[0]['ref'];
    include (DOL_DOCUMENT_ROOT . '/custom/pdfs_docs/pdf_MOD_THINK_REPOR_AUX.modules.php');
    $db->begin();

    $outputlangs = $langs;
    $pdfP = new pdf_MOD_THINK_REPOR_AUX($db);
    if (!empty(GETPOST('date_start') && !empty(GETPOST('date_end')))) {

        $pdfP->datestart = substr(GETPOST('date_start'), 6, 4) . "-" . substr(GETPOST('date_start'), 3, 2) . "-" . substr(GETPOST('date_start'), 0, 2);
        $pdfP->dateend = substr(GETPOST('date_end'), 6, 4) . "-" . substr(GETPOST('date_end'), 3, 2) . "-" . substr(GETPOST('date_end'), 0, 2);
        $pdfP->search_accountancy_code_end = GETPOST('search_accountancy_code_end');
        $pdfP->search_accountancy_code_start = GETPOST('search_accountancy_code_start');

        $pdfP->write_file($object, $outputlangs);
        setEventMessages($langs->trans('Se generó correctamente.'), '', 'mesgs');

    } else {
        setEventMessages($langs->trans("Por favor, complete ambos campos de fechay asegurese de presionar el botón de búsqueda."), null, 'warnings');
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


if ($action == 'pdfDiarioG') {
    ini_set('memory_limit', '-1'); // Aumentar el límite de memoria a 256 MB (ajusta según tus necesidades)
    set_time_limit(0);
    $carpeta = DOL_DATA_ROOT . "/facture/" . $reffobj[0]['ref'];
    include (DOL_DOCUMENT_ROOT . '/custom/pdfs_docs/pdf_DiarioGen.modules.php');
    $db->begin();

    $outputlangs = $langs;
    $pdfP = new pdf_DiarioGen($db);
    if (!empty(GETPOST('date_start') && !empty(GETPOST('date_end')))) {
        $pdfP->datestart = substr(GETPOST('date_start'), 6, 4) . "-" . substr(GETPOST('date_start'), 3, 2) . "-" . substr(GETPOST('date_start'), 0, 2);
        $pdfP->dateend = substr(GETPOST('date_end'), 6, 4) . "-" . substr(GETPOST('date_end'), 3, 2) . "-" . substr(GETPOST('date_end'), 0, 2);

        $pdfP->write_file($object, $outputlangs);
        setEventMessages($langs->trans('Se generó correctamente.'), '', 'mesgs');

    } else {
        setEventMessages($langs->trans("Por favor, complete ambos campos de fechay asegurese de presionar el botón de búsqueda."), null, 'warnings');
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
if ($action == 'pdfEstadoRes') {

    require_once DOL_DOCUMENT_ROOT . '/custom/pdfs_docs/pdf_MOD_EstadoRes.modules.php';
    $db->begin();
    $outputlangs = $langs;
    $pdfP = new pdf_EstadoRes($db);
    if (!empty(GETPOST('date_start') && !empty(GETPOST('date_end')))) {
        $pdfP->datestart = substr(GETPOST('date_start'), 6, 4) . "-" . substr(GETPOST('date_start'), 3, 2) . "-" . substr(GETPOST('date_start'), 0, 2);
        $pdfP->dateend = substr(GETPOST('date_end'), 6, 4) . "-" . substr(GETPOST('date_end'), 3, 2) . "-" . substr(GETPOST('date_end'), 0, 2);
        $pdfP->write_file($object, $outputlangs);
        setEventMessages($langs->trans('Se generó correctamente.'), '', 'mesgs');
    } else {
        setEventMessages($langs->trans("Por favor, complete ambos campos de fechay asegurese de presionar el botón de búsqueda."), null, 'warnings');
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


if ($action == 'pdfLibroMayor') {

    require_once DOL_DOCUMENT_ROOT . '/custom/pdfs_docs/pdf_LIBRO_MAYOR.modules.php';

    $db->begin();

    $outputlangs = $langs;
    $pdfP = new pdf_LIBRO_MAYOR($db);
    if (!empty(GETPOST('date_start') && !empty(GETPOST('date_end')))) {
        $pdfP->datestart = substr(GETPOST('date_start'), 6, 4) . "-" . substr(GETPOST('date_start'), 3, 2) . "-" . substr(GETPOST('date_start'), 0, 2);
        $pdfP->dateend = substr(GETPOST('date_end'), 6, 4) . "-" . substr(GETPOST('date_end'), 3, 2) . "-" . substr(GETPOST('date_end'), 0, 2);

        $pdfP->write_file($object, $outputlangs);
        setEventMessages($langs->trans('Se generó correctamente.'), '', 'mesgs');
    } else {
        setEventMessages($langs->trans("Por favor, complete ambos campos de fechay asegurese de presionar el botón de búsqueda."), null, 'warnings');
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;

}

if ($action == 'pdfLibroMayMonth') {

    require_once DOL_DOCUMENT_ROOT . '/custom/pdfs_docs/pdf_LIBRO_MAY_MONTH.modules.php';

    $db->begin();

    $outputlangs = $langs;
    $pdfP = new pdf_LIBRO_MAY_MONTH($db);
    if (!empty(GETPOST('date_start') && !empty(GETPOST('date_end')))) {
        $pdfP->datestart = substr(GETPOST('date_start'), 6, 4) . "-" . substr(GETPOST('date_start'), 3, 2) . "-" . substr(GETPOST('date_start'), 0, 2);
        $pdfP->dateend = substr(GETPOST('date_end'), 6, 4) . "-" . substr(GETPOST('date_end'), 3, 2) . "-" . substr(GETPOST('date_end'), 0, 2);
        $pdfP->write_file($object, $outputlangs);
        setEventMessages($langs->trans('Se generó correctamente.'), '', 'mesgs');
    } else {
        setEventMessages($langs->trans("Por favor, complete ambos campos de fechay asegurese de presionar el botón de búsqueda."), null, 'warnings');
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;

}

/*
 * View
 */

require 'functionsextcustom.php';

$form = new Form($db);
$formadmin = new FormAdmin($db);

$title = "Balanza de comprobación";

llxHeader('', $title);
print $formconfirm;

$linkback = '';
if ($id) {
    $title .= ' - ' . $langs->trans($tablib[$id]);
    $linkback = '<a href="' . $_SERVER['PHP_SELF'] . '">' . $langs->trans("BackToDictionaryList") . '</a>';
}
$titlepicto = 'title_setup';
if ($id == 10 && GETPOST('from') == 'accountancy') {
    $title = $langs->trans("MenuVatAccounts");
    $titlepicto = 'accountancy';
}
if ($id == 7 && GETPOST('from') == 'accountancy') {
    $title = $langs->trans("MenuTaxAccounts");
    $titlepicto = 'accountancy';
}

print load_fiche_titre($title, $linkback, $titlepicto);
print '

<!-- Popper.JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"
    integrity="sha384-cs/chFZiN24E4KMATLdqdvsezGxaGsi4hLGOzlXwp5UZB1LY//20VyM2taTB4QvJ"
    crossorigin="anonymous"></script>
<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"
    integrity="sha384-uefMccjFJAIv6A+rW+L4AHf99KvxDjWSu1z9VI8SKNVmz4sk7buKt/6v9KI65qnm"
    crossorigin="anonymous"></script>
<!-- jQuery Custom Scroller CDN -->
<script
    src="https://cdnjs.cloudflare.com/ajax/libs/malihu-custom-scrollbar-plugin/3.1.5/jquery.mCustomScrollbar.concat.min.js"></script>

<script src="assets\js\wrapper.js"></script>';

print '<link rel="stylesheet" href="assets\css\wrapperr.css">
    <link rel="stylesheet" href="assets\css\bootstrap.css">';
// List
$nbtotalofrecords = '';

print '<form method="POST" id="searchFormList" action="' . $_SERVER["PHP_SELF"] . '">';
if ($optioncss != '')
    print '<input type="hidden" name="optioncss" value="' . $optioncss . '">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" id="action" value="list">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
print '<input type="hidden" name="page" value="' . $page . '">';

print '<script type="text/javascript" language="javascript">
jQuery(document).ready(function() {
    jQuery("#exportcsvbutton").click(function() {
        event.preventDefault();
        console.log("Set action to export_csv");
        jQuery("#action").val("export_csv");
        jQuery("#searchFormList").submit();
        jQuery("#action").val("list");
    });
});
</script>';

$formaccounting = new FormAccounting($db); ///dvn
$moreforfilter1 = '';
$param = '';
$filter = array();

// 

$moreforfilter1 .= '<div class="divsearchfield">';
$moreforfilter1 .= $langs->trans('AccountAccounting') . ': ';
$moreforfilter1 .= '<div class="nowrap inline-block">';
$moreforfilter1 .= $formaccounting->select_account($search_accountancy_code_start, 'search_accountancy_code_start', $langs->trans('From'), array(), 1, 1, 'maxwidth300');
$moreforfilter1 .= ' ';
$moreforfilter1 .= $formaccounting->select_account($search_accountancy_code_end, 'search_accountancy_code_end', $langs->trans('to'), array(), 1, 1, 'maxwidth300');
$moreforfilter1 .= '</div>';
$moreforfilter1 .= '</div>';
//dvn





$moreforfilter = '';

$moreforfilter .= '<div class="divsearchfield">';
$moreforfilter .= $langs->trans('DateStart') . ': ';
$moreforfilter .= $form->selectDate($search_date_start ? $search_date_start : -1, 'date_start', 0, 0, 1, '', 1, 0);
$moreforfilter .= $langs->trans('DateEnd') . ': ';
$moreforfilter .= $form->selectDate($search_date_end ? $search_date_end : -1, 'date_end', 0, 0, 1, '', 1, 0);
$moreforfilter .= '</div>';



if (!empty($moreforfilter)) {
    print '<div class="liste_titre liste_titre_bydiv centpercent">';
    print $moreforfilter;
    $parameters = array();
    $reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters); // Note that $action and $object may have been modified by hook
    print $hookmanager->resPrint;
    print '</div>';
}



print '<table class="liste ' . ($moreforfilter ? "listwithfilterbefore" : "") . '">';

/* print '<td class="liste_titre" colspan="5">';
print '</td>'; */

print '<tr class="liste_titre">';
print '<td class="liste_titre">';
print $moreforfilter1;///dvn
$searchpicto = $form->showFilterAndCheckAddButtons(0);
print $searchpicto;
print '</td>';
print '<td class="right"></td>';
print '<td class="right"></td>';
print '<td class="right"></td>';
print '<td class="right"></td>';
print '<td class="right"></td>';
print '<td class="right"></td>';
print '<td class="right"></td>';
print '</tr>';
print '<tr class="liste_titre">';

print_liste_field_titre("Cuenta Contable", '', "", "", '', "", '', '');
print_liste_field_titre("", '', "", "", '', "", '', '');
print_liste_field_titre("Descripcion", '', "", "", '', "", '', '');
print_liste_field_titre("Saldo Inicial", '', "", "", '', 'class="right"', '', '');
print_liste_field_titre("Debe", '', "", "", '', 'class="right"', '', '');
print_liste_field_titre("Haber", '', "", "", '', 'class="right"', '', '');
print_liste_field_titre("Saldo Final", '', "", '', "", 'class="right"', '', '');
print_liste_field_titre("", '', "", '', "", 'class="right"', '', '');
print "</tr>\n";


$objectlines = new BookKeeping($db);
$param = '';
$url = '';
$filter = array();


$total_debit = 0;
$total_credit = 0;
$total_sous = 0;
$Saldo_Iniciall = 0;

$datest = substr(GETPOST('date_start'), 6, 4) . "-" . substr(GETPOST('date_start'), 3, 2) . "-" . substr(GETPOST('date_start'), 0, 2);
$dateen = substr(GETPOST('date_end'), 6, 4) . "-" . substr(GETPOST('date_end'), 3, 2) . "-" . substr(GETPOST('date_end'), 0, 2);
if (!empty($datest)) {
    $filter['t.doc_date>='] = $datest;
    $param .= '&search_date_startmonth=' . $search_date_startmonth . '&search_date_startday=' . $search_date_startday . '&search_date_startyear=' . $search_date_startyear;
    $url .= '&date_start=' . GETPOST('date_start');
}
if (!empty($dateen)) {
    $filter['t.doc_date<='] = $dateen;
    $param .= '&search_date_endmonth=' . $search_date_endmonth . '&search_date_endday=' . $search_date_endday . '&search_date_endyear=' . $search_date_endyear;
    $url .= '&date_end=' . GETPOST('date_end');
}
if (!empty($search_accountancy_code_start)) {
    $filter['t.numero_compte>='] = $search_accountancy_code_start;
    $param .= '&amp;search_accountancy_code_start=' . $search_accountancy_code_start;
    $url .= '&search_accountancy_code_start=' . $search_accountancy_code_start;

}
if (!empty($search_accountancy_code_end)) {
    $filter['t.numero_compte<='] = $search_accountancy_code_end;
    $param .= '&amp;search_accountancy_code_end=' . $search_accountancy_code_end;
    $url .= '&search_accountancy_code_end=' . $search_accountancy_code_end;
}

if (!empty($search_accountancy_code_end) && !empty($search_accountancy_code_start)) {
    $ssql = "SELECT account_number FROM (SELECT * FROM llx_accounting_account a 
                    WHERE a.fk_pcg_version = 'CrasaOficial' and account_parent IS NULL 
            UNION
            SELECT * FROM llx_accounting_account a 
                    WHERE a.fk_pcg_version = 'CrasaOficial' and account_parent IS NOT NULL ) asd
                    WHERE account_number BETWEEN '" . $search_accountancy_code_start . "' AND '" . $search_accountancy_code_end . "'
                    ORDER BY account_number";
    $ressql = $db->query($ssql);
    $accoun = $ressql->fetch_all(MYSQLI_ASSOC);
    $accounts = array();

    foreach ($accoun as $va) {
        $accounts[] = $va['account_number'];
    }
}

$result = dad($db, $search_accountancy_code_start, $search_accountancy_code_end);///dvn
if (!empty($result)) {
    $contPrin = 0;
    $saldoInFinallTT = 0;
    $dfinallT = 0;
    $hfinallT = 0;
    $finalFFT = 0;
    foreach ($result as $key => $value) {
        if (empty($accounts) || in_array($value['NumCta'], $accounts)) {

            $conp = "padre_" . $contPrin;
            $debe = 0;
            $haber = 0;
            $total = 0;
            $deb = 0;
            $hab = 0;
            $sal = 0;
            $Saldo_Inicial = 0;
            $Saldo_Inicialh = 0;
            $TParent = array();
            $tot = array();


            //Proccess
            if (!empty(GETPOST('date_start')) && !empty(GETPOST('date_end'))) {
                $Vtree = treedate($db, $value['NumCta'], '', $datest, $dateen);
                $TParent = totaldate($db, $value['NumCta'], $datest, $dateen);
                //  echo $value['NumCta'].'<br>';
            } elseif (!empty($moreforfilter)) {
                $Vtree = tree($db, $value['NumCta'], '');
                $TParent = total($db, $value['NumCta']);
            } else {
                $Vtree = tree($db, $value['NumCta'], '');
                $TParent = total($db, $value['NumCta']);
            }

            if (!empty($Vtree)) {
                foreach ($Vtree as $key => $value2) {
                    $exist = false;
                    $keyref = null;
                    $saldoInFinal = 0;

                    // Busca en $tot para saber si tiene registros de jefe
                    foreach ($tot as $key2 => $valRef) {
                        if ($valRef['Parent'] == $value2['Parent']) {
                            $exist = true;
                            $keyref = $key2;
                            break;
                        }
                    }

                    if ($exist) {
                        //   echo  $value2['Debe'].'+<br>';
                        $tot[$keyref]['SDebe'] += $value2['Debe'];
                        $tot[$keyref]['SHaber'] += $value2['Haber'];
                        $tot[$keyref]['SSaldo'] += $value2['SFin'];
                        $tot[$keyref]["Saldo_Inicial"] += $value2['Saldo_Inicial'];
                    } else {
                        // Es padre

                        $tot[] = [
                            "Parent" => $value2['Parent'],
                            "SDebe" => $value2['Debe'] + $TParent[$key]['Debe'],
                            "SHaber" => $value2['Haber'] + $TParent[$key]['Haber'],
                            "SSaldo" => $value2['SFin'] + ($TParent[$key]['Debe'] - $TParent[$key]['Haber']),
                            "Saldo_Inicial" => $value2['Saldo_Inicial']
                        ];
                    }
                }

                foreach ($tot as $key => $totvalue) {

                    $debe = $debe + $totvalue['SDebe'];
                    // echo  $debe.'<br>';
                    $haber = $haber + $totvalue['SHaber'];
                    // $total = $total + $totvalue['SSaldo'];
                    $total = $debe - $haber;
                    $Saldo_Inicial = $Saldo_Inicial + $totvalue['Saldo_Inicial'];
                }


                // Comptabilise le sous-total
                $total_debit = $total_debit + $debe;
                // echo  $total_debit.'<br>';
                $total_credit = $total_credit + $haber;
                $total_sous = $total_sous + $total;
                $Saldo_Iniciall = $Saldo_Iniciall + $Saldo_Inicial;
            }


            if (!empty(GETPOST('date_start')) && !empty(GETPOST('date_end'))) {
                $Vtree = treedate($db, $value['NumCta'], '', $datest, $dateen);
            } else {
                $Vtree = tree($db, $value['NumCta'], '');
            }
            if (!empty($Vtree)) {
                $conth = 0;
                $dfinall = 0;
                $hfinall = 0;
                $finalFF = 0;
                $saldoInFinall = 0;
                foreach ($Vtree as $key => $value2) {

                    $cont = $contPrin . '_' . $conth;
                    $exist = '';
                    $keyref = '';

                    foreach ($Vtree as $key2 => $valRef) {
                        if ($valRef['Index'] == $value2['Index']) {
                            $exist = 'true';
                            $keyref = $key2;
                        }
                    }
                    $dfinallI = 0;
                    $hfinallI = 0;
                    $finalFFI = 0;
                    $dfinallD = 0;
                    $hfinallD = 0;
                    $finalFFD = 0;
                    $salRess = 0;
                    $saldoInFinallI = 0;
                    $saldoInFinallD = 0;

                    if (!empty($exist) && $exist == 'true') {

                        // $queryN = " select a.account_number, a.label labelh, b.account_number padre, b.label,format(ifnull(c.debe,0.00),2) debe , ";
                        // $queryN.= " format(ifnull(c.Haber,0.00),2) haber,format(ifnull(c.debe,0.00)-ifnull(c.Haber,0.00),2) saldo ";
                        // $queryN.= " from    ".MAIN_DB_PREFIX."accounting_account a left join ".MAIN_DB_PREFIX."accounting_account b ";
                        // $queryN.= " on  a.fk_pcg_version = b.fk_pcg_version  ";
                        // $queryN.= " and     a.account_parent = b.rowid left join (SELECT subledger_account  NumCta,sum(debit) as Debe, sum(credit) as Haber   ";
                        //  if (!empty(GETPOST('date_start')) && !empty(GETPOST('date_end'))) {
                        //     $queryN.= "      FROM ".MAIN_DB_PREFIX."accounting_bookkeeping  where doc_date between '".$datest."' and '".$dateen."' ";
                        // } else {
                        //     $queryN.= "      FROM ".MAIN_DB_PREFIX."accounting_bookkeeping  ";
                        // }

                        // $queryN.= "     group by numero_compte ) c ";
                        // $queryN.= " on  a.account_number=c.NumCta ";
                        // $queryN.= " where   b.account_number=".$value2['Index'];
                        // $resN = $db->query($queryN);
                        // $resultN = $resN->fetch_all(MYSQLI_ASSOC);
                        // $resultN=getThirdAccount($db,$value2['Index'],GETPOST('date_start'),GETPOST('date_end'));
                        $resultN = getThirdAccount($db, $value2['Index'], GETPOST('date_start'), GETPOST('date_end'));
                        $deb = 0;
                        $hab = 0;
                        $sal = 0;
                        $Saldo_Inicialh = 0;
                        foreach ($resultN as $keyN => $valueN) {
                            if (empty($accounts) || in_array($valueN['account_number'], $accounts)) {

                                $deb = $deb + str_replace(",", "", $valueN['debe']);
                                $hab = $hab + str_replace(",", "", $valueN['haber']);
                                $sal = $sal + str_replace(",", "", $valueN['saldo']);
                                $Saldo_Inicialh = $Saldo_Inicialh + str_replace(",", "", $valueN['Saldo_Inicial']);
                            }
                        }
                        $total_debit = $total_debit + $deb;
                        $verifi = lastCount($db, $value2['Index']);
                        //    echo $value2['Index']."--";

                        if (!empty($verifi[0]['account_number'])) {
                            $saldoInFinallI = $Saldo_Inicialh;
                            $saldoInFinallff = ($Saldo_Inicialh + $deb) - $hab;///dvn
                            $dfinallI = $deb;
                            $hfinallI = $hab;
                            $finalFFI = ($Saldo_Inicialh + $deb) - ($hab);////dvn
                        } else {
                            //  echo $verifi[0]['account_number']."holaaa";

                            $salRess = salIn($db, $value2['Index'], $datest);
                            $saldoInFinallD = ($salRess[0]['Saldo_Inicial']);
                            $dfinallD = ($value2['Debe']);
                            $hfinallD = ($value2['Haber']);
                            $finalFFD = (($saldoInFinallD + $dfinallD) - ($hfinallD));
                        }
                        if ($dfinallD != 0) {

                        } else {
                            $dfinallD = $dfinallD + $value2['Debe'];
                        }
                        if ($hfinallD != 0) {

                        } else {
                            $hfinallD = $hfinallD + $value2['Haber'];
                        }

                        //$dfinallD =  $dfinallD + $value2['Debe'];

                    }
                    $saldoInFinall = $saldoInFinall + $saldoInFinallD + $saldoInFinallI;
                    $dfinall = $dfinall + $dfinallD + $dfinallI;
                    $hfinall = $hfinall + $hfinallD + $hfinallI;
                    $finalFF = $saldoInFinall + $dfinall - $hfinall;


                    //$finalFF=$finalFF+$finalFFD+$finalFFI;

                    $conth++;
                }

            }


            //print '<tr class="trforbreak">'; ehm quite la clase
            print '<tr>';

            print '<td><ul class="list-unstyled components">
                        <li class="active">
                            <a href="#Padre' . $conp . '" data-toggle="collapse" aria-expanded="false" class="">' . $value['NumCta'] . '</a>
                    </td>';
            print '<td class="right"></td>';
            //echo $dfinall.'<br>';
            $saldoInFinallTT = $saldoInFinallTT + $saldoInFinall;///dvn
            //echo $saldoInFinallTT.'<br>';
            $saldofinalw = ($saldoInFinallTT + $deb) - $hab;////dvn
            $dfinallT = $dfinallT + $dfinall;
            $hfinallT = $hfinallT + $hfinall;
            $finalFFT = $finalFFT + $finalFF;
            print '<td>' . $value['Descc'] . '</td>';
            print '<td class=" right">' . price($saldoInFinall)/*$TParent[0]['Debe']*/ . '</td>';
            print '<td class=" right">' . price($dfinall)/*$TParent[0]['Debe']*/ . '</td>';
            print '<td class=" right">' . price($hfinall)/*$TParent[0]['Haber']*/ . '</td>';
            print '<td class=" right">' . price($finalFF)/* ($TParent[0]['Debe'] - $TParent[0]['Haber'])*/ . '</td>';
            print "</tr>\n";
            print '<tr>
                    <td colspan="6">
                        <ul class="collapse list-unstyled" id="Padre' . $conp . '">
                            <li>
                                <table class="liste ' . ($moreforfilter ? "listwithfilterbefore" : "") . '">';
            $dfinall = 0;
            $hfinall = 0;
            $finalFF = 0;
            $saldoInFinall = 0;
            $salRess = 0;
            if (!empty(GETPOST('date_start')) && !empty(GETPOST('date_end'))) {
                $Vtree = treedate($db, $value['NumCta'], '', $datest, $dateen);
            } else {
                $Vtree = tree($db, $value['NumCta'], '');
            }

            if (!empty($Vtree)) {
                $conth = 0;
                foreach ($Vtree as $key => $value2) {
                    if (empty($accounts) || in_array($value2['Index'], $accounts)) {

                        $cont = $contPrin . '_' . $conth;
                        $exist = '';
                        $keyref = '';

                        foreach ($Vtree as $key2 => $valRef) {
                            if ($valRef['Index'] == $value2['Index']) {
                                $exist = 'true';
                                $keyref = $key2;
                            }
                        }

                        if (!empty($exist) && $exist == 'true') {
                            // $queryN = " select a.account_number, a.label labelh, b.account_number padre, b.label,format(ifnull(c.debe,0.00),2) debe , ";
                            // $queryN.= " format(ifnull(c.Haber,0.00),2) haber,format(ifnull(c.debe,0.00)-ifnull(c.Haber,0.00),2) saldo ";
                            // $queryN.= " from    ".MAIN_DB_PREFIX."accounting_account a left join ".MAIN_DB_PREFIX."accounting_account b ";
                            // $queryN.= " on  a.fk_pcg_version = b.fk_pcg_version  ";
                            // $queryN.= " and     a.account_parent = b.rowid left join (SELECT subledger_account  NumCta,sum(debit) as Debe, sum(credit) as Haber   ";
                            //  if (!empty(GETPOST('date_start')) && !empty(GETPOST('date_end'))) {
                            //     $queryN.= "      FROM ".MAIN_DB_PREFIX."accounting_bookkeeping  where doc_date between '".$datest."' and '".$dateen."' ";
                            // } else {
                            //     $queryN.= "      FROM ".MAIN_DB_PREFIX."accounting_bookkeeping  ";
                            // }

                            // $queryN.= "     group by numero_compte ) c ";
                            // $queryN.= " on  a.account_number=c.NumCta ";
                            // $queryN.= " where   b.account_number=".$value2['Index'];
                            // $resN = $db->query($queryN);
                            // $resultN = $resN->fetch_all(MYSQLI_ASSOC);
                            $resultN = getThirdAccount($db, $value2['Index'], GETPOST('date_start'), GETPOST('date_end'));
                            $d = 0;
                            $h = 0;
                            $s = 0;
                            $si = 0;
                            if (!empty($resultN)) {

                                foreach ($resultN as $keyN => $valueN) {
                                    if (empty($accounts) || in_array($valueN['account_number'], $accounts)) {

                                        $d = $d + str_replace(",", "", $valueN['debe']);
                                        $h = $h + str_replace(",", "", $valueN['haber']);
                                        $s = $s + str_replace(",", "", $valueN['saldo']);
                                        $si = $si + str_replace(",", "", $valueN['Saldo_Inicial']);
                                    }
                                }
                            }

                            // Filtros para traer el detalle de la cuenta de segundo nivel
                            if (!empty($value2['Index'])) {

                                $filter['t.numero_compte>='] = $value2['Index'];
                                $param .= '&search_accountancy_code_start=' . urlencode($search_accountancy_code_start);
                            }
                            if (!empty($value2['Index'])) {
                                $filter['t.numero_compte<='] = $value2['Index'];
                                $param .= '&search_accountancy_code_end=' . urlencode($search_accountancy_code_end);
                            }
                            // Obtener datos de tercer nivel
                            //$resultN = $object->fetchAllByAccount($sortorder, $sortfield, $limit, $offset, $filter);
                            $num = count($object->lines);

                            $verifi = lastCount($db, $value2['Index']);
                            //ShowDate
                            /* Hijo */
                            print '<tr class="oddeven">';
                            print '<td>
                                                        <ul class="list-unstyled components">
                                                            <li class="active">
                                                                <a href="#Padre' . $cont . '" data-toggle="collapse" aria-expanded="false" class="">' . $value2['Index'] . '</a>';
                            print '</td>';
                            print '<td></td>';
                            print '<td>' . $value2['Desc'] . '</td>';
                            $saldoInFinal = 0;
                            $dfinal = 0;
                            $hfinal = 0;
                            $finalF = 0;
                            if (!empty($verifi[0]['account_number'])) {
                                $saldoInFinal = $si;
                                $dfinal = $d;
                                $hfinal = $h;
                                // $finalF=($si)+($s);
                            } else {
                                $salRes = salIn($db, $value2['Index'], $datest);
                                $saldoInFinal = $salRes[0]['Saldo_Inicial'];
                                $dfinal = $value2['Debe'];
                                $hfinal = $value2['Haber'];
                                // $finalF=($salRes[0]['Saldo_Inicial']+$value2['Debe'])-($value2['Haber']);
                            }
                            if ($dfinal != 0) {

                            } else {
                                $dfinal = $dfinal + $value2['Debe'];
                            }
                            $finalF = $saldoInFinal + $dfinal - $hfinal;
                            //$dfinal= $dfinal + $value2['Debe'];
                            echo '<td class="right">' . price(($saldoInFinal)) . '</td>'; //saldo inicial
                            echo '<td class="right">' . price($dfinal) . '</td>';
                            echo '<td class="right">' . price($hfinal) . '</td>';
                            echo '<td class="right">' . price($finalF) . ' </td>';
                            print '</tr>';
                            //Imprimiendo detalle de tercer nivel
                            if (!empty($resultN)) {
                                print '<tr><td colspan="6"><ul class="collapse list-unstyled" id="Padre' . $cont . '">
                                                            <li>
                                                            <table class="liste ' . ($moreforfilter ? "listwithfilterbefore" : "") . '">';
                                foreach ($resultN as $keyN => $valueN) {
                                    # code...
                                    if (empty($accounts) || in_array($valueN['account_number'], $accounts)) {
                                        print '  <tr class="oddeven">
                                                                    <td >
                                                                    ' . $valueN['account_number'] . '
                                                                    </td>
                                                                    <td class="center">
                                                                    
                                                                    </td>
                                                                    <td  class="right">
                                                                    ' . $valueN['labelh'] . '
                                                                    </td>
                                                                    <td class="right">
                                                                    ' . price($valueN['Saldo_Inicial']) . '
                                                                    </td>
                                                                    <td class="right">
                                                                    ' . price($valueN['debe']) . '
                                                                    </td>
                                                                    <td class="right">
                                                                    ' . price($valueN['haber']) . '
                                                                    </td>
                                                                    <td class="right">
                                                                    ' . price(($valueN['Saldo_Inicial'] + $valueN['debe']) - ($valueN['haber'])) . '
                                                                    </td>
                                                                </tr>';
                                    }
                                }
                                print '        </table>
                                                            </li>
                                                        
                                                        </ul>
                                                    </li>
                                                </ul></td></tr>';
                            }
                        }
                    } else {
                        /* Padre */
                        // print '<tr class="trforbreak">';
                        // print '<td>' . $value2['Index'] . '</td>';
                        // print '<td>' . $value2['Parent'] . '</td>';
                        // print '<td>' . $value2['Desc'] . 'aquii</td>';

                        // foreach ($Vtree as $key2 => $valRef) {
                        //     $keyref = '';
                        //     if ($valRef['Index'] == $value2['Index']) {
                        //         $exist = 'true';
                        //         $keyref = $key2;
                        //     }
                        // }

                        // print '<td class="right">' . $tot[$keyref]['SDebe'] . '</td>';
                        // print '<td class="right">' . $tot[$keyref]['SHaber'] . '</td>';
                        // print '<td class="right">' . $tot[$keyref]['SSaldo'] . '</td>';
                        // print "</tr>";
                    }
                    $conth++;
                }
            }
            print '                      </table>
                            </li> 
                        </ul>
                    </li>
                </ul>
            </td>
        </tr>';
            $contPrin++;
        }
    }
}

///revisar porque salen otros datos al abrir la trilbalance

/* print '<tr class="liste_total"><td class="right" colspan="2">' . $langs->trans("SubTotal") . ':</td><td class="nowrap right">' . price($sous_total_debit) . '</td><td class="nowrap right">' . price($sous_total_credit) . '</td><td class="nowrap right">' . price(price2num($sous_total_debit - $sous_total_credit)) . '</td>';
print "<td>&nbsp;</td>\n";
print '</tr>'; */

/* print '<tr class="liste_total"><td class="right" colspan="2">' . $langs->trans("AccountBalance") . ':</td><td class="nowrap right">' . price($total_debit) . '</td><td class="nowrap right">' . price($total_credit) . '</td><td class="nowrap right">' . price(price2num($total_debit - $total_credit)) . '</td>'; */
print '<tr class="liste_total">
    <td class="nowraponall right"></td>
    <td class="right" colspan="2">Gran total:</td>    
    <td class="nowrap right">' . price($saldoInFinallTT) . '</td> 
    <td class="nowrap right">' . price($dfinallT) . '</td>
    <td class="nowrap right">' . price($hfinallT) . '</td>
    <td class="nowrap right">' . price($saldofinalw) . '</td>';///dvn
print "<td>&nbsp;</td>\n";
print '</tr>';

print "</table>";
print '</form>';
$search_date_start = dol_mktime(0, 0, 0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
$search_date_end = dol_mktime(23, 59, 59, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

$url .= '&date_startmonth=' . GETPOST('date_startmonth', 'int') . '&date_startday=' . GETPOST('date_startday', 'int') . '&date_startyear=' . GETPOST('date_startyear', 'int');
$url .= '&date_endmonth=' . GETPOST('date_endmonth', 'int') . '&date_endday=' . GETPOST('date_endday', 'int') . '&date_endyear=' . GETPOST('date_endyear', 'int');
print '<a class="butAction' . ($conf->use_javascript_ajax ? ' reposition' : '') . '" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&amp;action=pdfReg' . $url . '" title="' . $title_button . '">' . $langs->trans('Generar Balanza') . '</a>';
print '<a class="butAction' . ($conf->use_javascript_ajax ? ' reposition' : '') . '" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&amp;action=pdfBalanceGeneral' . $url . '" title="' . $title_button . '">' . $langs->trans('Balance General') . '</a>';
print '<a class="butAction' . ($conf->use_javascript_ajax ? ' reposition' : '') . '" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&amp;action=pdfAuxiliares' . $url . '" title="' . $title_button . '">' . $langs->trans('Generar Auxiliares') . '</a>';
print '<a class="butAction' . ($conf->use_javascript_ajax ? ' reposition' : '') . '" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&amp;action=pdfDiarioG' . $url . '" title="' . $title_button . '">' . $langs->trans('Generar Diario G') . '</a>';
print '<a class="butAction' . ($conf->use_javascript_ajax ? ' reposition' : '') . '" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&amp;action=pdfEstadoRes' . $url . '" title="' . $title_button . '">' . $langs->trans('Estado de Resultados') . '</a>';
print '<a class="butAction' . ($conf->use_javascript_ajax ? ' reposition' : '') . '" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&amp;action=pdfLibroMayor' . $url . '" title="' . $title_button . '">' . $langs->trans('Libro Mayor') . '</a>';
print '<a class="butAction' . ($conf->use_javascript_ajax ? ' reposition' : '') . '" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&amp;action=pdfLibroMayMonth' . $url . '" title="' . $title_button . '">' . $langs->trans('Libro Mayor Mes') . '</a>';

$filename = '1balanza';
$filedir = DOL_DATA_ROOT . "/facture/1balanza";
$urlsource = $_SERVER['PHP_SELF'] . '?id=' . $id;
$genallowed = $usercanread;
$delallowed = $usercancreate;

$formfile = new FormFile($db);
//$formmargin = new FormMargin($db);

print $formfile->showdocuments('facture', $filename, $filedir, $urlsource, $genallowed, $delallowed, '', 1, 0, 0, 28, 0, '', '', '', $soc->default_lang);

$somethingshown = $formfile->numoffiles;
// End of page
llxFooter();
$db->close();
