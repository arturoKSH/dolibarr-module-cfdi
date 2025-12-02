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
    $query .= " where numero_compte = '" . $rowid . "'";
    $query .= " group by numero_compte";
    $res = $db->query($query);
    $result = $res->fetch_all(MYSQLI_ASSOC);

    //echo $rowid.'</br>';
    return $result;
}

function dad($db)
{
    $query = " select * from llx_cashflowvw ";
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
    
    $resN = $db->query($queryN);
    $resultN = $resN->fetch_all(MYSQLI_ASSOC);
    return $resultN;
}

