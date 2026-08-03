<?php

function total($db, $rowid)
{
    $query = " SELECT numero_compte NumCta,sum(debit) as Debe, sum(credit) as Haber   ";
    $query .= " ,ifnull((select ifnull(SUM(a.debit)- SUM(credit),0)  ";
	$query .= "  From llx_accounting_bookkeeping a ";
    $query .= "  where a.doc_date  <= LAST_DAY(DATE_FORMAT('".date("Y")."-12-01' - INTERVAL 1 MONTH, '%Y-%m-01')) ";
	$query .= " AND a.numero_compte = '" . $rowid . "'  ";
	$query .= " GROUP BY a.numero_compte),0) Saldo_Inicial";
    $query .= " FROM " . MAIN_DB_PREFIX . "accounting_bookkeeping ";
    $query .= " where numero_compte = '" . $rowid . "' and doc_date between '2023-12-01' and '2023-12-31' ";
    $query .= " group by numero_compte";
    $res = $db->query($query);
    $result = $res->fetch_all(MYSQLI_ASSOC);
    
   
    return $result;
}

function dad()
{
    global $db;
    $query = "SELECT a.account_number AS NumCta, a.label AS Descc, b.account_number, b.label ";
    $query .= "FROM llx_accounting_account a LEFT JOIN llx_accounting_account b ";
    $query .= "ON a.fk_pcg_version = b.fk_pcg_version AND a.account_parent = b.rowid ";
    $query .= "WHERE a.fk_pcg_version='Crasaoficial' AND b.account_number IS NULL ";
    // Si los parámetros no están vacíos, agregar la condición BETWEEN a la consulta
    // if ($search_accountancy_code_start !== "" && $search_accountancy_code_end !== "" && $search_accountancy_code_start !== "0" && $search_accountancy_code_end !== "0" && $search_accountancy_code_start !== "-1" && $search_accountancy_code_end !== "-1") { 
    //     //  $query .= "AND a.account_number BETWEEN '" . $search_accountancy_code_start . "' AND '" . $search_accountancy_code_end . "'";
    // }
    
    $res = $db->query($query);
    $result = $res->fetch_all(MYSQLI_ASSOC);
    return $result;
}

function lastCount($db, $sup)
{
    $query =   "SELECT 	b.account_number
                FROM 	llx_accounting_account a INNER JOIN llx_accounting_account b
                ON 		a.rowid = b.account_parent
                AND     a.account_number = '" . $sup."'";
               
    $res = $db->query($query);
    $result = $res->fetch_all(MYSQLI_ASSOC);
    return $result;
}

/* tiene padre? */
function Idad($db, $sup)
{
    $query =   "SELECT 	'D' Natur,2 Nivel, a.account_number SubCtaDe , b.label Descc, b.account_number  NumCta, '' CodAgruop
                FROM 	llx_accounting_account a INNER JOIN llx_accounting_account b
                ON 		a.rowid = b.account_parent
                AND     a.account_number = '" . $sup."'";
    $res = $db->query($query);
    $result = $res->fetch_all(MYSQLI_ASSOC);
    // echo $query;
    return $result;
}

/* */
function tree($db, $sup, $list = '')
{
    if (!is_array($list)) {
        $list = array();
    }
    $Idad = Idad($db, $sup);
    if (!empty($Idad)) {
        foreach ($Idad as $key => $value) {
            $total = total($db, $value['NumCta']);
            if (!empty($total)) {
                $list[] = [
                    "Parent" => $value['SubCtaDe'],
                    "Index" => $value['NumCta'],
                    "Desc" => $value['Descc'],
                    "Debe" => bcdiv($total[0]['Debe'], '1', 2),
                    "Haber" => bcdiv($total[0]['Haber'], '1', 2),
                    "SFin" => bcdiv(abs($total[0]['Haber'] - $total[0]['Debe']), '1', 2),
                    "Saldo_Inicial"=>bcdiv($total[0]['Saldo_Inicial'], '1', 2)
                ];
            } else {
                
                $list[] = [
                    "Parent" => $value['SubCtaDe'],
                    "Index" => $value['NumCta'],
                    "Desc" => $value['Descc'],
                    "Debe" => 0,
                    "Haber" => 0,
                    "SFin" => 0,
                    "Saldo_Inicial" => 0
                ];
            }
        }
    }

    return $list;
}

