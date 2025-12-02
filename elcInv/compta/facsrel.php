<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2005 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2017      Ferran Marcet       	 <fmarcet@2byte.es>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *      \file       htdocs/compta/facture/info.php
 *      \ingroup    facture
 *		\brief      Page des informations d'une facture
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT .'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/client.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
if (! empty($conf->projet->enabled)) {
	include_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
}

// Load translation files required by the page
$langs->loadLangs(array('companies', 'bills'));

$id = GETPOST("facid", "int");
$ref=GETPOST("ref", 'alpha');

$action=$_GET['action'];

$limit = GETPOST('limit', 'int')?GETPOST('limit', 'int'):$conf->liste_limit;


require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';


/*
* View
*/

$contactstatic = new Contact($db);
$userstatic=new User($db);

$form = new Form($db);
$formother = new FormOther($db);
$title = $langs->trans('InvoiceCustomer') . " - " . $langs->trans('Facturas relacionadas');
$helpurl = "EN:Customers_Invoices|FR:Factures_Clients|ES:Facturas_a_clientes";
llxHeader('', $title, $helpurl);

$object = new Facture($db);
$object->fetch($id, $ref);
$object->fetch_thirdparty();
$object->info($object->id);


if ($action=='delete') {
  
    $query= " delete  from ".MAIN_DB_PREFIX."kshinvoice_rel where rowid =".$_GET['fk_facrel'];
    
    $res = $db->query($query);
}
if (isset($_POST['actionaddrel'])) {
    $query= " Insert into ".MAIN_DB_PREFIX."kshinvoice_rel (fk_facture_parent,reltype,uuid) ";
    $query.=" values ('".$id."','".$_POST['typerelnew']."','". $_POST['uuidnew']."')"; 
    $res = $db->query($query);
}
// facturas relacionadas externas

$queryfacsRel= " SELECT a.uuid,c.rowid,c.datef,c.fk_statut,c.total_ttc,c.ref,a.rowid rowidksh";
$queryfacsRel.=" from  ".MAIN_DB_PREFIX."kshinvoice_rel a left join ".MAIN_DB_PREFIX."facture_extrafields b ";
$queryfacsRel.=" on a.uuid= b.uuid left join ".MAIN_DB_PREFIX."facture c ";
$queryfacsRel.=" on b.fk_object=c.rowid ";
$queryfacsRel.=" where fk_facture_parent='".$id."'";
$resfacsrel=$db->query($queryfacsRel);
$objectfacsrel=$resfacsrel->fetch_all(MYSQLI_ASSOC);

$documentstatic=new Contrat($db);
$documentstaticline=new ContratLigne($db);

    if ($object->type==2) {
        $queryFacs =" SELECT b.*,c.uuid ";
        $queryFacs.=" FROM ".MAIN_DB_PREFIX."facture a inner join ".MAIN_DB_PREFIX."facture b ";
        $queryFacs.=" on       a.fk_facture_source=b.rowid left join ".MAIN_DB_PREFIX."facture_extrafields c ";
        $queryFacs.=" on    b.rowid=c.fk_object ";
        $queryFacs.=" where     a.rowid=".$id;
        //$queryFacs.=" and b.ref like '%".$sref."'";
     //   $queryFacs.=" group by b.ref ";
        $queryFacs.=" limit ".$limit;
    }else{
        $queryFacs =" SELECT a.*,c.uuid ";
        $queryFacs.=" FROM ".MAIN_DB_PREFIX."facture a inner join ".MAIN_DB_PREFIX."facture b ";
        $queryFacs.=" on       a.fk_facture_source=b.rowid left join ".MAIN_DB_PREFIX."facture_extrafields c ";
        $queryFacs.=" on    b.rowid=c.fk_object ";
        $queryFacs.=" where     a.fk_facture_source=".$id;
        //$queryFacs.=" and b.ref like '%".$sref."'";
       // $queryFacs.=" group by b.ref ";
        $queryFacs.=" limit ".$limit;
    }


