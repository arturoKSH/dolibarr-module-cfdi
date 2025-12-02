<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$lblfld = array();
$cnt = max(array_keys($tablib)) + 1;

$taborder[sizeof($taborder)] = $cnt;
// Name of SQL tables of dictionaries
$tabname[$cnt] = MAIN_DB_PREFIX."KSHTblDic";

// Dictionary labels
$tablib[$cnt] = "Diccionarios adicionales";

// Requests to extract data
$tabsql[$cnt] = " select a.rowid,a.tableName,a.label,a.strquery,a.orderby,a.sresult,a.sedit,a.sinsert,a.identity,a.listhelp,a.lblfld,a.active from ".MAIN_DB_PREFIX."KSHTblDic a ";

// Criteria to sort dictionaries
$tabsqlsort[$cnt] = "rowid ASC";

// Field names in select result for dictionary display
$tabfield[$cnt] = "tableName,label,strquery,orderby,sresult,sedit,sinsert,identity,lblfld";

// Edit field names for editing a record
$tabfieldvalue[$cnt] = "tableName,label,strquery,orderby,sresult,sedit,sinsert,identity,lblfld";

// Field names in the table for inserting a record
$tabfieldinsert[$cnt] = "tableName,label,strquery,orderby,sresult,sedit,sinsert,identity,lblfld";

// Rowid name of field depending if field is autoincrement on or off..
// Use "" if id field is "rowid" and has autoincrement on
// Use "nameoffield" if id field is not "rowid" or has not autoincrement on
$tabrowid[$cnt] = "rowid";

// Condition to show dictionary in setup page
$tabcond[$cnt] = true;

// List of help for fields
$tabhelp[$cnt]  = array("code"=>$langs->trans("EnterAnyCode"));

// List of check for fields (NOT USED YET)
//$tabfieldcheck[$cnt]  = array();
$tabcomplete['KSHTblDic'] = array('picto'=>'resource');

$cnt++;
$sqlDic = " select tableName,label,strquery,orderby,sresult,sedit,sinsert,identity,listhelp,lblfld,active from ".MAIN_DB_PREFIX."KSHTblDic ";
$resultDic = $db->query($sqlDic);
if ($resultDic) {    
    foreach ($resultDic as $dic => $valueDic) {        
        $taborder[sizeof($taborder)] = $cnt;
        $tabname[$cnt] = MAIN_DB_PREFIX.$valueDic["tableName"];
        $tablib[$cnt] = $valueDic["label"];
        $tabsql[$cnt] = "  select ".$valueDic["strquery"]." from ".MAIN_DB_PREFIX.$valueDic["tableName"]." a ";
        $tabsqlsort[$cnt] = $valueDic["orderby"]." ASC ";
        $tabfield[$cnt] = $valueDic["sresult"];
        $tabfieldvalue[$cnt] = $valueDic["sedit"];
        $tabfieldinsert[$cnt] = $valueDic["sinsert"];
        $tabrowid[$cnt] = $valueDic["identity"];
        $tabcond[$cnt] =  $valueDic["active"]==1?true:false;
        $tabhelp[$cnt]  = $valueDic["listhelp"];
        $tabcomplete[$valueDic["tableName"]] = array('picto'=>'resource');
        $lblfld[$valueDic["tableName"]] = explode(",", $valueDic["lblfld"]);
        $cnt++;

        //echo $valueDic["active"]==1?"true":"false";
    }
    
} else {
    dol_print_error($db);
}

function getLableAdd($value, $tblName, $pos, $lblfld)
{     
    
    $tblName = str_replace(MAIN_DB_PREFIX, "", $tblName);
    
    $label = "";
    switch ($value) {    
        case 'tableName': $label = 'Tabla';break;
        case 'label': $label = 'Etiqueta';break;
        case 'strquery': $label = 'Consulta';break;
        case 'orderby': $label = 'Orden';break;
        case 'sresult': $label = 'Campos listado';break;
        case 'sedit': $label = 'Campos edición';break;
        case 'sinsert': $label = 'Campos Inserción';break;
        case 'identity': $label = 'Campo automático';break;        
        case 'listhelp': $label = 'Codigo ayuda';break;
        case 'lblfld': $label = 'Etiquetas campos';break;
    }
    if($label == "")
        $label = $lblfld[$tblName][$pos];

    $valuetoshow = $label ; $class = 'center';
    
    return $valuetoshow;
}