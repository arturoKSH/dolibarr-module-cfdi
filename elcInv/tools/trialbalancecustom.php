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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmargin.class.php';
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
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param .= '&contextpage=' . urlencode($contextpage);
if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit=' . urlencode($limit);

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

if ($action=='pdfReg') {
    $page = GETPOST('page');
    $niveles = ['Nivel 1 ', 'Nievel 2', 'Nievel 3'];
    $formquestion = array(
        array('type' => 'select', 'name' => 'level', 'label' => $langs->trans("Nivel").' ', 'values' => $niveles, 'default' => ''),
    );
    $url  = '';
    $url .='&date_startmonth='. GETPOST('date_startmonth', 'int').'&date_startday='. GETPOST('date_startday', 'int').'&date_startyear='. GETPOST('date_startyear', 'int');
    $url .='&date_endmonth='. GETPOST('date_endmonth', 'int').'&date_endday='. GETPOST('date_endday', 'int').'&date_endyear='. GETPOST('date_endyear', 'int');
    $url .= '&date_start='.GETPOST('date_start');
    $url .= '&date_end='.GETPOST('date_end');
	$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id. $url, 'Niveles de cuenta', 'Seleccionar el nivel de cuenta solicitado', 'pdfReg1', $formquestion, '', 1, 300);
}
if ($action=='pdfReg1' ) {
    // // echo 'aaaaaa';
    // ini_set('memory_limit', '-1'); // Aumentar el límite de memoria a 256 MB (ajusta según tus necesidades)
	// set_time_limit(0);
	// $carpeta = DOL_DATA_ROOT."/facture/".$reffobj[0]['ref'];
	// include(DOL_DOCUMENT_ROOT.'/custom/pdfs_docs/pdf_MOD_THINK_BCOMP.modules.php');
	// $db->begin();
    // $nivel = GETPOST('level');
    // // echo ' / / / / / / ' . $nivel . ' / / / / / / / / / ';
	// $outputlangs = $langs;
	// $pdfP= new pdf_MOD_THINK_BCOMP($db);
    // $pdfP->datestart=substr(GETPOST('date_start'), 6,4)."-".substr(GETPOST('date_start'), 3,2)."-".substr(GETPOST('date_start'), 0,2);
    // $pdfP->dateend=substr(GETPOST('date_end'), 6,4)."-".substr(GETPOST('date_end'), 3,2)."-".substr(GETPOST('date_end'), 0,2);
	// $pdfP->write_file($object,$outputlangs);
    require_once DOL_DOCUMENT_ROOT.'/custom/pdfs_docs/pdf_mod_BC.modules.php';
    
    ini_set('memory_limit', '-1'); // Aumentar el límite de memoria a 256 MB (ajusta según tus necesidades)
	set_time_limit(0);
	$db->begin();
    $nivel = GETPOST('level');
    // echo ' / / / / / / ' . $nivel . ' / / / / / / / / / ';
	$outputlangs = $langs;
	$pdfP= new pdf_mod_BC($db);
   // $pdfP->name = 'Balanza2.0';
    $pdfP->datestart=substr(GETPOST('date_start'), 6,4)."-".substr(GETPOST('date_start'), 3,2)."-".substr(GETPOST('date_start'), 0,2);
    $pdfP->dateend=substr(GETPOST('date_end'), 6,4)."-".substr(GETPOST('date_end'), 3,2)."-".substr(GETPOST('date_end'), 0,2);
	$pdfP->write_file($object,$outputlangs);
}
if ($action=='pdfBalanceGeneral') {
	$carpeta = DOL_DATA_ROOT."/facture/".$reffobj[0]['ref'];
	include(DOL_DOCUMENT_ROOT.'/custom/pdfs_docs/pdf_BALANCE_GENE.modules.php');
	$db->begin();
   
	$outputlangs = $langs;
	$pdfP= new pdf_BALANCE_GENE($db);
    $pdfP->datestart=substr(GETPOST('date_start'), 6,4)."-".substr(GETPOST('date_start'), 3,2)."-".substr(GETPOST('date_start'), 0,2);
    $pdfP->dateend=substr(GETPOST('date_end'), 6,4)."-".substr(GETPOST('date_end'), 3,2)."-".substr(GETPOST('date_end'), 0,2);

	$pdfP->write_file($object,$outputlangs);
}
if ($action=='pdfAuxiliares') {
	$carpeta = DOL_DATA_ROOT."/facture/".$reffobj[0]['ref'];
	include(DOL_DOCUMENT_ROOT.'/custom/pdfs_docs/pdf_MOD_THINK_REPOR_AUX.modules.php');
	$db->begin();
  
	$outputlangs = $langs;
	$pdfP= new pdf_MOD_THINK_REPOR_AUX($db);
    $pdfP->datestart=substr(GETPOST('date_start'), 6,4)."-".substr(GETPOST('date_start'), 3,2)."-".substr(GETPOST('date_start'), 0,2);
    $pdfP->dateend=substr(GETPOST('date_end'), 6,4)."-".substr(GETPOST('date_end'), 3,2)."-".substr(GETPOST('date_end'), 0,2);
    
	$pdfP->write_file($object,$outputlangs);
}
if ($action=='pdfDiarioG') {
    ini_set('memory_limit', '-1'); // Aumentar el límite de memoria a 256 MB (ajusta según tus necesidades)
	set_time_limit(0);
	$carpeta = DOL_DATA_ROOT."/facture/".$reffobj[0]['ref'];
	include(DOL_DOCUMENT_ROOT.'/custom/pdfs_docs/pdf_DiarioGen.modules.php');
	$db->begin();
  
	$outputlangs = $langs;
	$pdfP= new pdf_DiarioGen($db);
    $pdfP->datestart=substr(GETPOST('date_start'), 6,4)."-".substr(GETPOST('date_start'), 3,2)."-".substr(GETPOST('date_start'), 0,2);
    $pdfP->dateend=substr(GETPOST('date_end'), 6,4)."-".substr(GETPOST('date_end'), 3,2)."-".substr(GETPOST('date_end'), 0,2);
    
	$pdfP->write_file($object,$outputlangs);
}
if ($action=='pdfEstadoRes') {
    
	require_once DOL_DOCUMENT_ROOT.'/custom/pdfs_docs/pdf_MOD_EstadoRes.modules.php';
    
	$db->begin();
  
	$outputlangs = $langs;
	$pdfP= new pdf_EstadoRes($db);
    $pdfP->datestart=substr(GETPOST('date_start'), 6,4)."-".substr(GETPOST('date_start'), 3,2)."-".substr(GETPOST('date_start'), 0,2);
    $pdfP->dateend=substr(GETPOST('date_end'), 6,4)."-".substr(GETPOST('date_end'), 3,2)."-".substr(GETPOST('date_end'), 0,2);

	$pdfP->write_file($object,$outputlangs);
}