$respessT = $db->query($queryFacs);
$objpessT = $respessT->fetch_all(MYSQLI_ASSOC);

$head = facture_prepare_head($object);
dol_fiche_head($head, 'facsrel', $langs->trans("InvoiceCustomer"), -1, 'bill');

//$totalpaye = $object->getSommePaiement();

// Invoice content

$linkback = '<a href="' . DOL_URL_ROOT . '/compta/facture/list.php?restore_lastsearch_values=1' . (!empty($socid) ? '&socid=' . $socid : '') . '">' . $langs->trans("BackToList") . '</a>';
$morehtmlref = '<div class="refidno">';

// Ref customer
$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', 0, 1);
$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', null, null, '', 1);
// Thirdparty
$morehtmlref .= '<br>' . $langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1, 'customer');

//cliente
//$object = new Client($db);
//SATKSH
$query = "SELECT * FROM llx_kshtyperelsat";
$res = $db->query($query);
$valselect = $res->fetch_all(MYSQLI_ASSOC);
//UUID
/* relacion */
$typerelsat = "SELECT   a.ref, b.uuid ";
$typerelsat .= " FROM   " . MAIN_DB_PREFIX . "facture a left JOIN " . MAIN_DB_PREFIX . "facture_extrafields b ";
$typerelsat .= " ON     a.rowid = b.fk_object  ";
$typerelsat .= " where  a.fk_soc='" . $object->socid . "' and b.uuid is not null and a.type=0 ";


$restyperelsat = $db->query($typerelsat);
$objtyperelsat = $restyperelsat->fetch_all(MYSQLI_ASSOC);

