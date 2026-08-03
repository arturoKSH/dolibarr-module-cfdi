<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/cfdi/lib/cfdi.lib.php';

$certName = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
$certPsw  = dolDecrypt(getDolGlobalString('MAIN_INFO_CFDI_CERT_PSW'));
$localPht = cfdiCertDir();
// Directorio del modulo: base para los .xslt. Antes se reusaba $localPht (el directorio
// de certificados), lo que armaba .../cfdi_Cert//xslt/origStr40.xslt -- una ruta inexistente,
// asi que la cadena original salia vacia y el sello se calculaba sobre nada.
$elcInvDir = DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv';
$xlst = "/xslt/origStr40.xslt";

updCertVal($localPht, $certName);

updCertNum($localPht, $certName);

function updCertVal($localPht,$certName)
{
    $certDir = cfdiCertDir($certName);
    if ($certDir === '') return;

    $txtPath = $certDir."Cert.txt";
    shell_exec("openssl x509 -inform DER -in ".escapeshellarg(cfdiCertFile($certName, '.cer'))." > ".escapeshellarg($txtPath));

    if (!file_exists($txtPath) || filesize($txtPath) == 0) return;

    $archivo = fopen($txtPath,'r');
    $fileLgh = filesize($txtPath);
    $val = fread($archivo, $fileLgh);
    fclose($archivo);

    $val = str_replace("-----BEGIN CERTIFICATE-----", "", $val);
    $val = str_replace("-----END CERTIFICATE-----", "", $val);
    $val = trim($val);
    updValConst('MAIN_INFO_CFDI_CERT_VAL',$val);
}

function updCertNum($localPht,$certName)
{
    $certDir = cfdiCertDir($certName);
    if ($certDir === '') return;

    $txtPath = $certDir."Serial.txt";
    shell_exec("openssl x509 -inform DER -in ".escapeshellarg(cfdiCertFile($certName, '.cer'))." -noout -serial > ".escapeshellarg($txtPath));

    if (!file_exists($txtPath) || filesize($txtPath) == 0) return;

    $archivo = fopen($txtPath,'r');
    $val = fgets($archivo);
    fclose($archivo);
    $val = str_replace("serial=", "", $val);    
    $val2 = "";
    for ($i = 0;$i< strlen($val);$i++)
    {
        if (($i % 2) != 0)
        $val2.= substr($val, $i, 1);
    
    }
    $val2= substr($val2, 0, 20);
   // echo strlen($val2)."|".$val2."holaaaa";
    updValConst('MAIN_INFO_CFDI_CERT',$val2);
    
}

function updValConst($name,$val)
{
    global $db;
    $update = "UPDATE ".MAIN_DB_PREFIX."const SET";
    $update.= "  value = replace(replace('".$db->escape($val)."',CHAR(10),''),CHAR(13),'')";
    $update.= " WHERE name = '".$db->escape($name)."' ";

    $up = execQry($update);
}

function execQry($sql)
{
    global $db;      
    $rslt = $db->query($sql);
    return $rslt; 
    
}

function createPem($localPht,$certName,$certPsw)
{   
    
//
$fil=$localPht.$certName."/".$certName.".key";
//$rsa->setPassword($certPsw);
//$rsa->load(file_get_contents($fil));
//    var_dump( openssl_pkey_get_private($fil, "-passin pass:".$certPsw." "));
//$sig = "";
//openssl_sign("Hola", $sig, $private, OPENSSL_ALGO_SHA256);
//$sello = base64_encode($sig);
//echo "private ". $private;

/*
$pk = file_get_contents($fil);
$handle = openssl_pkey_get_private($fil, hash('md5', $certPsw));
var_dump($handle) ;
$fil2=$localPht.$certName."/".$certName.".key.pem";
 $status = openssl_pkey_export_to_file($pk, $fil2, "passin pass:".$certPsw);

echo " nesrc ".$status;
*/

    if (cfdiCertDir($certName) === '') return;

    if (!file_exists(cfdiCertFile($certName, '.key.pem')))
        $salida=shell_exec("openssl pkcs8 -inform DER -in ".escapeshellarg(cfdiCertFile($certName, '.key'))." -passin ".escapeshellarg("pass:".$certPsw)." -out ".escapeshellarg(cfdiCertFile($certName, '.key.pem')));

    if (!file_exists(cfdiCertFile($certName, '.cer.pem')))
        $salida2=shell_exec("openssl x509 -inform DER -outform PEM -in ".escapeshellarg(cfdiCertFile($certName, '.cer'))." -pubkey -out ".escapeshellarg(cfdiCertFile($certName, '.cer.pem')));
}
function stampStr($localPht, $certName, $orgStr)
{
    $keyPem = cfdiCertFile($certName, '.key.pem');

    if (!file_exists($keyPem)) return '';

    $fp = fopen($keyPem, "r");
    $priv_key = fread($fp, 8192);
    fclose($fp);
    $pkeyid = openssl_get_privatekey($priv_key);

    $firma = "";
    openssl_sign($orgStr, $firma, $pkeyid, OPENSSL_ALGO_SHA256);
    $linea = base64_encode($firma);

    return $linea;
}
function getOrigStr($localPht,$xml, $xslt)
{
    // XSLT
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
    
    $xslFile = $localPht.$xslt;  
    
    $xsl = new DOMDocument();
    $xsl->load($xslFile);
    
    try
    {
    
        $proc = new XSLTProcessor;
        $proc->importStyleSheet($xsl);       
        $origStr = $proc->transformToXML($xml);
    }
    catch(Error $e) {
        echo $e->getMessage();
    }
    
    
    
    return $origStr;
    
    
}