if ($action=='pdfLibroMayor') {
    
	require_once DOL_DOCUMENT_ROOT.'/custom/pdfs_docs/pdf_LIBRO_MAYOR.modules.php';
    
	$db->begin();
  
	$outputlangs = $langs;
	$pdfP= new pdf_LIBRO_MAYOR($db);
    $pdfP->datestart=substr(GETPOST('date_start'), 6,4)."-".substr(GETPOST('date_start'), 3,2)."-".substr(GETPOST('date_start'), 0,2);
    $pdfP->dateend=substr(GETPOST('date_end'), 6,4)."-".substr(GETPOST('date_end'), 3,2)."-".substr(GETPOST('date_end'), 0,2);

	$pdfP->write_file($object,$outputlangs);
}
if ($action=='pdfBalanza2') {
    
	require_once DOL_DOCUMENT_ROOT.'/custom/pdfs_docs/pdf_mod_BC.modules.php';
    
    ini_set('memory_limit', '-1'); // Aumentar el límite de memoria a 256 MB (ajusta según tus necesidades)
	set_time_limit(0);
	$db->begin();
    $nivel = GETPOST('level');
    // echo ' / / / / / / ' . $nivel . ' / / / / / / / / / ';
	$outputlangs = $langs;
	$pdfP= new pdf_mod_BC($db);
   // $pdfP->name = 'Balanza2.0';
    $pdfP->datestart=substr(GETPOST('date_start'), 6,4)."-".substr(GETPOST('date_start'), 3,2)."-".substr(GETPOST('date_start'), 0,2);
    $pdfP->dateend=substr(GETPOST('date_end'), 6,4)."-".substr(GETPOST('date_end'), 3,2)."-".substr(GETPOST('date_end'), 0,2);
	$pdfP->write_file($object,$outputlangs);
}