//dol_banner_tab($object, 'socid', $linkback, 0, 'rowid', 'nom', $morehtmlref, '', 0);
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0);
if(empty($object->array_options['options_uuid'])) // ehm se agrego validacion para no agregar despues de ser timbrada
{
    print '<form action="' . $_SERVER['PHP_SELF'] . '?facid=' . $id . '" method="POST">';
        /* print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="from" value="' . dol_escape_htmltag(GETPOST('from', 'alpha')) . '">'; */
        print '<div class="div-table-responsive-no-min">';
            print '<table class="noborder centpercent">';
                print '<tr class="liste_titre">';
                    print '<td  colspan="3" class="center">UUID</td>';
                    print '<td  colspan="3" class="center">Tipo de relación </td>';
                    print '<td  colspan="3" class="center">&nbsp;</td>';
                print '</tr>';
                // Line to enter new values
                print '<!-- line to add new entry -->';
                print '<tr class="oddeven nodrag nodrop nohover">';
                    print '<td colspan="3" class="center">';
                    print '<select name="uuidnew" class="flat selectpaymenttypes">';
                        foreach ($objtyperelsat as $key => $value) {
                            print '<option value="'.$value['uuid'].'">'.$value['uuid'].'</option>';
                        }
                    print '</select>';

                    /*print '<input type="text" name="uuidnew" >';*/
                    print '</td>';
                    print '<td colspan="3" class="center">';
                    print '<select name="typerelnew" id="typerelnews" class="flat selectpaymenttypes">';
                        foreach ($valselect as $key => $value) {
                            //ehm se modifico por idsat, el campo se llama asi
                            print '<option value="'.$value['idsat'].'">'.$value['description'].'</option>';
                        }
                    print '</select>';
                /* print '<input type="text" name="typerelnew" >';*/
                    print '</td>';

                    print '<td colspan="3" class="center">';
                    
                    if ($action != 'edit') {
                        print '<input type="submit" class="button" name="actionaddrel" value="' . $langs->trans("Add") . '">';
                    }
                print '</td>';
            
                print "</tr>";
            print '</table>';
        print '</div>';
    print '</form>';
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?facid='.$id.'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print_barre_liste($langs->trans('Facturas Relacionadas').' '.$typeElementString.' '.$button, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $totalnboflines, '', 0, '', '', $limit);
print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';


print '<table class="liste" width="100%">'."\n";
    // Titles with sort buttons
   print '<table class="liste" width="100%">'."\n";

    // Filters
   /* print '<tr class="liste_titre">';
    print '<td class="liste_titre left">';
    print '<input class="flat" type="text" name="sref" size="8" value="'.$sref.'">';
    print '</td>';
    print '<td class="liste_titre nowrap center">'; // date
    print $formother->select_month($month?$month:-1, 'month', 1, 0, 'valignmiddle');
    $formother->select_year($year?$year:-1, 'year', 1, 20, 1);
    print '</td>';
    print '<td class="liste_titre center">';
    print '</td>';
   
   
    
  
    print '<td class="liste_titre maxwidthsearch">';
    $searchpicto=$form->showFilterAndCheckAddButtons(0);
    print $searchpicto;
    print '</td>';
    print '</tr>';*/

    // Titles with sort buttons
    print '<tr class="liste_titre">';
    print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 'doc_number', '', $param, '', $sortfield, $sortorder, 'left ');
     print_liste_field_titre('UUID', $_SERVER['PHP_SELF'], 'doc_number', '', $param, '', $sortfield, $sortorder, 'left ');
    print_liste_field_titre('Date', $_SERVER['PHP_SELF'], 'dateprint', '', $param, 'width="150"', $sortfield, $sortorder, 'center ');
    print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'fk_statut', '', $param, '', $sortfield, $sortorder, 'center ');
   
   
    print_liste_field_titre('TotalHT', $_SERVER['PHP_SELF'], 'total_ht', '', $param, '', $sortfield, $sortorder, 'right ');
     print_liste_field_titre('Eliminar', $_SERVER['PHP_SELF'], 'Eliminar', '', $param, '', $sortfield, $sortorder, 'right ');
   
    print "</tr>\n";

    foreach ($objpessT as $key => $value) {
    	print '<tr class="oddeven">';
			print "<td >";
				print "<a href='".DOL_URL_ROOT."/compta/facture/card.php?facid=".$value['rowid']."'> ".$value['ref']."</a>";
				
			print "</td>";
            print "<td>";
                print $value['uuid'];
            print "</td>";
			print "<td class='center'>";
				print $value['datef'];
			print "</td>";
			print "<td class='center'>";
			$documentstatic->statut=$value['fk_statut'];
				 print $documentstatic->getLibStatut(2);
			print "</td>";
			print "<td class='right'>";
				print $value['total_ttc'];
			print "</td>";

    	print "</tr>";
    }
        foreach ($objectfacsrel as $key => $value) {
            $url = $_SERVER["PHP_SELF"].'?facid='.$id.'&fk_facrel='.$value['rowidksh'].'&';
            print '<tr class="oddeven">';
            print "<td >";
            print "<a href='".DOL_URL_ROOT."/compta/facture/card.php?facid=".$value['rowid']."'> ".$value['ref']."</a>";
                
            print "</td>";
            print "<td>";
                print $value['uuid'];
            print "</td>";
            print "<td class='center'>";
                print $value['datef'];
            print "</td>";
            print "<td class='center'>";
            $documentstatic->statut=$value['fk_statut'];
                 print $documentstatic->getLibStatut(2);
            print "</td>";
            print "<td class='right'>";
                print $value['total_ttc'];
            print "</td>";
            if(empty($object->array_options['options_uuid'])) // ehm se agrego para evitar eliminar cuando ya fue timbrada
            {
                print '<td colspan="3" class="center">';
                print '<a href="' . $url . 'action=delete">' . img_delete() . '</a>';
                print '</td>';
            }

        print "</tr>";
    }
	print "</table>";print '</div>';
print "</form>";
dol_fiche_end();

// End of page
llxFooter();
$db->close();
