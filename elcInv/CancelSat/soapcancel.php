<?php
function CancelaCfdi($db, $ruta)
{
    global $conf;

    $fname = $ruta;
    if (!file_exists($fname)) {
        die(PHP_EOL . "File not found" . PHP_EOL . PHP_EOL);
    }

    $handle = fopen($fname, "r");
    $sData = '';

    while (!feof($handle))
        $sData .= fread($handle, filesize($fname));
    fclose($handle);

    $b64 = base64_encode($sData);

    $cancelUrlTest = !empty($conf->global->CFDI_CANCEL_URL_TEST) ? $conf->global->CFDI_CANCEL_URL_TEST : 'https://develop.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL';
    $cancelUrlProd = !empty($conf->global->CFDI_CANCEL_URL_PROD) ? $conf->global->CFDI_CANCEL_URL_PROD : 'https://cfdi.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL';
    $cancelUrl = (!empty($conf->global->CFDI_ENV) && $conf->global->CFDI_ENV === 'prod') ? $cancelUrlProd : $cancelUrlTest;

    try {

        ini_set("soap.wsdl_cache_enabled", "0");
        $client2 = new SoapClient($cancelUrl, array('trace' => 1));
        //$client2 = new SoapClient("http://201.144.64.67:81/CancelacionServicesNew/CancelacionServices.asmx?WSDL", array('trace' => 1));
        
        //$auten2 = array('UserName' => 'crasa_t', 'Password' => '2x!D-Bf9Ln6=$Gp4');

        // $auten = array('UserName' => 'autofac_t', 'Password' => '6Pj!N+5sbQ$4=t8Y'); //version anterior
        // $params2 = array('minOccurs' => '0', 'maxOccurs' => '1', 'xmlBytes' => $sData, 'type' => 's:base64Binary');//version anterior
        
        // $result = $client2->__Call(//version anterior
        //     'CancelaCFDIs',//version anterior
        //     array('xmlBytes' => $params2),//version anterior
        //     null,//version anterior
        //     new SoapHeader("https://cfdi.timbrado.com.mx/cancelaservices", "AuthenticationHeader", $auten2)//version anterior
        // );

        $auten = array('UserName' => 'crasa_t', 'Password' => '2x!D-Bf9Ln6=$Gp4');
        $params2 = array('minOccurs' => '0', 'maxOccurs' => '1', 'xmlBytes' => $sData, 'type' => 's:base64Binary');

        $header = new SoapHeader(
            "https://cfdi.timbrado.com.mx/cancelaservices",
            "AuthenticationHeader",
            $auten
        );

        // Agregar el encabezado SOAP al cliente
        $client2->__setSoapHeaders($header);

        // Realizar la llamada al método 'CancelaCFDIs'
        $result = $client2->CancelaCFDIs($params2);

        if (!empty($result->CancelaCFDIsResult)) {
            $xml2 = simplexml_load_string($result->CancelaCFDIsResult->XmlAcuseSAT);
            //print_r($xml2);

            $fecha = substr($xml2['Fecha'], 0, 10) . ' ' . substr($xml2['Fecha'], 11, 8);

            $sql =  " INSERT INTO llx_kshCancelcfdi (FECHA, RFC, UUID, EstatusUUID, KeyName, DigestValue, SignatureValue, RSAKeyValue, Exponent) VALUES (";
            $sql .= " '" .$xml2['Fecha'] . "','" . $xml2['RfcEmisor'] . "','" . $xml2->Folios->UUID . "',";
            $sql .= "  '" . $xml2->Folios->EstatusUUID . "','" . $xml2->Signature->KeyInfo->KeyName . "','" . $xml2->Signature->SignedInfo->Reference->DigestValue . "',";
            $sql .= " '" . $xml2->Signature->SignatureValue . "','" . $xml2->Signature->KeyInfo->KeyValue->RSAKeyValue->Modulus . "','" . $xml2->Signature->KeyInfo->KeyValue->RSAKeyValue->Exponent . "')";
            //echo $sql;
            $db->query($sql);
            $_SESSION['CancelStatus']="yes";
         setEventMessages('Fue recibida la petición para cancelar el UUID.', null, 'mesgs');
        } else {
            print '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
            <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
            <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script> ';

            print '<div class="alert alert-danger alert-dismissible fade show" style="width:400px;";>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <strong></strong>Error, verifique:  ';
            print_r($result).'</div>';
        }
        
    } catch (SoapFault $fault2) {
        echo 'llegue3';
    exit;
        trigger_error("SOAP Fault: (faultcode: {$fault2->faultcode}, faultstring: {$fault2->faultstring})", E_USER_ERROR);
        echo 'error';
    }
    
    if (!empty($xml2)) {
        
        return $xml2;
    } else {
        return null;
    }
    
}

