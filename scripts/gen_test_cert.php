<?php
/**
 * Genera los 4 archivos de certificado SAT de prueba (autofirmados).
 *
 * Ejecutar dentro del contenedor Docker:
 *   docker exec -it <contenedor_app> php //var/www/html/custom/cfdi/scripts/gen_test_cert.php
 *
 * Genera en test_certs/:
 *   test_cert.cer      — certificado DER (binario)   → campo "Certificado DER"
 *   test_cert.cer.pem  — certificado PEM (texto)     → campo "Certificado PEM"
 *   test_cert.key      — llave PKCS8 DER cifrada     → campo "Llave PKCS8"
 *   test_cert.key.pem  — llave PEM cifrada           → campo "Llave PEM"
 */

if (!extension_loaded('openssl')) {
    die("ERROR: la extensión OpenSSL no está disponible en este PHP.\n");
}

$outDir = __DIR__.'/../test_certs/';
if (!is_dir($outDir) && !mkdir($outDir, 0750, true)) {
    die("ERROR: no se pudo crear el directorio $outDir\n");
}

// Bloquear acceso web
if (!file_exists($outDir.'.htaccess')) {
    file_put_contents($outDir.'.htaccess', "Require all denied\nphp_flag engine off\n");
}

$password = 'TestCFDI2026!';

// Llave privada RSA 2048
$privKey = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
if (!$privKey) {
    die("ERROR al generar llave privada: ".openssl_error_string()."\n");
}

// DN simulando RFC SAT
$dn = [
    'C'  => 'MX',
    'ST' => 'CDMX',
    'L'  => 'Ciudad de Mexico',
    'O'  => 'EMPRESA TEST SA DE CV',
    'CN' => 'TESE800101ABC',
];

$csr  = openssl_csr_new($dn, $privKey, ['digest_alg' => 'sha256']);
$cert = openssl_csr_sign($csr, null, $privKey, 365, ['digest_alg' => 'sha256']);

if (!$csr || !$cert) {
    die("ERROR al generar certificado: ".openssl_error_string()."\n");
}

// --- test_cert.cer  (DER / binario) ---
openssl_x509_export($cert, $certPem);
$certDer = base64_decode(preg_replace('/-----[^-]+-----|[\r\n]/', '', $certPem));
file_put_contents($outDir.'test_cert.cer', $certDer);

// --- test_cert.cer.pem  (PEM / texto) ---
file_put_contents($outDir.'test_cert.cer.pem', $certPem);

// --- test_cert.key.pem  (PEM cifrada con contraseña) ---
openssl_pkey_export($privKey, $keyPem, $password);
file_put_contents($outDir.'test_cert.key.pem', $keyPem);

// --- test_cert.key  (PKCS8 DER cifrado — simula el .key del SAT) ---
// Lo generamos convirtiendo el PEM a PKCS8 DER vía shell_exec
$keyPemFile = $outDir.'test_cert.key.pem';
$keyDerFile = $outDir.'test_cert.key';
$cmd = 'openssl pkcs8 -topk8 -inform PEM -outform DER'
     . ' -in '.escapeshellarg($keyPemFile)
     . ' -out '.escapeshellarg($keyDerFile)
     . ' -passout '.escapeshellarg('pass:'.$password)
     . ' -passin '.escapeshellarg('pass:'.$password)
     . ' 2>&1';
$out = shell_exec($cmd);
if (!file_exists($keyDerFile)) {
    // Fallback: guardar el PEM también como .key (funciona para pruebas si openssl binario no está)
    file_put_contents($keyDerFile, $keyPem);
    echo "Nota: openssl binario no disponible, .key guardado en formato PEM.\n";
    if ($out) echo "  ($out)\n";
}

// --- Verificar ---
$checkCert = openssl_x509_read($certPem);
$checkKey  = openssl_pkey_get_private($keyPem, $password);
$matches   = $checkCert && $checkKey && openssl_x509_check_private_key($checkCert, $checkKey);
$parsed    = openssl_x509_parse($certPem);

echo "\n=== Certificados de prueba generados ===\n\n";
echo "Directorio: $outDir\n\n";
printf("  %-22s  → Certificado DER  (campo 1)\n", 'test_cert.cer');
printf("  %-22s  → Certificado PEM  (campo 2)\n", 'test_cert.cer.pem');
printf("  %-22s  → Llave PKCS8      (campo 3)\n", 'test_cert.key');
printf("  %-22s  → Llave PEM        (campo 4)\n", 'test_cert.key.pem');
echo "\nContraseña: $password\n";
echo "RFC (CN)  : ".($parsed['subject']['CN'] ?? '?')."\n";
echo "Vence     : ".date('d/m/Y H:i', $parsed['validTo_time_t'])."\n";
echo "Cert/key  : ".($matches ? "OK ✓" : "FALLÓ ✗")."\n\n";
echo "=== Subir en CFDI > Configuración ===\n\n";