//POST dateinit / dateend


function totaldate($db, $rowid, $init, $end)
{
    $query = " SELECT d.numero_compte NumCta,ifnull(sum(d.debit),0) as Debe, sum(d.credit) as Haber ,  sum(d.debit) - sum(d.credit) as SaldoFin ";
    $query .= " ,ifnull((select ifnull(SUM(a.debit)- SUM(a.credit),0)  ";
	$query .= "  From llx_accounting_bookkeeping a ";
    $query .= "  where a.doc_date <= LAST_DAY(DATE_FORMAT('".$init."' - INTERVAL 1 MONTH, '%Y-%m-01')) ";
	$query .= " AND a.numero_compte = '" . $rowid . "' GROUP BY a.numero_compte),0) Saldo_Inicial ";
    $query .= " FROM " . MAIN_DB_PREFIX . "accounting_bookkeeping d left join llx_accounting_account e
                on d.numero_compte=e.account_number ";
    $query .= " where d.numero_compte = '" . $rowid."' ";
    $query .= " and d.doc_date between '" . $init . "' and '" . $end . "'";
    $query .= " group by numero_compte";
    
    $res = $db->query($query);
    $result = $res->fetch_all(MYSQLI_ASSOC);

    /* SELECT numero_compte NumCta,sum(debit) as Debe, sum(credit) as Haber , sum(montant) as SaldoIni, (sum(debit) + sum(montant))- sum(credit) as SaldoFin
        FROM llx_accounting_bookkeeping 
        group by numero_compte */

    return $result;
}
function salIn($db, $rowid, $init){
    $query = " select ifnull(SUM(a.debit)- SUM(a.credit),0) Saldo_Inicial ";
	$query .= "  From llx_accounting_bookkeeping a ";
    $query .= "  where a.doc_date <= LAST_DAY(DATE_FORMAT('".$init."' - INTERVAL 1 MONTH, '%Y-%m-01')) ";
	$query .= " AND a.numero_compte = '" . $rowid . "' GROUP BY a.numero_compte ";
   
    $res = $db->query($query);
    $result = $res->fetch_all(MYSQLI_ASSOC);
    return $result;
}

/* Jefe tiene personal capacitado */
function treedate($db, $sup, $list = '', $init, $end)
{
    if (!is_array($list)) {
        $list = array();
    }
    $Idad = Idad($db, $sup);
    if (!empty($Idad)) {
       
        foreach ($Idad as $key => $value) {
           
            $total = totaldate($db, $value['NumCta'], $init, $end);
          
            if (!empty($total)) {
                $list[] = [
                    "Parent" => $value['SubCtaDe'],
                    "Index" => $value['NumCta'],
                    "Desc" => $value['Descc'],
                    "Debe" => $total[0]['Debe'],
                    "Haber" => $total[0]['Haber'],
                    "SFin" => abs($total[0]['Haber'] - $total[0]['Debe']),
                    "Saldo_Inicial"=>$total[0]['Saldo_Inicial']
                ];
            } else {
                
                $list[] = [
                    "Parent" => $value['SubCtaDe'],
                    "Index" => $value['NumCta'],
                    "Desc" => $value['Descc'],
                    "Debe" => 0,
                    "Haber" => 0,
                    "SFin" => 0,
                    "Saldo_Inicial" => 0
                ];
               
            }
            //$list = treedate($db, $value['NumCta'], $list, $init, $end);
        }
    }
    
    return $list;
}