function AcepRechazoCfdi($db, $ruta)
{
    global $conf;

    $fname = $ruta;
    if (!file_exists($fname)) {
        die(PHP_EOL . "File not found" . PHP_EOL . PHP_EOL);
    }

    $handle = fopen($fname, "r");
    $sData = '';

    while (!feof($handle))
        $sData .= fread($handle, filesize($fname));
    fclose($handle);

    $b64 = base64_encode($sData);

    $cancelUrlTest = !empty($conf->global->CFDI_CANCEL_URL_TEST) ? $conf->global->CFDI_CANCEL_URL_TEST : 'https://develop.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL';
    $cancelUrlProd = !empty($conf->global->CFDI_CANCEL_URL_PROD) ? $conf->global->CFDI_CANCEL_URL_PROD : 'https://cfdi.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL';
    $cancelUrl = (!empty($conf->global->CFDI_ENV) && $conf->global->CFDI_ENV === 'prod') ? $cancelUrlProd : $cancelUrlTest;

    ini_set("soap.wsdl_cache_enabled", "0");

    $client = new SoapClient($cancelUrl, array('trace' => 1));

    try {
        $auten = array('UserName' => 'autofac_t', 'Password' => '6Pj!N+5sbQ$4=t8Y');
        $params = array('minOccurs' => '0', 'maxOccurs' => '1', 'xmlBytes' => $sData, 'type' => 's:base64Binary');
        /* Namespace */
        $result = $client->__Call(
            'AceptacionRechazo',
            array('xmlBytes' => $params),
            null,
            new SoapHeader("https://cfdi.timbrado.com.mx/cancelaservices", "AuthenticationHeader", $auten)
        );


        if (!empty($result->AceptacionRechazoResult)) {

            $xml2 = simplexml_load_string($result->AceptacionRechazoResult->XmlAcuseSAT);
            print_r($xml2);

            $fecha = substr($xml2['Fecha'], 0, 10) . ' ' . substr($xml2['Fecha'], 11, 8);
            $sql =  " INSERT INTO llx_kshacceptordeclinecfdi (FECHA, RFCEmisor, RFCReceptor, UUID, EstatusUUID, EstatusUUIDDesc, KeyName, DigestValue, SignatureValue, RSAKeyValue, Exponent) VALUES";
            $sql .= " ('" . $fecha . "','" . $xml2['RfcPac'] . "','" . $xml2['RfcReceptor'] . "','" . $xml2->Folios->UUID . "',";
            $sql .= "  '" . $xml2->Folios->EstatusUUID . "','" . $xml2->Folios['Respuesta'] . "','" . $xml2->Signature->KeyInfo->KeyName . "','" . $xml2->Signature->SignedInfo->Reference->DigestValue . "',";
            $sql .= " '" . $xml2->Signature->SignatureValue . "','" . $xml2->Signature->KeyInfo->KeyValue->RSAKeyValue->Modulus . "','" . $xml2->Signature->KeyInfo->KeyValue->RSAKeyValue->Exponent . "')";

            $db->query($sql);
        } else {
            var_dump($result);
        }
        return $xml2;
    } catch (SoapFault $fault) {
        trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
        echo 'error';
    }

    if (!empty($xml2)) {
        return $xml2;
    } else {
        return null;
    }
}

