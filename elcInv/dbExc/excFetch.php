<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$active = true;

/**
 * Ultimo error SQL registrado por excQry(), o cadena vacia si la ultima consulta
 * salio bien. Permite que un flujo critico (timbrado) aborte en lugar de seguir
 * con datos incompletos. Ver excQryFailed().
 */
$GLOBALS['excQryLastError'] = '';

/**
 * Ejecuta una consulta y devuelve todas las filas como array asociativo.
 *
 * Si la consulta falla no lanza un fatal error: registra el detalle (syslog de
 * Dolibarr + tabla de errores del modulo), avisa en pantalla y devuelve un array
 * vacio, de modo que los llamadores puedan asumir siempre un array.
 *
 * OJO: un array vacio no distingue "sin resultados" de "la consulta fallo". En un
 * flujo que arma un CFDI eso importa, porque seguir adelante puede generar un
 * comprobante con datos faltantes. Para eso esta excQryFailed().
 *
 * @param  string $script SQL a ejecutar
 * @return array          Filas encontradas; array vacio si no hubo o si fallo
 */
function excQry($script)
{
    global $db, $user;

    $GLOBALS['excQryLastError'] = '';

    $resultQry = $db->query($script);

    // DoliDB::query() devuelve false cuando la consulta falla. Antes se llamaba
    // fetch_all() sobre ese false, lo que producia
    // "Call to a member function fetch_all() on bool" sin dejar rastro del SQL.
    if ($resultQry === false) {
        $errno  = $db->lasterrno();
        $errmsg = $db->lasterror();
        $detail = 'excQry ['.$errno.'] '.$errmsg;

        $GLOBALS['excQryLastError'] = $detail;

        // Siempre al syslog, con el SQL completo para poder diagnosticar.
        if (function_exists('dol_syslog')) {
            dol_syslog('excQry fallo: '.$detail." | SQL: ".$script, LOG_ERR);
        }

        // Y a la tabla del modulo, para que quede visible en CFDI > Registro de errores.
        if (function_exists('cfdiLogError')) {
            cfdiLogError($db, 'sql', '', $detail." | SQL: ".$script, 0);
        }

        // Aviso en pantalla. El SQL solo se muestra a admin: expone estructura de la base.
        if (function_exists('setEventMessages')) {
            $onScreen = !empty($user->admin) ? $detail.' | SQL: '.$script : $detail;
            setEventMessages($onScreen, null, 'errors');
        }

        return array();
    }

    $resultObj = $resultQry->fetch_all(MYSQLI_ASSOC);

    $resultQry->free();

    // fetch_all() devuelve array, pero normalizamos para que el contrato de retorno
    // sea siempre array y los llamadores no tengan que revisar el tipo.
    return is_array($resultObj) ? $resultObj : array();
}

/**
 * Indica si la ultima llamada a excQry() fallo por un error de SQL.
 *
 * @param  string $errmsg Recibe el detalle del error, si hubo
 * @return bool           True si la ultima consulta fallo
 */
function excQryFailed(&$errmsg = '')
{
    $errmsg = isset($GLOBALS['excQryLastError']) ? $GLOBALS['excQryLastError'] : '';

    return ($errmsg !== '');
}

function getCustInf($Id)
{   
        //truncate en Total
        //$sql = " SELECT    'ESCUELA KEMPER URGATE SA DE CV' Nombre,'EKU9003173C9' Rfc, '21000' DomicilioFiscalReceptor,       
        //ABS(round(a.total_ttc + 0.0000000001, 2)) Total, //aoz reemplazar por la linea del total
        // $sql = " SELECT 
        //             CASE  
        //             WHEN a.type = 2 then b.nom
        //             else
        //                 ifnull(e.nom,b.nom)
        //             end Nombre
                    
        //             ,ifnull(e.siren,b.siren) Rfc,ifnull(e.zip,b.zip) DomicilioFiscalReceptor,     
        $sql = " SELECT    'ESCUELA KEMPER URGATE SA DE CV' Nombre,'EKU9003173C9' Rfc, '21000' DomicilioFiscalReceptor,  
                    ifnull(d.propouse,'') UsoCFDI ,round(total_ttc/multicurrency_total_ttc,2) as TipoCambio,
                    ABS(round(a.multicurrency_total_ht + 0.0000000001, 2)) SubTotal,
                    (case when f.fiscalreg = '' or  f.fiscalreg is null then c.fiscalreg else f.fiscalreg end) RegimenFiscalReceptor, d.export Exportacion, 
                    a.multicurrency_code Moneda, 
                    round(abs(round(abs(a.multicurrency_total_ht), 2) + round(abs(a.multicurrency_total_tva), 2) + round(abs(a.localtax1), 2) - round(abs(a.localtax2), 2)),2) Total,
                    (select 	 abs(round(sum(e.multicurrency_subprice * e.qty - e.multicurrency_total_ht)+ 0.0000000001,2))
                    from 	".MAIN_DB_PREFIX."facturedet e
                    where 	e.fk_facture = a.rowid group by e.fk_facture) Descuento, 
                    CASE  
                    WHEN a.type = 2 then 'PUE'
                    WHEN d.mofpaymt = 1 THEN 'PUE' 
                    WHEN d.mofpaymt = 2 THEN 'PPD'	
                    END MetodoPago, (select 	f.code_sat
                                        from 	".MAIN_DB_PREFIX."c_paiement f
                                        where f.id = a.fk_mode_reglement
                                    ) FormaPago, 
                    ifnull((select g.libelle
                            from 	".MAIN_DB_PREFIX."c_payment_term g
                            where 	g.rowid = a.fk_cond_reglement),'NA') CondicionesDePago, 
                    CASE a.type  
                    when 2 then 'E' 
                    else  'I' end TipoDeComprobante,
                   Concat( curdate() ,'T',DATE_FORMAT(DATE_SUB(DATE_ADD(NOW(), INTERVAL -2 HOUR),INTERVAL 000 MINUTE), '%H:%i:%S' ))  as Fecha2,
                   Concat( curdate() ,'T',DATE_FORMAT(DATE_SUB(DATE_ADD(NOW(), INTERVAL -2 HOUR),INTERVAL 000 MINUTE), '%H:%i:%S' ))  as Fecha,
                    TRIM(replace(a.ref,'NC','')) Folio , '4.0' Version,
                    (select 	h.serie
                               from 	".MAIN_DB_PREFIX."serie h
                                where 	h.rowid = d.serie) Serie,
                    round(abs(a.multicurrency_total_tva + a.localtax1) + 0.0000000001,2) TotalImpuestosTrasladados,
                    abs(round(a.localtax2+ 0.0000000001,2)) TotalImpuestosRetenidos ,ifnull(d.periodic,'') Periodicidad, ifnull(d.mth,'') Meses, ifnull(d.peranio,0) Año,
                    d.typerelsat
            FROM ".MAIN_DB_PREFIX."facture a
            left join ".MAIN_DB_PREFIX."societe b on a.fk_soc = b.rowid
            left join ".MAIN_DB_PREFIX."societe_extrafields c on    b.rowid=c.fk_object
            left join ".MAIN_DB_PREFIX."facture_extrafields d on a.rowid = d.fk_object
            left join ".MAIN_DB_PREFIX."societe e on c.invto = e.rowid
            left join ".MAIN_DB_PREFIX."societe_extrafields f on    e.rowid= f.fk_object
            where a.rowid= ".$Id;      
        //echo $sql;
      return excQry($sql);
    
}
function getDtlInv($id)
{
    // ifnull(b.taxobjct,d.taxobjct)
    $sql = " select  a.fk_facture, 
                a.rowid,a.vat_src_code, 
            ifnull(d.prodservid,'01010101') ClaveProdServ, 
            ifnull(e.UOMId,'ACT') ClaveUnidad, 
            ifnull(a.fk_product,'Servicio') NoIdentificacion,
            round(a.qty,6) Cantidad,
            ifnull(e.code,'Serv') Unidad,
            a.description Descripcion,
            round(a.subprice,6) ValorUnitario,
            round(a.subprice* a.qty,6) Importe, 
            round((((a.subprice) * (a.remise_percent / 100))*a.qty),6) Descuento,
            '02' ObjetoImp,
            (a.qty*c.weight) as weight
            from ".MAIN_DB_PREFIX."facturedet a 
            left join ".MAIN_DB_PREFIX."facturedet_extrafields b on a.rowid=b.fk_object 
            left join ".MAIN_DB_PREFIX."product c on a.fk_product=c.rowid 
            left join ".MAIN_DB_PREFIX."product_extrafields d on c.rowid=d.fk_object 
            left join ".MAIN_DB_PREFIX."c_units e on d.udm= e.rowid 
            where a.fk_facture = ".$id;
    
    return excQry($sql);
    
}