/*
 * View
 */

require 'functionsext.php';

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

print  '<link rel="stylesheet" href="assets\css\wrapperr.css">
    <link rel="stylesheet" href="assets\css\bootstrap.css">';
// List
$nbtotalofrecords = '';

print '<form method="POST" id="searchFormList" action="' . $_SERVER["PHP_SELF"] . '">';
if ($optioncss != '') print '<input type="hidden" name="optioncss" value="' . $optioncss . '">';
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
$Saldo_Iniciall=0;

$datest=substr(GETPOST('date_start'), 6,4)."-".substr(GETPOST('date_start'), 3,2)."-".substr(GETPOST('date_start'), 0,2);
$dateen=substr(GETPOST('date_end'), 6,4)."-".substr(GETPOST('date_end'), 3,2)."-".substr(GETPOST('date_end'), 0,2);
if (!empty($datest)) {
    $filter['t.doc_date>='] = $datest;
    $param .= '&search_date_startmonth='.$search_date_startmonth.'&search_date_startday='.$search_date_startday.'&search_date_startyear='.$search_date_startyear;
    $url .= '&date_start='.GETPOST('date_start');
}
if (!empty($dateen)) {
    $filter['t.doc_date<='] = $dateen;
    $param .= '&search_date_endmonth='.$search_date_endmonth.'&search_date_endday='.$search_date_endday.'&search_date_endyear='.$search_date_endyear;
    $url .= '&date_end='.GETPOST('date_end');
}


//empezando ciclos para obtener cuentas
$array_result = accountsTree($datest,$dateen,$search_accountancy_code_start,$search_accountancy_code_end);
$array_totales = $array_result['array_totales'];
$array_result = $array_result['array_result'];