function RelacionadosCfdi($db, $ruta)
{
    global $conf;

    $fname = $ruta;
    if (!file_exists($fname)) {
        //echo "aqui";
        die(PHP_EOL . "File not found" . PHP_EOL . PHP_EOL);
    }

    $handle = fopen($fname, "r");
    $sData = '';

    while (!feof($handle))
        $sData .= fread($handle, filesize($fname));
    fclose($handle);

    $b64 = base64_encode($sData);

    $cancelUrlTest = !empty($conf->global->CFDI_CANCEL_URL_TEST) ? $conf->global->CFDI_CANCEL_URL_TEST : 'https://develop.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL';
    $cancelUrlProd = !empty($conf->global->CFDI_CANCEL_URL_PROD) ? $conf->global->CFDI_CANCEL_URL_PROD : 'https://cfdi.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL';
    $cancelUrl = (!empty($conf->global->CFDI_ENV) && $conf->global->CFDI_ENV === 'prod') ? $cancelUrlProd : $cancelUrlTest;

    ini_set("soap.wsdl_cache_enabled", "0");

    $client = new SoapClient($cancelUrl, array('trace' => 1));

    try {
        $auten = array('UserName' => 'autofac_t', 'Password' => '6Pj!N+5sbQ$4=t8Y');
        $params = array('minOccurs' => '0', 'maxOccurs' => '1', 'xmlBytes' => $sData, 'type' => 's:base64Binary');
        /* Namespace */
        $result = $client->__Call(
            'ConsultaRelacionados',
            array('xmlBytes' => $params),
            null,
            new SoapHeader("https://cfdi.timbrado.com.mx/cancelaservices", "AuthenticationHeader", $auten)
        );


        if (!empty($result->ConsultaRelacionadosResult)) {
            $xml2 = simplexml_load_string($result->ConsultaRelacionadosResult->XmlAcuseSAT);

            $fecha = substr($xml2['Fecha'], 0, 10) . ' ' . substr($xml2['Fecha'], 11, 8);

            $sql =  " INSERT INTO llx_kshcfdirelations (UUIDConsult, RFCRecept, RFCEmisor, Result, UUIDRel) VALUES ";
            $sql .= " ('" . $xml2->UuidConsultado . "','" . $xml2->UuidsRelacionadosHijos->UuidRelacionado->RfcReceptor . "','" . $xml2->UuidsRelacionadosHijos->UuidRelacionado->RfcEmisor . "',";
            $sql .= " '" . $xml2->Resultado . "','" . $xml2->UuidsRelacionadosHijos->UuidRelacionado->Uuid . "')";
            $db->query($sql);
        } else {
        }
        return $xml2;
    } catch (SoapFault $fault) {
        trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
        echo 'error';
    }

    if (!empty($xml2)) {
        return $xml2;
    } else {
        return null;
    }
}
function statusCFDI($db,$rfc){
    global $conf;

    $cancelUrlTest = !empty($conf->global->CFDI_CANCEL_URL_TEST) ? $conf->global->CFDI_CANCEL_URL_TEST : 'https://develop.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL';
    $cancelUrlProd = !empty($conf->global->CFDI_CANCEL_URL_PROD) ? $conf->global->CFDI_CANCEL_URL_PROD : 'https://cfdi.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL';
    $cancelUrl = (!empty($conf->global->CFDI_ENV) && $conf->global->CFDI_ENV === 'prod') ? $cancelUrlProd : $cancelUrlTest;

    ini_set("soap.wsdl_cache_enabled", "0");

    $client2 = new SoapClient($cancelUrl, array('trace' => 1));


    try {
        $auten =  array('UserName' => 'crasa_t', 'Password' => '2x!D-Bf9Ln6=$Gp4');

        $params = array('minOccurs' => '0', 'maxOccurs' => '1', 'rfcReceptor' => $rfc, 'type' => 's:string');
        /* Namespace */
        $result = $client2->__Call(
            'PeticionesPendientes',
            array('rfcReceptor' => $params),
            null,
            new SoapHeader("https://cfdi.timbrado.com.mx/cancelaservices", "AuthenticationHeader", $auten)
        );


        // if (!empty($result->AceptacionRechazoResult)) {

        //     $xml2 = simplexml_load_string($result->AceptacionRechazoResult->XmlAcuseSAT);
        //     print_r($xml2);

        //     $fecha = substr($xml2['Fecha'], 0, 10) . ' ' . substr($xml2['Fecha'], 11, 8);
        //     $sql =  " INSERT INTO llx_kshacceptordeclinecfdi (FECHA, RFCEmisor, RFCReceptor, UUID, EstatusUUID, EstatusUUIDDesc, KeyName, DigestValue, SignatureValue, RSAKeyValue, Exponent) VALUES";
        //     $sql .= " ('" . $fecha . "','" . $xml2['RfcPac'] . "','" . $xml2['RfcReceptor'] . "','" . $xml2->Folios->UUID . "',";
        //     $sql .= "  '" . $xml2->Folios->EstatusUUID . "','" . $xml2->Folios['Respuesta'] . "','" . $xml2->Signature->KeyInfo->KeyName . "','" . $xml2->Signature->SignedInfo->Reference->DigestValue . "',";
        //     $sql .= " '" . $xml2->Signature->SignatureValue . "','" . $xml2->Signature->KeyInfo->KeyValue->RSAKeyValue->Modulus . "','" . $xml2->Signature->KeyInfo->KeyValue->RSAKeyValue->Exponent . "')";

        //     $db->query($sql);
        // } else {
        //     var_dump($result);
        // }
        //return $xml2;
        
        print "<pre>";
        print_r($result);
        print_r(simplexml_load_string($result->PeticionesPendientesResult->XmlAcuseSAT));
        print "</pre>";
        $Res=simplexml_load_string($result->PeticionesPendientesResult->XmlAcuseSAT);
        //echo $Res['CodEstatus'];//$Res->UUID;
    } catch (SoapFault $fault) {
        trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
        echo 'error';
    }

    if (!empty($xml2)) {
        return $xml2;
    } else {
        return $xml2;
    }
}