function getDtlgrpInv($id, $fk_product)
{
    // ifnull(b.taxobjct,d.taxobjct)
    $sql = "SELECT  
                a.fk_facture, 
                MIN(a.rowid) AS rowid,
                MIN(a.vat_src_code) AS vat_src_code,
                IFNULL(d.prodservid, '01010101') AS ClaveProdServ, 
                IFNULL(e.UOMId, 'ACT') AS ClaveUnidad, 
                IFNULL(a.fk_product, 'Servicio') AS NoIdentificacion,
                ROUND(SUM(a.qty), 6) AS Cantidad,
                IFNULL(e.code, 'Serv') AS Unidad,
                MIN(a.description) AS Descripcion,
                ROUND(AVG(a.subprice), 6) AS subprice,
                ROUND(SUM(a.subprice * a.qty), 6) AS Importe,
                ROUND(SUM(((a.subprice) * (a.remise_percent / 100)) * a.qty), 6) AS Descuento,
                '02' AS ObjetoImp,
                ROUND(SUM(a.qty * c.weight), 6) AS weight
                FROM " . MAIN_DB_PREFIX . "facturedet a 
                LEFT JOIN " . MAIN_DB_PREFIX . "facturedet_extrafields b ON a.rowid = b.fk_object 
                LEFT JOIN " . MAIN_DB_PREFIX . "product c ON a.fk_product = c.rowid 
                LEFT JOIN " . MAIN_DB_PREFIX . "product_extrafields d ON c.rowid = d.fk_object 
                LEFT JOIN " . MAIN_DB_PREFIX . "c_units e ON d.udm = e.rowid 
                WHERE a.fk_facture = " . $id . 
                " and a.fk_product = ". $fk_product . "
                 GROUP BY a.fk_facture, a.fk_product";
    
    return excQry($sql);
    
}






function getCustInfPym($Id)
{   
    //        $sql = " SELECT    'ESCUELA KEMPER URGATE SA DE CV' nom,'EKU9003173C9' siren, '21000' zip,           
    
    //    $sql = "select c.nom,  c.siren, zip,d.propouse,   d.fiscalreg,
        $sql = "select ifnull(f.nom,c.nom) nom,ifnull(f.siren,c.siren) siren,ifnull(f.zip,c.zip) zip, ifnull(d.propouse,'') propouse , d.fiscalreg,
            Concat( curdate() ,'T',DATE_FORMAT(DATE_SUB(DATE_ADD(NOW(), INTERVAL -2 HOUR),INTERVAL 000 MINUTE), '%H:%i:%S' )) as Fecha
            from 	".MAIN_DB_PREFIX."facture b 
            inner join ".MAIN_DB_PREFIX."societe c on b.fk_soc = c.rowid 
            inner join ".MAIN_DB_PREFIX."societe_extrafields d on c.rowid = d.fk_object 
            inner join ".MAIN_DB_PREFIX."paiement_facture e on b.rowid = e.fk_facture
            left join ".MAIN_DB_PREFIX."societe f on d.invto = f.rowid
            left join ".MAIN_DB_PREFIX."societe_extrafields g on    f.rowid= g.fk_object
            where 	e.fk_paiement = ".$Id;
    
    return excQry($sql);
}
function getDtlPym($id)
{
    $sql = "	select 	a.rowid NumOper, format(a.amount,2) monto, 'MXN' MonedaP, c.code_sat formpagop,a.ref pago,
    	 	cast((concat(SUBSTRING(a.datep,1,10),  'T'  , SUBSTRING(a.datep,12,9))) as char) fechapago, d.ref  folio, 
    		f.serie,format(((d.total_ttc - k.saldant +b.amount)-ifnull(l.nc,0)),2) impsaldoanterior, 
    		format((((d.total_ttc - k.saldant + b.amount)-ifnull(l.nc,0)) - b.amount ),2) saldoinsoluto, 
    		        format(b.amount,2) impPagado, b.num_parcial partialnum , CASE e.mofpaymt WHEN 1 THEN 'PUE' WHEN 2 THEN 'PPD' END MetodoPago, 
    		        d.multicurrency_code MonedaDR, e.uuid IdDocumento, e.propouse ,a.ref folioP, '01' as export, '02' as objetoimpdr,
                        '1' TipoCambioP, '1' EquivalenciaDR , d.rowid invRowId
    		from 	".MAIN_DB_PREFIX."paiement a 
                inner join ".MAIN_DB_PREFIX."paiement_facture b on a.rowid = b.fk_paiement 
                inner join ".MAIN_DB_PREFIX."c_paiement c on a.fk_paiement=c.id 
                inner join ".MAIN_DB_PREFIX."facture d on b.fk_facture=d.rowid 
                inner join ".MAIN_DB_PREFIX."facture_extrafields e on b.fk_facture=e.fk_object 
                inner join ".MAIN_DB_PREFIX."serie f on e.serie=f.rowid 
    		inner join (select 	b.fk_facture, sum(b.amount) saldant 
                        from 	".MAIN_DB_PREFIX."paiement a left join ".MAIN_DB_PREFIX."paiement_facture b 
    			on 		a.rowid = b.fk_paiement 
    			group by b.fk_facture) k on d.rowid = k.fk_facture 
    		left join (select fk_facture,sum(amount_ttc) nc 
                            from 	".MAIN_DB_PREFIX."societe_remise_except  
                            group by fk_facture ) l on d.rowid=l.fk_facture 
    		where a.rowid= ".$id;
            
    return excQry($sql);
    
}

