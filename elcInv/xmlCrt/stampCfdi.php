<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$certName = $conf->global->MAIN_INFO_CFDI_CERT_NAME;
$certPsw = $conf->global->MAIN_INFO_CFDI_CERT_PSW;
$localPht = DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/cfdi_Cert/';
$xlst = "/xslt/origStr40.xslt";

updCertVal($localPht, $certName);

updCertNum($localPht, $certName);

function updCertVal($localPht,$certName)
{
    shell_exec("openssl x509 -inform DER -in ".$localPht.$certName."/".$certName.".cer  > ".$localPht.$certName."/Cert.txt");
    
    $archivo = fopen($localPht.$certName."/Cert.txt",'r');
    $fileLgh = filesize($localPht.$certName."/Cert.txt");
    if(filesize($localPht.$certName."/Cert.txt") >0)
        $val = fread($archivo, $fileLgh);
    
    $val = str_replace("-----BEGIN CERTIFICATE-----", "", $val);
    $val = str_replace("-----END CERTIFICATE-----", "", $val);
    $val = trim($val);
    //$val = mberegi_replace("[\n|\r|\n\r|\t|\|\x0B]", "",$val);
    fclose($archivo);   
    updValConst('MAIN_INFO_CFDI_CERT_VAL',$val);
}

function updCertNum($localPht,$certName)
{
    shell_exec("openssl x509 -inform DER -in ".$localPht.$certName."/".$certName.".cer -noout -serial > ".$localPht.$certName."/Serial.txt");
    $archivo = fopen($localPht.$certName."/Serial.txt",'r');
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
    $update = "UPDATE ".MAIN_DB_PREFIX."const SET";
    $update.= "  value = replace(replace('". $val."',CHAR(10),''),CHAR(13),'')";	
    $update.= " WHERE name = '".$name."' ";
    
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

    if (!file_exists($localPht.$certName."/".$certName.".key.pem")) 
        $salida=shell_exec("openssl pkcs8 -inform DER -in ".$localPht.$certName."/".$certName.".key -passin pass:".$certPsw." -out ".$localPht.$certName."/".$certName.".key.pem");

    if (!file_exists($localPht.$certName."/".$certName.".cer.pem"))
        $salida2=shell_exec("openssl x509 -inform DER -outform PEM -in ".$localPht.$certName."/".$certName.".cer -pubkey -out ".$localPht.$certName."/".$certName.".cer.pem");
    

    //return $localPht.$certName."/".$certName;   
}
function stampStr($localPht, $certName, $orgStr)
{       
    $keyPem =$localPht.$certName."/".$certName.".key.pem";

    $fp = fopen($keyPem, "r");
    $priv_key = fread($fp, 8192);
    fclose($fp);
    $pkeyid = openssl_get_privatekey($priv_key);

    // computar la firma
    $firma = "";
    openssl_sign($orgStr, $firma, $pkeyid, OPENSSL_ALGO_SHA256);
    $linea = base64_encode($firma);
    // liberar la clave de la memoria
    openssl_free_key($pkeyid);

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