function insertcir($db, $type, $dateinit, $datedue, $rowid, $user, $xtime, $ultMes = '')
{
    $query = " SELECT * ";
    $query .= " FROM llx_user ";
    $query .= " where login = '" . $user . "'";
    $res = $db->query($query);
    $us = $res->fetch_all(MYSQLI_ASSOC);


    $month = 0;
    $year = 0;

    if ($xtime == "Year") {
        $year = substr($dateinit, 0, 4);
        if ($type == "cerrada") {
            $typey = 1;
            $typem = 0;
        } elseif ($type == "abierta") {
            $typey = 0;
            $typem = 0;
        }
    } elseif ($xtime == "Month") {
        $year = substr($dateinit, 0, 4);
        $month = substr($dateinit, 5, 2);
        if ($type == "cerrada") {
            $typey = 0;
            $typem = 1;
        } elseif ($type == "abierta") {
            $typey = 0;
            $typem = 0;
        }
    }
    
    if (!empty($ultMes)) {
        $month = $ultMes;
    }

    $query = " SELECT * ";
    $query .= " FROM llx_kshaccountingstatus ";
    $query .= " where bookaccount = '" . $rowid . "'";
    $query .= " and year = " . $year . " and month = " . $month . " ";
    $res = $db->query($query);
    $result = $res->fetch_all(MYSQLI_ASSOC);

    if (!empty($result)) {
        if (empty($year)) {
            $year = 0;
        }

        $query = "UPDATE llx_kshaccountingstatus SET";
        //$query .= " month=" . $month . "";
        //$query .= ", year=" . $year . "";
        $query .= " statusmonth=" . $typem . "";
        $query .= ", statusyear=" . $typey . "";
        $query .= ", userclouse=" . $us[0]['rowid'] . "";
        $query .= ", date_modify=(NOW())";
        $query .= " WHERE bookaccount = '" . $rowid . "'";
        $query .= " and month = ".$month." ";
        $query .= " and year= ".$year." ";
        if ($res = $db->query($query)) {
            return $query;
        } else {
            return $query;
        }
    } else {
        $query = " INSERT INTO llx_kshaccountingstatus (";
        $query .= " month";
        $query .= ", year";
        $query .= ", bookaccount";
        $query .= ", statusmonth";
        $query .= ", statusyear";
        $query .= ", userclouse";
        $query .= ") values (";
        $query .= " " . $month . " ";
        $query .= ", " . $year . " ";
        $query .= ",'" . $rowid . "'";
        $query .= ", " . $typem . " ";
        $query .= ", " . $typey . " ";
        $query .= ", " . $us[0]['rowid'] . " )";
        if ($res = $db->query($query)) {
            return $query;
        } else {
            return $query;
        }
    }
}
function getThirdAccount($db,$value,$datest,$dateen){
    if(substr($datest,4,1)!='-'){
        if(!empty($datest)){
        $datest=substr($datest, 6,4)."-".substr($datest, 3,2)."-".substr($datest, 0,2);
        }
        if(!empty($dateen)){
            $dateen=substr($dateen, 6,4)."-".substr($dateen, 3,2)."-".substr($dateen, 0,2);
        }
    }
    $queryN = " select a.account_number, a.label labelh, b.account_number padre, b.label,ifnull(c.debe,0) debe , ";
    $queryN.= " ifnull(c.Haber,0) haber,ifnull(c.debe,0)-ifnull(c.Haber,0) saldo ";
    $queryN .= " ,ifnull((select ifnull(SUM(h.debit)- SUM(h.credit),0)  ";
	$queryN .= "  From llx_accounting_bookkeeping h ";
    if (!empty($datest) && !empty($dateen)) {
    $queryN .= "  where h.doc_date <= LAST_DAY(DATE_FORMAT('".$datest."' - INTERVAL 1 MONTH, '%Y-%m-01')) ";
    }else {
    $queryN .= "  where h.doc_date <= LAST_DAY(DATE_FORMAT('".date("Y")."-12-01' - INTERVAL 1 MONTH, '%Y-%m-01')) ";
    }
	$queryN .= " AND h.numero_compte =a.account_number GROUP BY h.numero_compte),0) Saldo_Inicial ";
    $queryN.= " from    ".MAIN_DB_PREFIX."accounting_account a left join ".MAIN_DB_PREFIX."accounting_account b ";
    $queryN.= " on  a.fk_pcg_version = b.fk_pcg_version  ";
    $queryN.= " and     a.account_parent = b.rowid left join (SELECT numero_compte  NumCta,sum(debit) as Debe, sum(credit) as Haber   ";
        if (!empty($datest) && !empty($dateen)) {
        $queryN.= "      FROM ".MAIN_DB_PREFIX."accounting_bookkeeping  where doc_date between '".$datest."' and '".$dateen."' ";
    } else {
        $queryN.= "      FROM ".MAIN_DB_PREFIX."accounting_bookkeeping  ";
    }
    
    $queryN.= "     group by numero_compte ) c ";
    $queryN.= " on  a.account_number=c.NumCta ";
    $queryN.= " where   b.account_number='".$value."'";
    // echo $queryN;
    $resN = $db->query($queryN);
    $resultN = $resN->fetch_all(MYSQLI_ASSOC);
    return $resultN;
}