function getTrasTax($id, $dtlId)
{    
    /*
        --            left join ".MAIN_DB_PREFIX."product c on b.fk_product=c.rowid 
        --            left join ".MAIN_DB_PREFIX."facture_extrafields d on a.rowid=d.fk_object 
        --            left join ".MAIN_DB_PREFIX."facturedet_extrafields i on b.rowid = i.fk_object 
        round(ifnull((b.total_tva / (case when b.tva_tx > 16 then 0.16 else (b.tva_tx/100) end)),b.total_ht),6) 
     */
    ///// Query cuando el unpuesto es con 17.28%
    /*echo $sql = " select  b.fk_facture, b.rowid, b.vat_src_code, 
    abs(round((case when b.tva_tx > 16 then (b.total_tva)/(b.total_tva/(b.total_ht+b.total_localtax1)) else b.total_ht end),6)) Base,
                    '002' Impuesto, 'Tasa' TipoFactor,
                    round(case when b.tva_tx > 16 then 0.16 else (b.tva_tx/100) end,6) TasaOCuota,
                    round(abs(b.total_tva),6) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            where b.fk_facture= ".$id."
            and b.rowid = ".$dtlId."
            and locate('IVA',b.vat_src_code) > 0
            union all
            select  b.fk_facture, b.rowid, b.vat_src_code,
                    abs(round(ifnull((b.total_localtax1 / (b.localtax1_tx /100)),b.total_ht),6)) Base,
                    '003' Impuesto, 'Tasa' TipoFactor,
                    round(ifnull((b.localtax1_tx/100),0.000000),6) TasaOCuota,
                    round(abs(b.total_localtax1),6) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            where b.fk_facture= ".$id."
            and b.rowid = ".$dtlId."
            and locate('IEP',b.vat_src_code) > 0 ";*/
            $sql = " select  b.fk_facture, b.rowid, b.vat_src_code,
            replace(format(abs(b.total_ht),6),',','') Base,            
                            '002' Impuesto, 'Tasa' TipoFactor,
                            format(case when b.tva_tx > 16 then 0.160000  else (CAST(b.tva_tx as DECIMAL(10,6))/100) end,6) TasaOCuota,
                            format((abs(b.total_ht) * (case when b.tva_tx > 16 then 0.160000 else (CAST(b.tva_tx as DECIMAL(10,6))/100) end)),6) Importe
                    from ".MAIN_DB_PREFIX."facture a 
                    inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
                    where b.fk_facture= ".$id."
                    and b.rowid = ".$dtlId."
                    and locate('IVA',b.vat_src_code) > 0
                    union all
                    select  b.fk_facture, b.rowid, b.vat_src_code,
                            replace(format(abs(b.total_ht),6),',','') Base,                    
                            '003' Impuesto, 'Tasa' TipoFactor,
                            format(ifnull((b.localtax1_tx/100),0.000000),6) TasaOCuota,
                            format((abs(b.total_ht) * ifnull((b.localtax1_tx/100),0.000000)),6) Importe
                    from ".MAIN_DB_PREFIX."facture a 
                    inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
                    where b.fk_facture= ".$id."
                    and b.rowid = ".$dtlId."
                    and locate('IEP',b.vat_src_code) > 0 ";
    /*
            left join ".MAIN_DB_PREFIX."product c on b.fk_product=c.rowid 
            left join ".MAIN_DB_PREFIX."facture_extrafields d on a.rowid=d.fk_object 
            left join ".MAIN_DB_PREFIX."facturedet_extrafields i on b.rowid = i.fk_object 
     */
    return excQry($sql);
    
}
function getTrasTaxIEPS($id, $dtlId)
{    
    /*
        --            left join ".MAIN_DB_PREFIX."product c on b.fk_product=c.rowid 
        --            left join ".MAIN_DB_PREFIX."facture_extrafields d on a.rowid=d.fk_object 
        --            left join ".MAIN_DB_PREFIX."facturedet_extrafields i on b.rowid = i.fk_object 
        round(ifnull((b.total_tva / (case when b.tva_tx > 16 then 0.16 else (b.tva_tx/100) end)),b.total_ht),6) 
     */
    ///// Query cuando el unpuesto es con 17.28%
     $sql = " select  b.fk_facture, b.rowid, b.vat_src_code, 
    round(round((case when b.tva_tx > 16 then (b.total_tva)/(b.total_tva/(b.total_ht+b.total_localtax1)) else b.total_ht end),6),2) Base,
                    '002' Impuesto, 'Tasa' TipoFactor,
                    round(case when b.tva_tx > 16 then 0.16 else (b.tva_tx/100) end,6) TasaOCuota,
                    round(abs(b.total_tva),6) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            where b.fk_facture= ".$id."
            and b.rowid = ".$dtlId."
            and locate('IVA',b.vat_src_code) > 0
            union all
            select  b.fk_facture, b.rowid, b.vat_src_code,
                    abs(round(ifnull((b.total_localtax1 / (b.localtax1_tx /100)),b.total_ht),6)) Base,
                    '003' Impuesto, 'Tasa' TipoFactor,
                    round(ifnull((b.localtax1_tx/100),0.000000),6) TasaOCuota,
                    round(abs(b.total_localtax1),6) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            where b.fk_facture= ".$id."
            and b.rowid = ".$dtlId."
            and locate('IEP',b.vat_src_code) > 0 ";
           
    /*
            left join ".MAIN_DB_PREFIX."product c on b.fk_product=c.rowid 
            left join ".MAIN_DB_PREFIX."facture_extrafields d on a.rowid=d.fk_object 
            left join ".MAIN_DB_PREFIX."facturedet_extrafields i on b.rowid = i.fk_object 
     */
    return excQry($sql);
    
}