$contPrin=0;
foreach ($array_result as $level1) {
    //echo "Nivel 1: Cuenta {$level1['account_number']}, Débito: {$level1['debit']}<br>";
    $conp="padre_".$contPrin;
    print '<tr>';

    print '<td><ul class="list-unstyled components">
                    <li class="active">
                        <a href="#Padre'.$conp.'" data-toggle="collapse" aria-expanded="false" class="">'. $level1['account_number'].'</a>
                </td>';
    print '<td class="right"></td>';
    print '<td>' . $level1['account_label']. '</td>';
    print '<td class=" right">' . price($level1['sal_inicial'])/*$TParent[0]['Debe']*/ . '</td>';
    print '<td class=" right">' . price($level1['debit'])/*$TParent[0]['Debe']*/ . '</td>';
    print '<td class=" right">' . price($level1['credit'])/*$TParent[0]['Haber']*/ . '</td>';
    print '<td class=" right">' .price($level1['sal_final'])/* ($TParent[0]['Debe'] - $TParent[0]['Haber'])*/ . '</td>';
    print "</tr>\n";
    print '<tr>';
    print '<td colspan="6">';
    print '<ul class="collapse list-unstyled" id="Padre'.$conp.'">';
    print '<li>';
    print '<table class="liste ' . ($moreforfilter ? "listwithfilterbefore" : "") . '">';
    if (!empty($level1['children'])) {
        $conth=0;
        foreach ($level1['children'] as $level2) {
            $cont=$contPrin.'_'.$conth;
            //echo "&nbsp;&nbsp;Nivel 2: Cuenta {$level2['account_number']}, Débito: {$level2['debit']}<br>";
            /* Hijo */
            print '<tr class="oddeven">';
            print '<td>
                    <ul class="list-unstyled components">
                    <li class="active">
                    <a href="#Padre'.$cont.'" data-toggle="collapse" aria-expanded="false" class="">'.$level2['account_number'].'</a>';
            print '</td>';
            print '<td></td>';
            print '<td>' .$level2['account_label'] . '</td>';
            echo '<td class="right">' .  price( ($level2['sal_inicial']))  . '</td>'; //saldo inicial
            echo '<td class="right">' . price( $level2['debit']) . '</td>';
            echo '<td class="right">' . price($level2['credit']) . '</td>';
            echo '<td class="right">' . price($level2['sal_fin'])  . ' </td>';
            print '</tr>';
            print '<tr><td colspan="6"><ul class="collapse list-unstyled" id="Padre'.$cont.'">';
            print '<li>';
            print '<table class="liste ' . ($moreforfilter ? "listwithfilterbefore" : "") . '">';
            if (!empty($level2['children'])) {
                foreach ($level2['children'] as $level3) {
                    //echo "&nbsp;&nbsp;&nbsp;&nbsp;Nivel 3: Cuenta {$level3['account_number']}, Débito: {$level3['debit']}<br>";
                    print'  <tr class="oddeven">
                        <td >
                        '.$level3['account_number'].'
                        </td>
                        <td class="center">
                        
                        </td>
                        <td  class="right">
                        '.$level3['account_label'].'
                        </td>
                        <td class="right">
                        '.price($level3['sal_inicial']).'
                        </td>
                        <td class="right">
                        '.price($level3['debit']).'
                        </td>
                        <td class="right">
                        '.price($level3['credit']).'
                        </td>
                        <td class="right">
                        '.price($level3['sal_final']).'
                        </td>
                    </tr>';
                }
            }
            print'  </table>
                    </li>    
                    </ul>
                    </li>
                    </ul></td></tr>';
            $conth++;
        }
    }
    print'</table>
    </li> 
    </ul>
    </li>
    </ul>
    </td>
    </tr>';
    $contPrin++;
}

print '<tr class="liste_total">
    <td class="nowraponall right"></td>
    <td class="right" colspan="2">Gran total:</td>    
    <td class="nowrap right">' . price($array_totales['sal_inicial']) . '</td> 
    <td class="nowrap right">' . price($array_totales['debit']) . '</td>
    <td class="nowrap right">' . price($array_totales['credit']) . '</td>
    <td class="nowrap right">' . price($array_totales['sal_inicial']) . '</td>';
print "<td>&nbsp;</td>\n";
print '</tr>';

print "</table>";
print '</form>';
$search_date_start = dol_mktime(0, 0, 0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
$search_date_end = dol_mktime(23, 59, 59, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

$url .='&date_startmonth='. GETPOST('date_startmonth', 'int').'&date_startday='. GETPOST('date_startday', 'int').'&date_startyear='. GETPOST('date_startyear', 'int');
$url .='&date_endmonth='. GETPOST('date_endmonth', 'int').'&date_endday='. GETPOST('date_endday', 'int').'&date_endyear='. GETPOST('date_endyear', 'int');
print '<a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;action=pdfReg'.$url.'" title="'.$title_button.'">'.$langs->trans('Generar Balanza').'</a>';
print '<a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;action=pdfBalanceGeneral'.$url.'" title="'.$title_button.'">'.$langs->trans('Balance General').'</a>';
print '<a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;action=pdfAuxiliares'.$url.'" title="'.$title_button.'">'.$langs->trans('Generar Auxiliares').'</a>';
print '<a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;action=pdfDiarioG'.$url.'" title="'.$title_button.'">'.$langs->trans('Generar Diario G').'</a>';
print '<a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;action=pdfEstadoRes'.$url.'" title="'.$title_button.'">'.$langs->trans('Estado de Resultados').'</a>';
print '<a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&amp;action=pdfLibroMayor'.$url.'" title="'.$title_button.'">'.$langs->trans('Libro Mayor').'</a>';
$filename = '1balanza';
$filedir =  DOL_DATA_ROOT."/facture/1balanza";
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
