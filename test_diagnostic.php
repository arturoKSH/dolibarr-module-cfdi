<?php
// Test diagnostic for Dolibarr CFDI Cert Loading and Signing

define("NOLOGIN", 1);
define("NOCSRFCHECK", 1);
define("NOIPCHECK", 1);

// Load Dolibarr environment
$res = 0;
if (file_exists(__DIR__ . "/../main.inc.php")) {
	$res = @include __DIR__ . "/../main.inc.php";
} elseif (file_exists(__DIR__ . "/../../main.inc.php")) {
	$res = @include __DIR__ . "/../../main.inc.php";
} elseif (file_exists(__DIR__ . "/../../../main.inc.php")) {
	$res = @include __DIR__ . "/../../../main.inc.php";
}

if (!$res) {
    // If not in Dolibarr context, mock it or show warning
    echo "Iniciando test en modo CLI autónomo...\n";
    define('DOL_DOCUMENT_ROOT', dirname(__DIR__, 2));
}

require_once __DIR__.'/lib/cfdi.lib.php';

echo "=== DIAGNOSTICO DE CONFIGURACION CFDI ===\n\n";

// 1. Verificar extensión OpenSSL
if (!extension_loaded('openssl')) {
    echo "[-] ERROR: La extensión 'openssl' de PHP no está cargada.\n";
    exit(1);
}
echo "[+] PHP OpenSSL: Cargada con éxito.\n";

// 2. Cargar constantes de Dolibarr
if (defined('DOL_DOCUMENT_ROOT')) {
    global $db, $conf;
    $certName = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
    $encryptedPsw = getDolGlobalString('MAIN_INFO_CFDI_CERT_PSW');
    $certPsw = dolDecrypt($encryptedPsw);
    
    echo "[+] Dolibarr Contexto: Detectado.\n";
    echo "[+] Certificado configurado: " . ($certName ? $certName : '(ninguno)') . "\n";
    echo "[+] Contraseña guardada: " . ($encryptedPsw ? 'Sí (Cifrada)' : 'No') . "\n";
} else {
    // Fallback para pruebas directas en local
    $certName = 'test_cert';
    $certPsw = 'TestCFDI2026!';
    echo "[!] Dolibarr Contexto: No detectado (usando mock 'test_cert')\n";
}

if (empty($certName)) {
    echo "[-] ERROR: No hay ningún certificado digital configurado en Dolibarr.\n";
    exit(1);
}

// 3. Verificar archivos en disco
$basePath = (defined('DOL_DOCUMENT_ROOT') ? DOL_DOCUMENT_ROOT : dirname(__DIR__, 2)) . '/custom/cfdi/elcInv/cfdi_Cert/' . $certName . '/';
echo "\nDirectorio de búsqueda: $basePath\n";

$files = array(
    'cer' => $certName . '.cer',
    'cer_pem' => $certName . '.cer.pem',
    'key' => $certName . '.key',
    'key_pem' => $certName . '.key.pem'
);

$missing = 0;
foreach ($files as $key => $filename) {
    $path = $basePath . $filename;
    if (file_exists($path)) {
        echo "[+] Archivo existente: $filename (" . filesize($path) . " bytes)\n";
    } else {
        echo "[-] ERROR: Archivo faltante: $filename\n";
        $missing++;
    }
}

if ($missing > 0) {
    echo "\n[-] ERROR: Faltan archivos requeridos para el sellado CFDI.\n";
    exit(1);
}

// 4. Cargar y verificar certificado publico (PEM)
$pemCertContent = file_get_contents($basePath . $files['cer_pem']);
$certRes = openssl_x509_read($pemCertContent);
if (!$certRes) {
    echo "[-] ERROR: No se pudo leer el archivo de certificado PEM.\n";
    exit(1);
}
echo "[+] Certificado PEM leído correctamente.\n";

$parsedCert = openssl_x509_parse($certRes);
if ($parsedCert) {
    echo "    Titular (CN): " . ($parsedCert['subject']['CN'] ?? 'Desconocido') . "\n";
    echo "    Emisor (O): " . ($parsedCert['issuer']['O'] ?? 'Desconocido') . "\n";
    echo "    Válido desde: " . date('Y-m-d H:i:s', $parsedCert['validFrom_time_t'] ?? 0) . "\n";
    echo "    Válido hasta: " . date('Y-m-d H:i:s', $parsedCert['validTo_time_t'] ?? 0) . "\n";
}

// 5. Cargar y verificar llave privada (PEM) con contraseña
$pemKeyContent = file_get_contents($basePath . $files['key_pem']);
$keyRes = openssl_pkey_get_private($pemKeyContent, $certPsw);
if (!$keyRes) {
    echo "[-] ERROR: No se pudo descifrar la llave privada con la contraseña configurada.\n";
    echo "    Verifica que la contraseña del certificado SAT sea la correcta.\n";
    exit(1);
}
echo "[+] Llave privada descifrada correctamente.\n";

// 6. Verificar correspondencia Certificado <-> Llave privada
$match = openssl_x509_check_private_key($certRes, $keyRes);
if ($match) {
    echo "[+] ÉXITO: El certificado coincide perfectamente con la llave privada.\n";
} else {
    echo "[-] ERROR: El certificado y la llave privada no coinciden entre sí.\n";
    exit(1);
}

// 7. Prueba de firma digital simulando sellado CFDI
$testData = "||4.0|Factura de Prueba|100.00|MXN||";
$signature = '';
$signOk = openssl_sign($testData, $signature, $keyRes, OPENSSL_ALGO_SHA256);

if ($signOk) {
    echo "[+] ÉXITO: Firma de sello digital generada correctamente (SHA256).\n";
    $seal = base64_encode($signature);
    echo "    Sello (base64, primeros 60 chars): " . substr($seal, 0, 60) . "...\n";
    
    // Verificar firma
    $verifyOk = openssl_verify($testData, $signature, $certRes, OPENSSL_ALGO_SHA256);
    if ($verifyOk === 1) {
        echo "[+] ÉXITO: Firma digital verificada correctamente por el certificado público.\n";
    } else {
        echo "[-] ERROR: No se pudo verificar la firma digital generada.\n";
        exit(1);
    }
} else {
    echo "[-] ERROR: Falló la generación de la firma digital (openssl_sign).\n";
    exit(1);
}

echo "\n*** TODO FUNCIONA EXCELENTEMENTE. EL MOTOR DE SELLADO CFDI ESTÁ LISTO PARA USARSE ***\n";