function getTrasTotTax($id)
{    
    //round((sum(b.total_tva)/0.160000)+ 0.0000000001,2)
    /*$sql = " select 	b.fk_facture,
                            abs(sum(round((b.total_tva)/(b.total_tva/(b.total_ht+b.total_localtax1)) ,2)))  Base,
                            '002' Impuesto,
                            'Tasa' TipoFactor, 
                            case when b.tva_tx > 16 then 0.160000 else round((b.tva_tx/100),6) end TasaOCuota, 
                            abs(round(sum(b.total_tva)+ 0.0000000001,2)) Importe 
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            where b.fk_facture= ".$id." 
            and locate('IVA',b.vat_src_code) > 0 
            and case when b.tva_tx > 16 then 0.160000 else (b.tva_tx/100) end = 0.16
            group by b.fk_facture
            union all 
            select 	b.fk_facture, 
                            abs(round(sum(ifnull((b.total_tva / (b.tva_tx/100)),b.total_ht))+ 0.0000000001,2)) Base, 
                            '002' Impuesto, 
                            'Tasa' TipoFactor, 
                            case when b.tva_tx > 16 then 0.160000 else round((b.tva_tx/100),6) end TasaOCuota, 
                            abs(round(sum(b.total_tva)+ 0.0000000001,2)) Importe 
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            where b.fk_facture= ".$id." 
            and locate('IVA',b.vat_src_code) > 0 
            and case when b.tva_tx > 16 then 0.160000 else (b.tva_tx/100) end = 0.00
            group by b.fk_facture
            union all
            select 	b.fk_facture,  
                            abs(round((sum(b.total_localtax1)/0.080000)+ 0.0000000001,2)) Base, 
                            '003' Impuesto, 
                            'Tasa' TipoFactor, 
                            ifnull(round((b.localtax1_tx/100),6),0.000000) TasaOCuota, 
                            abs(round(sum(b.total_localtax1)+ 0.0000000001,2)) Importe 
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            where b.fk_facture= ".$id." 
            and locate('IEP',b.vat_src_code) > 0
            and ifnull((b.localtax1_tx/100),0.000000) = 0.080000
            group by b.fk_facture
            truncate(  sum(round((b.total_tva)/(b.total_tva/(b.total_ht+b.total_localtax1)) ,2)),6)  BaseOriginla, macv
 ";*/
     $sql = " select 	b.fk_facture,
            round(truncate( round(sum((b.total_tva)/(b.total_tva/(b.total_ht+b.total_localtax1)) ),2),6),2) Base,
            '002' Impuesto,
            'Tasa' TipoFactor, 
            cast(case when b.tva_tx > 16 then 0.160000 else round((CAST(b.tva_tx as DECIMAL(10,2))/100),6)  end as char ) TasaOCuota, 
            abs(round(sum(b.total_tva)+ 0.0000000001,2)) Importe 
        from ".MAIN_DB_PREFIX."facture a 
        inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
        where b.fk_facture= ".$id." 
        and locate('IVA',b.vat_src_code) > 0 
        and case when b.tva_tx > 16 then 0.160000 else (b.tva_tx/100) end = 0.160000
        GROUP BY b.fk_facture, b.tva_tx
        union all 
        select 	b.fk_facture, 
        abs(round(sum(ifnull((b.total_tva / (b.tva_tx/100)),b.total_ht))+ 0.0000000001,2)) Base, 
            '002' Impuesto, 
            'Tasa' TipoFactor, 
            cast( case when b.tva_tx > 16 then 0.160000 else  round((CAST(b.tva_tx as DECIMAL(10,2))/100),6)  end as char) TasaOCuota, 
            abs(round(sum(b.total_tva)+ 0.0000000001,2)) Importe 
        from ".MAIN_DB_PREFIX."facture a 
        inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
        where b.fk_facture= ".$id." 
        and locate('IVA',b.vat_src_code) > 0 
        and case when b.tva_tx > 16 then 0.160000 else (b.tva_tx/100) end = 0.00
        GROUP BY b.fk_facture, b.tva_tx
        union all
        select 	b.fk_facture,  
        abs(round((sum(b.total_localtax1)/0.080000)+ 0.0000000001,2)) Base, 
            '003' Impuesto, 
            'Tasa' TipoFactor, 
            ifnull(format((b.localtax1_tx/100),6),0.000000) TasaOCuota, 
            abs(round(sum(b.total_localtax1)+ 0.0000000001,2)) Importe 
        from ".MAIN_DB_PREFIX."facture a 
        inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
        where b.fk_facture= ".$id." 
        and locate('IEP',b.vat_src_code) > 0
        and ifnull((b.localtax1_tx/100),0.000000) = 0.080000
        GROUP BY b.fk_facture, b.localtax1_tx
        ";
    return excQry($sql);
    
}
function getRetTax($id, $dtlId)
{    
    $sql = " select b.fk_facture
                    ,abs(round(ifnull((b.total_localtax2 / (b.localtax2_tx /100)),b.total_ht),6)) Base,
                    '003' Impuesto,
                    'Tasa' TipoFactor,
                    round(ifnull(abs(b.localtax2_tx/100),0.000000),6) TasaOCuota,
                    abs(round(b.total_localtax2,6)) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."product c on b.fk_product=c.rowid 
            left join ".MAIN_DB_PREFIX."facture_extrafields d on a.rowid=d.fk_object 
            left join ".MAIN_DB_PREFIX."facturedet_extrafields i on b.rowid = i.fk_object 
            where b.fk_facture= ".$id."
            and b.rowid = ".$dtlId."
            and abs(b.localtax2_tx) = 8
            union all
            select  b.fk_facture
                    ,abs(round(ifnull((b.total_localtax2 / (b.localtax2_tx /100)),b.total_ht),6)) Base,
                    '002' Impuesto,
                    'Tasa' TipoFactor,
                    round(ifnull(abs(b.localtax2_tx/100),0.000000),6) TasaOCuota,
                    abs(round(b.total_localtax2,6)) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."product c on b.fk_product=c.rowid 
            left join ".MAIN_DB_PREFIX."facture_extrafields d on a.rowid=d.fk_object 
            left join ".MAIN_DB_PREFIX."facturedet_extrafields i on b.rowid = i.fk_object 
            where b.fk_facture= ".$id."
            and b.rowid = ".$dtlId."
            and abs(b.localtax2_tx) = 4
            ";
    //echo $sql;
    return excQry($sql);
    
}
function getRetTotTax($id)
{    
    $sql = " select abs(round(sum(ifnull((b.total_localtax2 / (b.localtax2_tx /100)),b.total_ht))+ 0.0000000001,2)) Base,
                    '003' Impuesto,
                    'Tasa' TipoFactor,
                    round(ifnull(abs(b.localtax2_tx/100),0.000000),6) TasaOCuota,
                    sum(abs(round(b.total_localtax2+ 0.0000000001,2))) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."product c on b.fk_product=c.rowid 
            left join ".MAIN_DB_PREFIX."facture_extrafields d on a.rowid=d.fk_object 
            left join ".MAIN_DB_PREFIX."facturedet_extrafields i on b.rowid = i.fk_object 
            where b.fk_facture= ".$id."            
            and abs(b.localtax2_tx) = 8
            group by b.fk_facture, b.localtax2_tx
            union all
            select  abs(ifnull(round(sum(ifnull((b.total_localtax2 / (b.localtax2_tx /100)),b.total_ht))+ 0.0000000001,2),0.000000)) Base, 
                    '002' Impuesto,
                    'Tasa' TipoFactor,
                    round(ifnull(abs(b.localtax2_tx/100),0.000000),6) TasaOCuota,
                    ifnull((abs(round(b.total_localtax2+ 0.0000000001,2))),0.00) Importe 
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."product c on b.fk_product=c.rowid 
            left join ".MAIN_DB_PREFIX."facture_extrafields d on a.rowid=d.fk_object 
            left join ".MAIN_DB_PREFIX."facturedet_extrafields i on b.rowid = i.fk_object 
            where b.fk_facture= ".$id."            
            and abs(b.localtax2_tx) = 4
            group by b.fk_facture, b.localtax2_tx
            ";
    return excQry($sql);    
}
function getRelType($id)
{
    $sql = "SELECT reltype ";
    $sql.= " FROM ".MAIN_DB_PREFIX."kshinvoice_rel ";
    $sql.= " where fk_facture_parent= '".$id."' group by reltype " ;
    
    return excQry($sql);
}
function getRelTypeNC($id)
{
    $sql =" SELECT c.typerelsat as reltype ";
    $sql.=" FROM ".MAIN_DB_PREFIX."facture a inner join ".MAIN_DB_PREFIX."facture b ";
    $sql.=" on       a.fk_facture_source=b.rowid left join ".MAIN_DB_PREFIX."facture_extrafields c ";
    $sql.=" on    a.rowid=c.fk_object ";
    $sql.=" where     a.rowid=".$id;
    $sql.= " group by c.typerelsat ";
    
    return excQry($sql);
}

function getRef($id)
{
    $sql = " select b.ref
    from ".MAIN_DB_PREFIX."facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture_source = b.rowid
    where a.rowid= ".$id;

    return excQry($sql);
}

function getRefInv($id)
{
    $sql = " select c.ref
    from ".MAIN_DB_PREFIX."kshinvoice_rel a 
    inner join ".MAIN_DB_PREFIX."facture_extrafields b on a.uuid = b.uuid
    inner join ".MAIN_DB_PREFIX."facture c on b.fk_object = c.rowid
    where a.fk_facture_parent= ".$id;

    return excQry($sql);
}