/**
 * Funciones para balanza 2.0
 */

 function accountsTree($datest,$dateen,$search_accountancy_code_start,$search_accountancy_code_end){
    global $db;
    // Realiza la consulta para obtener todos los registros
    $query = "SELECT * FROM llx_accounting_account ";
    $query .= " where fk_pcg_version = 'crasaOficial' ";
    if($search_accountancy_code_start) 
        $query .= " and account_number >= '".$search_accountancy_code_start."'";
    if($search_accountancy_code_end)  
        $query .= " and account_number <= '".$search_accountancy_code_end."'";
        $query .= " order by rowid asc";
    $result =  $db->query($query);
    $cuentas_organizadas = array();
    $array_account_t = array();
    $tt_debit = 0;
    $tt_inicial_by_parent = 0;
    $tt_debit_by_parent = 0;
    $tt_debit_by_parent_level2 = 0;
    $array_result = array();

    $array_totales = array();
    $TTotal_SalInicial = 0;
    $TTotal_debit = 0;
    $TTotal_credit = 0;
    while ($row = mysqli_fetch_assoc($result)) {

        $account_parent = $row['account_parent'];
        $rowid = $row['rowid'];
        $label = $row['label'];
        $account_number = $row['account_number'];
        $account_label = $row['label'];
        $tt_debit_by_parent = 0;
        $tt_inicial_by_parent = 0;
        $tt_credit_by_parent = 0;

        if (!$account_parent) {
            $amount_t = 0;
            $amount_t = getsumLevel($account_number,$datest,$dateen);

            $account_level1 =  $account_number;
            $tt_inicial_by_parent  +=  $amount_t['Saldo_Inicial'];
            $tt_debit_by_parent +=  $amount_t['debit'];
            $tt_credit_by_parent +=  $amount_t['credit'];

            $next_account = getaccountNextLavel($rowid);
            if($next_account){
                foreach($next_account as $key => $value){
                    $amount_t = 0;
                    $tt_debit_by_parent_level2 = 0;
                    $tt_credit_by_parent_level2 = 0;
                    $next_account2 = getaccountNextLavel($value['rowid']);
                    $amount_t = getsumLevel($value['account_number'],$datest,$dateen);
                    $account_level2 = $value['account_number'];
                    $account_label_level2 = $value['label'];

                    $tt_inicial_by_parent  +=  $amount_t['Saldo_Inicial'];
                    $tt_debit_by_parent +=  $amount_t['debit'];
                    $tt_credit_by_parent +=  $amount_t['credit'];

                    $tt_inicial_by_parent_level2 = $amount_t['Saldo_Inicial'];
                    $tt_debit_by_parent_level2 = $amount_t['debit'];
                    $tt_credit_by_parent_level2 = $amount_t['credit'];

                    if($next_account2){
                        foreach($next_account2 as $key2 => $value2){
                            $amount_t = 0;
                            $amount_t = getsumLevel($value2['account_number'],$datest,$dateen);
                            $account_level3 =  $amount_t['account_number'];
                            $account_label_level3 =  $amount_t['label'];

                            $tt_inicial_by_parent  +=  $amount_t['Saldo_Inicial'];
                            $tt_debit_by_parent +=  $amount_t['debit'];
                            $tt_credit_by_parent +=  $amount_t['credit'];

                            $tt_inicial_by_parent_level2 += $amount_t['Saldo_Inicial'];
                            $tt_debit_by_parent_level2 +=  $amount_t['debit'];
                            $tt_credit_by_parent_level2 += $amount_t['credit'];

                            $total_inicial_level3 = $amount_t['Saldo_Inicial'];
                            $total_debit_level3 = $amount_t['debit'];
                            $total_credit_level3 = $amount_t['credit'];

                            $array_result[$account_level1]['children'][$account_level2]['children'][$account_level3] = array(
                                'account_number' => $account_level3,
                                'account_label' => $account_label_level3,
                                'sal_inicial' => $total_inicial_level3,
                                'debit' => $total_debit_level3,
                                'credit' => $total_credit_level3,
                                'sal_final'=> $total_inicial_level3 + $total_debit_level3 - $total_credit_level3,
                            );

                        }
                    }
                    $array_result[$account_level1]['children'][$account_level2] = array(
                        'account_number' => $account_level2,
                        'account_label' => $account_label_level2,
                        'sal_inicial' => $tt_inicial_by_parent_level2,
                        'debit' => $tt_debit_by_parent_level2,
                        'credit' => $tt_credit_by_parent_level2,
                        'sal_final'=> $tt_inicial_by_parent_level2 + $tt_debit_by_parent_level2 - $tt_credit_by_parent_level2,
                        'children' => $array_result[$account_level1]['children'][$account_level2]['children']
                    );
                }
            
            }
            // Agregar los datos al array con el total de débito
            $array_result[$account_level1] = array(
                'account_number' => $account_level1,
                'account_label' =>  $account_label,
                'sal_inicial' => $tt_inicial_by_parent,
                'debit' => $tt_debit_by_parent,
                'credit' => $tt_credit_by_parent,
                'sal_final'=> $tt_inicial_by_parent + $tt_debit_by_parent - $tt_credit_by_parent,
                'children' => $array_result[$account_level1]['children']
            );
            $TTotal_SalInicial += $tt_inicial_by_parent;
            $TTotal_debit += $tt_debit_by_parent;
            $TTotal_credit += $tt_credit_by_parent;
        } 
    }
    $array_totales = [
        'sal_inicial' => $TTotal_SalInicial,
        'debit' => $TTotal_debit,
        'credit' => $TTotal_credit,
        'sal_final' => $TTotal_SalInicial + $TTotal_debit - $TTotal_credit
    ];
    return array('array_result' => $array_result , 'array_totales' => $array_totales);
}
function getsumLevel($numero_compte,$datest,$dateen){
    global $db;
    
    $query_transacciones = "SELECT  a.account_number ,a.label, round(ifnull(SUM(b.debit),0),2) AS debit,round(ifnull(SUM(b.credit),0),2) AS credit";
    $query_transacciones .= " ,ifnull((select ifnull(SUM(a.debit)- SUM(a.credit),0)  ";
    $query_transacciones .= "  From llx_accounting_bookkeeping a ";
    $query_transacciones .= "  where a.doc_date <= LAST_DAY(DATE_FORMAT('".$datest."' - INTERVAL 1 MONTH, '%Y-%m-01')) ";
    $query_transacciones .= " AND a.numero_compte = '" . $numero_compte . "' GROUP BY a.numero_compte),0) Saldo_Inicial ";
    $query_transacciones .= " FROM llx_accounting_account a
               LEFT JOIN llx_accounting_bookkeeping b ON b.doc_date between '".$datest."' and '".$dateen."'
               and   a.account_number = b.numero_compte
               where a.account_number = '".$numero_compte."'
               GROUP BY a.rowid, a.account_number ";
    $result_transacciones = $db->query($query_transacciones);
    $result = array();
    $total_debit = 0;
    $total_credit = 0;
    $account_number = 0;
    $row = mysqli_fetch_assoc($result_transacciones);
        $rowid_cuenta = $row['rowid_cuenta'];
        $total_debit = $row['debit'];
        $total_credit = $row['credit'];
        $account_number = $row['account_number'];
        $Saldo_Inicial = $row['Saldo_Inicial'];
        $label = $row['label'];
    $result = [
        'Saldo_Inicial' => $Saldo_Inicial,
        'debit' => $total_debit,
        'credit' => $total_credit,
        'account_number' => $account_number,
        'label' => $label
    ];
    return $result;
}
function getaccountNextLavel($parent_account){
    global $db;
    $result= '';
    $query = "SELECT * FROM llx_accounting_account where account_parent = ".$parent_account." order by rowid asc";
    $res = $db->query($query);
    if($res){
        $result = $res->fetch_all(MYSQLI_ASSOC);
    }
    return $result;
}