function getRelInv($where)
{
    $sql= "SELECT * ";
    $sql.= " FROM ".MAIN_DB_PREFIX."kshinvoice_rel ";
    $sql.= " where fk_facture_parent= '".$where;   
    
    return excQry($sql);   
}
function getRelNC($where)
{
    $sql =" SELECT c.typerelsat as reltype , d.uuid";
    $sql.=" FROM ".MAIN_DB_PREFIX."facture a ";
    $sql.=" inner join ".MAIN_DB_PREFIX."facture b  on a.fk_facture_source=b.rowid ";
    $sql.=" left join ".MAIN_DB_PREFIX."facture_extrafields c on a.rowid= c.fk_object ";
    $sql.=" left join ".MAIN_DB_PREFIX."facture_extrafields d on b.rowid= d.fk_object ";
    $sql.=" where     a.rowid=".$where;
    $sql.= " group by c.typerelsat "; 
//    echo $sql;
    return excQry($sql);   
}
function gethdPdfInv($id)
{
    $sql = "select 	
                ifnull(j.nom,d.nom) nom,ifnull(j.siren,d.siren)siren,ifnull(j.address,d.address) address, 
                ifnull(j.zip,d.zip) zip, ifnull(j.town,d.town)town,
                e.propouse, d.code_client, d.tva_intra,
		f.Descripcion,  e.mth, e.periodic, 
		e.peranio,
        (case when k.fiscalreg = '' or  k.fiscalreg is null then c.fiscalreg else k.fiscalreg end) fiscalreg,
        (case when l.description = '' or  l.description is null then g.description else l.description end) description,        
                b.rowid, b.fk_mode_reglement, e.mofpaymt, h.serie, b.ref, b.type, i.code_sat,
                e.export
            FROM ".MAIN_DB_PREFIX."facturedet a 
            inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture=b.rowid 
            left join ".MAIN_DB_PREFIX."societe_extrafields c on b.fk_soc=c.fk_object 
            left join ".MAIN_DB_PREFIX."societe d on c.fk_object=d.rowid 
            left join ".MAIN_DB_PREFIX."facture_extrafields e on b.rowid=e.fk_object 
            left join ".MAIN_DB_PREFIX."usocfdi f on e.propouse=f.usocfdi_id 
            left join ".MAIN_DB_PREFIX."FiscalRegimen g on g.fiscalreg = c.fiscalreg
            left join ".MAIN_DB_PREFIX."serie h on e.serie = h.rowid 
            left join ".MAIN_DB_PREFIX."c_paiement i on b.fk_mode_reglement = i.id 
            left join ".MAIN_DB_PREFIX."societe j on c.invto =j.rowid 
            left join ".MAIN_DB_PREFIX."societe_extrafields k on j.rowid= k.fk_object
            left join ".MAIN_DB_PREFIX."FiscalRegimen l on l.fiscalreg = k.fiscalreg
            where a.fk_facture='".$id."' group by a.fk_facture";
        
    return excQry($sql);   
}
function getDtlPdfInv($id, $line)
{
    $sql = " select  a.fk_facture, 
                a.rowid,
		ifnull(d.prodservid,'01010101') ClaveProdServ, 
		ifnull(e.UOMId,'ACT') ClaveUnidad, 
		ifnull(a.fk_product,'Servicio') NoIdentificacion,
		round(a.qty,6) Cantidad,
		ifnull(e.code,'Serv') Unidad,
		a.description Descripcion,
		round(a.subprice,6) ValorUnitario,
		round(a.subprice* a.qty,6) Importe, 
		round((((a.subprice) * (a.remise_percent / 100))*a.qty),6) Descuento,
		b.taxobjct ObjetoImp,
                (a.qty*c.weight) as weight,
                e.label as Unidad, c.ref, format(a.tva_tx,2) tva_tx
            from ".MAIN_DB_PREFIX."facturedet a 
            left join ".MAIN_DB_PREFIX."facturedet_extrafields b on a.rowid=b.fk_object 
            left join ".MAIN_DB_PREFIX."product c on a.fk_product=c.rowid 
            left join ".MAIN_DB_PREFIX."product_extrafields d on c.rowid=d.fk_object 
            left join ".MAIN_DB_PREFIX."c_units e on d.udm= e.rowid 
            where a.fk_facture = ".$id." 
            and a.ref = '".$line."'";

    return excQry($sql);
    
}
function getTtlPdfInv($id)
{
    $sql = " select  a.fk_facture , abs(sum(a.total_ht))Subtotal, 
				(
		select 	ifnull(ROUND(sum(total_tva)+ 0.0000000001,2) ,0.00)
		from 	".MAIN_DB_PREFIX."facturedet
		where 	fk_facture  = a.fk_facture
		and locate('IVA',vat_src_code) > 0
		and tva_tx > 0.00 
		)IVA16,
		(
		select 	ROUND(sum(total_tva)+ 0.0000000001,2)
		from 	".MAIN_DB_PREFIX."facturedet
		where 	fk_facture  = a.fk_facture
		and locate('IVA',vat_src_code) > 0
		and tva_tx = 0.00 
		)IVA0,
		(
		select 	ifnull(ROUND(sum(total_localtax1)+ 0.0000000001,2) ,0.00)
		from 	".MAIN_DB_PREFIX."facturedet
		where 	fk_facture  = a.fk_facture
		and locate('IEPS',vat_src_code) > 0
		and abs(localtax1_tx) > 0.00 
		)IEPS8,
		(
		select 	abs(ifnull(ROUND(sum(total_localtax2)+ 0.0000000001,2),0.00))
		from 	".MAIN_DB_PREFIX."facturedet
		where 	fk_facture  = a.fk_facture
		and locate('IEPS',vat_src_code) > 0
		and abs(localtax2_tx) >= 8.00 
		)IEPSRET8,
		(
		select 	abs(ifnull(ROUND(sum(total_localtax2)+ 0.0000000001,2),0.00)) 
		from 	".MAIN_DB_PREFIX."facturedet
		where 	fk_facture  = a.fk_facture
		and locate('IVA',vat_src_code) > 0
		and abs(localtax2_tx) < 8.00 
		)IVARET4,
		ROUND(sum(a.total_ttc)+ 0.0000000001,2) total,
		sum(a.qty*(	select abs(ifnull(ROUND(sum(weight)+ 0.0000000001,2),0.00)) 
					from ".MAIN_DB_PREFIX."product 
					where rowid=a.fk_product)) as weight, sum(a.qty) as qty
                
            from 	".MAIN_DB_PREFIX."facturedet  a
            where a.fk_facture = ".$id." 
            group by a.fk_facture ";

    return excQry($sql);    
}
function getGlbInf($id)
{
    $sql = " select  a.address, a.nom, a.town, ifnull(a.zip,'') zip, 
            (select label from ".MAIN_DB_PREFIX."c_country where rowid = a.fk_pays )Country
            from  ".MAIN_DB_PREFIX."societe a
            left join ".MAIN_DB_PREFIX."societe_extrafields b on a.rowid = b.fk_object
            where 	b.invto is not null
            and 	a.rowid = ".$id." ";

    return excQry($sql);
}

function getTotPymInf($id)
{
    /* se utilizo truncate ????? */
    $sql = " 
            select 
			round(sum(case 
				when abs(d.localtax2_tx) = 4 and locate('IVA',d.vat_src_code) > 0 
				then abs((d.total_localtax2/d.total_ttc) * ((d.total_ttc/ b.total_ttc)* a.amount)) 
				else '0.00' 
			END)+ 0.0000000001,2) TotalRetencionesIVA
        ,round(sum(case 
				when abs(d.localtax2_tx) = 8 and locate('IEP',d.vat_src_code) > 0 
				then abs((d.total_localtax2/d.total_ttc) * ((d.total_ttc/ b.total_ttc)* a.amount))
				else '0.00' 
             END)+ 0.0000000001,2)  TotalRetencionesIEPS 
        ,truncate(sum(case 
                                when d.tva_tx >= 16 and locate('IVA',d.vat_src_code) > 0 
                                then ((((d.total_ttc/b.total_ttc)*a.amount)) / ((d.total_tva/d.total_ht) + 1) +d.total_localtax1)
                                else 0.00
                        end ) + 0.0000000001,2) TotalTrasladosBaseIVA16
        ,truncate(sum(	case when d.tva_tx >= 16 and locate('IVA',d.vat_src_code) > 0
                                then (d.total_tva/d.total_ttc) * ((d.total_ttc/ b.total_ttc)* a.amount)
                                else 0.00
                        end)+ 0.0000000001,2) TotalTrasladosImpuestoIVA16
        ,round(sum(case
                                when d.total_tva=0 and locate('IVA',d.vat_src_code) > 0
                                then (((d.total_ttc/b.total_ttc)*a.amount)) / ((d.total_tva/d.total_ht) + 1)
                                else 0.00
                        end ) + 0.0000000001,2) TotalTrasladosBaseIVA0
        ,round(sum(	case when d.tva_tx = 0 and locate('IVA',d.vat_src_code) > 0 
                                then (d.total_tva/d.total_ttc) * ((d.total_ttc/ b.total_ttc)* a.amount)
                                else 0.00
                        end)+ 0.0000000001,2) TotalTrasladosImpuestoIVA0
        ,round(sum((d.total_ttc/ b.total_ttc)* a.amount)+ 0.0000000001,2) MontoTotalPagos
            from ".MAIN_DB_PREFIX."paiement_facture a
            inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
            inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
            inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
            where a.fk_paiement = '".$id."'
            group by c.fk_paiement ";
            //echo $sql;
    return excQry($sql);
            
}
/* error en decimales EHM inicia */
function getTotPymInfIVA0($id)
{
    
    $sql = "
            
            select  
            round(	sum((	(a.amount/b.total_ttc) * d.total_ht)  ),2) + 
            round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax1/d.total_ttc))  ),2) base,
            round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_tva/d.total_ttc))  ),2)  importe,
            0.000000 tasaocuota,
            'Tasa' tipofactor,
            '002' impuesto,
            'T' tipoimpuesto
            from ".MAIN_DB_PREFIX."paiement_facture a 
            inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
            inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
            inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
            where 	a.fk_paiement = '".$id."'
            and 	d.tva_tx = 0
            and locate('IVA',d.vat_src_code) > 0 
            group by c.rowid
            ";

    return excQry($sql);
            
}
function getTotPymInfIVA16($id)
{
    // sum(round(	(	(a.amount/b.total_ttc) * d.total_ht)  ,2) ) + 
    //         sum(round(	(	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax1/d.total_ttc))  ,2)) base,
    //         sum(round(	(	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_tva/d.total_ttc))  ,2)) importe,//aoz
    
    $sql = "            
            select  
            ROUND(SUM(((a.amount / b.total_ttc) * d.total_ht)),2) + ROUND(SUM((((a.amount / b.total_ttc) * d.total_ttc) * (d.total_localtax1 / d.total_ttc))),2) base,
            ROUND(SUM((((a.amount / b.total_ttc) * d.total_ttc) * (d.total_tva / d.total_ttc))),2) importe,
            0.160000 tasaocuota,
            'Tasa' tipofactor,
            '002' impuesto,
            'T' tipoimpuesto
            from ".MAIN_DB_PREFIX."paiement_facture a 
            inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
            inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
            inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
            where 	a.fk_paiement = '".$id."'
            and 	d.tva_tx >= 16 
            and locate('IVA',d.vat_src_code) > 0 
            group by c.rowid
    ";

    return excQry($sql);
            
}
function getTotPymInfRETIEPS($id)
{
    
    $sql = "            
            select  
            sum(round(	(	(a.amount/b.total_ttc) * d.total_ht)  ,2) )  base,
            abs(sum(round(	(	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax2/d.total_ttc))  ,2))) importe,
            0.080000 tasaocuota,
            'Tasa' tipofactor,
            '003' impuesto,
            'R' tipoimpuesto
            from ".MAIN_DB_PREFIX."paiement_facture a 
            inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
            inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
            inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
            where 	a.fk_paiement = '".$id."'
            and 	abs(d.localtax2_tx) = 8
            and locate('IEPS',d.vat_src_code) > 0 
            group by c.rowid
    ";

    return excQry($sql);
            
}
function getTotPymInfRETIVA($id)
{
    
    $sql = "            
        select  
        sum(round(	(	(a.amount/b.total_ttc) * d.total_ht)  ,2) )  base,
        sum(round(	(	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax2/d.total_ttc))  ,2)) importe,
        0.040000 tasaocuota,
        'Tasa' tipofactor,
        '003' impuesto,
        'R' tipoimpuesto
        from ".MAIN_DB_PREFIX."paiement_facture a 
        inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
        inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
        inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
        where 	a.fk_paiement = '".$id."'
        and 	abs(d.localtax2_tx) = 4
        and locate('IVA',d.vat_src_code) > 0 
        group by c.rowid";
    

    return excQry($sql);
            
}
function getTotalsPymInf($id)
{
    //SELECT 	SUM(ROUND((a.amount / b.total_ttc) * d.total_ttc,2)) amount
    $sql = "            
    select  round(sum(	(a.amount/b.total_ttc) * d.total_ttc),2) amount
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'    
    group by c.rowid";

    return excQry($sql);
            
}

function getTotalTaxTransPym($id)
{
    // sum(round(	(	(a.amount/b.total_ttc) * d.total_ht)  ,2) ) + 
    // sum(round(	(	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax1/d.total_ttc))  ,2)) Base,
    // sum(round(	(	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_tva/d.total_ttc))  ,2)) Importe,   
    $sql = "
    select  a.fk_paiement,
    ROUND(SUM(((a.amount / b.total_ttc) * d.total_ht)),2) + ROUND(SUM((((a.amount / b.total_ttc) * d.total_ttc) * (d.total_localtax1 / d.total_ttc))),2) Base,
    ROUND(SUM((((a.amount / b.total_ttc) * d.total_ttc) * (d.total_tva / d.total_ttc))),2) Importe, 
    0.160000 TasaOCuota,
    'Tasa' TipoFactor,
    '002' Impuesto,
    'T' tipoimpuesto
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'
    and 	d.tva_tx >= 16 
    and locate('IVA',d.vat_src_code) > 0 
    group by c.rowid
    union all 
    select  a.fk_paiement,
    round(	sum((	(a.amount/b.total_ttc) * d.total_ht)  ),2) + 
    round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax1/d.total_ttc))  ),2) Base,
    round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_tva/d.total_ttc))  ),2)  Importe,
    0.000000 TasaOCuota,
    'Tasa' TipoFactor,
    '002' Impuesto,
    'T' tipoimpuesto
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'
    and 	d.tva_tx = 0
    and locate('IVA',d.vat_src_code) > 0 
    group by c.rowid
    union all 
    select  a.fk_paiement,
    round(	sum((	(a.amount/b.total_ttc) * d.total_ht)   ),2) Base,
    round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax1/d.total_ttc))  ),2) Importe,
    0.080000 TasaOCuota,
    'Tasa' TipoFactor,
    '003' Impuesto,
    'T' tipoimpuesto
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'
    and 	d.localtax1_tx = 8
    and locate('IEPS',d.vat_src_code) > 0 
    group by c.rowid
    ";

// truncate(sum(	(	((	a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax1/d.total_ttc))  ),2) Importe ,   

return excQry($sql);
            
}
function getTotalTaxRetnPym($id)
{
    //aoz aqui
    $sql = "    
    select   a.fk_paiement,
    sum(round(	(	(a.amount/b.total_ttc) * d.total_ht)  ,2) )  Base,
    abs(sum(round(	(	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax2/d.total_ttc))  ,2))) Importe,
    0.080000 TasaOCuota,
    'Tasa' TipoFactor,
    '003' Impuesto,
    'R' tipoimpuesto
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'
    and 	abs(d.localtax2_tx) = 8
    and locate('IEPS',d.vat_src_code) > 0 
    group by c.rowid
    union all 
    select  a.fk_paiement,
    sum(round(	(	(a.amount/b.total_ttc) * d.total_ht)  ,2) )  Base,
    sum(round(	(	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax2/d.total_ttc))  ,2)) Importe,
    0.040000 TasaOCuota,
    'Tasa' TipoFactor,
    '003' Impuesto,
    'R' tipoimpuesto
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'
    and 	abs(d.localtax2_tx) = 4
    and locate('IVA',d.vat_src_code) > 0 
    group by c.rowid";
    
return excQry($sql);
            
}

function getTotTaxTrasInv($id, $factId)
{
    $sql = "
    select  a.fk_paiement,
    round(	sum((	(a.amount/b.total_ttc) * d.total_ht)  ),2) + 
    round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax1/d.total_ttc))  ),2) Base,
    round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_tva/d.total_ttc))  ),2)  Importe,
    0.160000 TasaOCuota,
    'Tasa' TipoFactor,
    '002' Impuesto,
    'T' tipoimpuesto
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'
    and     b.rowid = ".$factId."
    and 	d.tva_tx >= 16 
    and locate('IVA',d.vat_src_code) > 0 
    group by c.rowid, b.rowid
    union all 
    select  a.fk_paiement,
    round(	sum((	(a.amount/b.total_ttc) * d.total_ht)  ),2) + 
    round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax1/d.total_ttc))  ),2) Base,
    round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_tva/d.total_ttc))  ),2)  Importe,
    0.000000 TasaOCuota,
    'Tasa' TipoFactor,
    '002' Impuesto,
    'T' tipoimpuesto
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'
    and     b.rowid = ".$factId."
    and 	d.tva_tx = 0
    and locate('IVA',d.vat_src_code) > 0 
    group by c.rowid, b.rowid
    union all 
    select  a.fk_paiement,
    round(	sum((	(a.amount/b.total_ttc) * d.total_ht)  ),2) Base,
    round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax1/d.total_ttc))  ),2) Importe,
    0.080000 TasaOCuota,
    'Tasa' TipoFactor,
    '003' Impuesto,
    'T' tipoimpuesto
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'
    and     b.rowid = ".$factId."
    and 	d.localtax1_tx = 8
    and locate('IEPS',d.vat_src_code) > 0 
    group by c.rowid, b.rowid
    ";
   //echo $sql;
    return excQry($sql);
            
}

function getTotalTaxRetnInv($id,$factId)
{
    //aoz aqui
    $sql = "    
    select   a.fk_paiement,
    round(	sum((	(a.amount/b.total_ttc) * d.total_ht)  ),2) Base,
    abs(round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax2/d.total_ttc))  ),2)) Importe,
    0.080000 TasaOCuota,
    'Tasa' TipoFactor,
    '003' Impuesto,
    'R' tipoimpuesto
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'
    and     b.rowid = ".$factId."
    and 	abs(d.localtax2_tx) = 8
    and locate('IEPS',d.vat_src_code) > 0 
    group by c.rowid, b.rowid
    union all 
    select  a.fk_paiement,
    round(	sum((	(a.amount/b.total_ttc) * d.total_ht)  ),2) Base,
    round(	sum((	((a.amount/b.total_ttc) * d.total_ttc)	* 	(d.total_localtax2/d.total_ttc))  ),2) Importe,
    0.040000 TasaOCuota,
    'Tasa' TipoFactor,
    '003' Impuesto,
    'R' tipoimpuesto
    from ".MAIN_DB_PREFIX."paiement_facture a 
    inner join ".MAIN_DB_PREFIX."facture b on a.fk_facture = b.rowid
    inner join ".MAIN_DB_PREFIX."paiement c on a.fk_paiement = c.rowid
    inner join ".MAIN_DB_PREFIX."facturedet d on b.rowid = d.fk_facture
    where 	a.fk_paiement = '".$id."'
    and     b.rowid = ".$factId."
    and 	abs(d.localtax2_tx) = 4
    and locate('IVA',d.vat_src_code) > 0 
    group by c.rowid, b.rowid ";

return excQry($sql);
            
}




/* ehmm decimales termina*/

function getTotTaxPymInf($id)
{
   
    $sql = " select 	c.fk_paiement,
                    truncate(sum(round(((((b.total_ttc / a.total_ttc) * c.amount) / ((b.total_tva / b.total_ht) + 1)) + b.total_localtax1),2)),2) Base ,  
                    '002' Impuesto, 
                    'Tasa' TipoFactor, 
                    0.160000 TasaOCuota, 
                    truncate(sum((((b.total_ttc/a.total_ttc)*c.amount) / ((b.total_tva/b.total_ht) + 1)) * ((b.total_tva/b.total_ht))),2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement = ".$id." 
            and locate('IVA',b.vat_src_code) > 0 
            and b.tva_tx >= 16 
            group by c.fk_paiement 
            union all 
            select 	c.fk_paiement, 
                            truncate(sum(((b.total_ttc/a.total_ttc)*c.amount) / ((b.total_tva/b.total_ht) + 1))+ 0.0000000001,2) Base,
                            '002' Impuesto,
                            'Tasa' TipoFactor,
                            0.000000 TasaOCuota,
                            truncate((sum(((b.total_ttc/a.total_ttc)*c.amount) / ((b.total_tva/b.total_ht) + 1)) * ((b.total_tva/b.total_ht))),2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement = ".$id."
            and locate('IVA',b.vat_src_code) > 0 
            and b.tva_tx <= 0.00 
            group by c.fk_paiement
            union all 
            select 	c.fk_paiement, 
                    truncate(sum(((b.total_ttc / a.total_ttc) * c.amount) / (((b.total_tva+ b.total_localtax1+b.total_localtax2) / b.total_ht) + 1)) + 0.0000000001,2) Base,
                    '003' Impuesto,
                    'Tasa' TipoFactor,
                    0.080000 TasaOCuota,
                    truncate((sum(((b.total_ttc / a.total_ttc) * c.amount) * ((b.total_localtax1 / b.total_ttc) ) )) ,2)  Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement= ".$id."
            and locate('IEP',b.vat_src_code) > 0 
            and ifnull((b.localtax1_tx/100),0.000000) = 0.080000 
            group by c.fk_paiement
            union all 
            select 	c.fk_paiement, 
                            truncate(sum(((b.total_ttc/a.total_ttc)*c.amount) / ((b.total_localtax2/b.total_ht) + 1))+ 0.0000000001,2) Base,
                            '003' Impuesto,
                            'Tasa' TipoFactor,
                            0.040000 TasaOCuota,
                            truncate((sum(((b.total_ttc/a.total_ttc)*c.amount) / ((b.total_localtax2/b.total_ht) + 1))*((b.total_localtax2/b.total_ht) )),2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement= ".$id."
            and locate('IEP',b.vat_src_code) > 0 
            and ifnull((b.localtax2_tx/100),0.000000) = 0.040000
            group by c.fk_paiement
    ";
    //echo $sql;
    return excQry($sql);
            
}
function getTotTaxRetPymInf($id)
{
   
    $sql = " 
            select 	c.fk_paiement, 
            round(sum(((b.total_ttc / a.total_ttc) * c.amount) / (((b.total_tva+ b.total_localtax1+b.total_localtax2) / b.total_ht) + 1)) + 0.0000000001,2) Base,
                            '003' Impuesto,
                            'Tasa' TipoFactor,
                            0.080000 TasaOCuota,
                            round((sum(((b.total_ttc / a.total_ttc) * c.amount) * (abs(b.total_localtax2 / b.total_ttc) ) )) ,2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement= ".$id."
            and locate('IEP',b.vat_src_code) > 0 
            and ifnull(abs(b.localtax2_tx/100),0.000000) = 0.080000
            group by c.fk_paiement
            union all
            select 	c.fk_paiement, 
            round(sum(((b.total_ttc / a.total_ttc) * c.amount) / (((b.total_tva+ b.total_localtax1+b.total_localtax2) / b.total_ht) + 1)) + 0.0000000001,2) Base,
                            '002' Impuesto,
                            'Tasa' TipoFactor,
                            0.040000 TasaOCuota,
                            round((sum(((b.total_ttc / a.total_ttc) * c.amount) * (abs(b.total_localtax2 / b.total_ttc) ) )) ,2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement= ".$id."
            and locate('IEP',b.vat_src_code) > 0 
            and ifnull(abs(b.localtax2_tx/100),0.000000) = 0.040000
            group by c.fk_paiement
    ";
    //echo $sql;
    return excQry($sql);
            
}
function getTotTaxPymInv($id, $factId)
{
   
    $sql = " select 	b.fk_facture,
                truncate(sum(((((b.total_ttc / a.total_ttc) * c.amount) / ((b.total_tva / b.total_ht) + 1))+b.total_localtax1)),2)Base,
                '002' Impuesto, 
                'Tasa' TipoFactor, 
                0.160000 TasaOCuota, 
                round(sum((((b.total_ttc / a.total_ttc) * c.amount) / ((b.total_tva / b.total_ht) + 1)) * ((b.total_tva / b.total_ht))),2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement = ".$id." 
            and  a.rowid = ".$factId."
            and locate('IVA',b.vat_src_code) > 0 
            and b.tva_tx >= 16.00
            group by c.fk_paiement,b.fk_facture, a.rowid
            union all 
            select 	b.fk_facture, 
                            truncate(sum(((b.total_ttc / a.total_ttc) * c.amount) / ((b.total_tva / b.total_ht) + 1) )+ 0.0000000001,2) Base,
                            '002' Impuesto,
                            'Tasa' TipoFactor,
                            0.000000 TasaOCuota,
                            truncate(sum((((b.total_ttc/a.total_ttc)*c.amount) / ((b.total_tva/b.total_ht) + 1)) * ((b.total_tva/b.total_ht))),2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement = ".$id."
            and  a.rowid = ".$factId."
            and locate('IVA',b.vat_src_code) > 0 
            and b.tva_tx <= 0.00 
            group by c.fk_paiement,b.fk_facture, a.rowid
            union all 
            select 	b.fk_facture, 
                            truncate(sum(((b.total_ttc / a.total_ttc) * c.amount) / (((b.total_tva+ b.total_localtax1+b.total_localtax2) / b.total_ht) + 1)) + 0.0000000001,2) Base,
                            '003' Impuesto,
                            'Tasa' TipoFactor,
                            0.080000 TasaOCuota,
                            truncate((sum(((b.total_ttc / a.total_ttc) * c.amount) * ((b.total_localtax1 / b.total_ttc) ) )) ,2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement= ".$id."
            and  a.rowid = ".$factId."
            and locate('IEP',b.vat_src_code) > 0 
            and ifnull((b.localtax1_tx/100),0.000000) = 0.080000 
            group by c.fk_paiement,b.fk_facture, a.rowid
            union all 
            select 	b.fk_facture, 
                            truncate(sum(((b.total_ttc/a.total_ttc)*c.amount) / ((b.total_localtax2/b.total_ht) + 1))+ 0.0000000001,2) Base,
                            '003' Impuesto,
                            'Tasa' TipoFactor,
                            0.040000 TasaOCuota,
                            truncate(sum((((b.total_ttc/a.total_ttc)*c.amount) / ((b.total_localtax2/b.total_ht) + 1)) * ((b.total_localtax2/b.total_ht))),2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement= ".$id."
            and  a.rowid = ".$factId."
            and locate('IEP',b.vat_src_code) > 0 
            and ifnull((b.localtax2_tx/100),0.000000) = 0.040000
            group by c.fk_paiement,b.fk_facture, a.rowid
    ";
    
    return excQry($sql);
            
}
function getTotTaxRetPymInv($id, $factId)
{
   
    $sql = " 
            select 	b.fk_facture, 
            round(sum(((b.total_ttc / a.total_ttc) * c.amount) / (((b.total_tva+ b.total_localtax1+b.total_localtax2) / b.total_ht) + 1)) + 0.0000000001,2) Base,
                            '003' Impuesto,
                            'Tasa' TipoFactor,
                            0.080000 TasaOCuota,
                            round((sum(((b.total_ttc / a.total_ttc) * c.amount) * (abs(b.total_localtax2 / b.total_ttc) ) )) ,2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement= ".$id."
            and  a.rowid = ".$factId."
            and locate('IEP',b.vat_src_code) > 0 
            and ifnull(abs(b.localtax2_tx/100),0.000000) = 0.080000 
            group by c.fk_paiement,b.fk_facture, a.rowid
            union all 
            select 	b.fk_facture, 
            round(sum(((b.total_ttc / a.total_ttc) * c.amount) / (((b.total_tva+ b.total_localtax1+b.total_localtax2) / b.total_ht) + 1)) + 0.0000000001,2) Base,
                            '002' Impuesto,
                            'Tasa' TipoFactor,
                            0.040000 TasaOCuota,
                            round((sum(((b.total_ttc / a.total_ttc) * c.amount) * (abs(b.total_localtax2 / b.total_ttc) ) )) ,2) Importe
            from ".MAIN_DB_PREFIX."facture a 
            inner join ".MAIN_DB_PREFIX."facturedet b on a.rowid=b.fk_facture 
            left join ".MAIN_DB_PREFIX."paiement_facture c on c.fk_facture = a.rowid
            where c.fk_paiement= ".$id."
            and  a.rowid = ".$factId."
            and locate('IEP',b.vat_src_code) > 0 
            and ifnull((b.localtax2_tx/100),0.000000) = 0.040000
            group by c.fk_paiement,b.fk_facture, a.rowid
    ";
    return excQry($sql);
            
}