<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2023 Admin SuperAdmin <orasles@orasles.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    cfdi/admin/setup.php
 * \ingroup cfdi
 * \brief   Cfdi setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
// files.lib.php: dol_delete_dir_recursive() vive aqui y main.inc.php no lo carga.
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once '../lib/cfdi.lib.php';
//require_once "../class/myclass.class.php";

// Translations
$langs->loadLangs(array("admin", "cfdi@cfdi"));

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('cfdisetup', 'globalsetup'));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Los certificados se movieron de custom/cfdi/elcInv/cfdi_Cert/ (alcanzable por web)
// a DOL_DATA_ROOT/cfdi/certs/. Migracion idempotente: solo actua si quedo la ruta antigua.
$certNameCurrent = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
if (!empty($certNameCurrent) && cfdiMigrateLegacyCertDir($certNameCurrent)) {
	setEventMessages($langs->trans('CfdiCertMigratedToDataRoot'), null, 'mesgs');
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'myobject';


$error = 0;
$setupnotempty = 0;

// Set this to 1 to use the factory to manage constants. Warning, the generated module will be compatible with version v15+ only
$useFormSetup = 1;

if (!class_exists('FormSetup')) {
	// For retrocompatibility Dolibarr < 16.0
	if (floatval(DOL_VERSION) < 16.0 && !class_exists('FormSetup')) {
		require_once __DIR__.'/../backport/v16/core/class/html.formsetup.class.php';
	} else {
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
	}
}

$formSetup = new FormSetup($db);


// Credenciales del PAC (Proveedor Autorizado de Certificación)
$item = $formSetup->newItem('CFDI_PAC_USER');
$item->nameText = $langs->transnoentities('CfdiPacUser');
$item->cssClass = 'minwidth300';

$item = $formSetup->newItem('CFDI_PAC_PASSWORD');
$item->nameText = $langs->transnoentities('CfdiPacPassword');
$item->setAsSecureKey();
$item->cssClass = 'minwidth300';

// Ambiente de timbrado
$currentEnv = !empty($conf->global->CFDI_ENV) ? $conf->global->CFDI_ENV : 'test';
$item = $formSetup->newItem('CFDI_ENV');
$item->nameText = $langs->trans('CfdiEnvLabel');
$selectHtml  = '<select name="CFDI_ENV" class="flat minwidth200">';
$selectHtml .= '<option value="test"'.($currentEnv === 'test' ? ' selected' : '').'>'.$langs->trans('CfdiEnvTest').'</option>';
$selectHtml .= '<option value="prod"'.($currentEnv === 'prod' ? ' selected' : '').'>'.$langs->trans('CfdiEnvProd').'</option>';
$selectHtml .= '</select>';
$item->fieldInputOverride  = $selectHtml;
$item->fieldOutputOverride = ($currentEnv === 'prod') ? $langs->trans('CfdiEnvProd') : $langs->trans('CfdiEnvTest');

$item = $formSetup->newItem('CFDI_STAMP_URL_TEST');
$item->nameText = $langs->trans('CfdiStampUrlTest');
$item->cssClass = 'minwidth500';
$item->fieldAttr = array('placeholder' => 'https://develop.timbrado.com.mx/wsTimbrado.asmx?WSDL');

$item = $formSetup->newItem('CFDI_STAMP_URL_PROD');
$item->nameText = $langs->trans('CfdiStampUrlProd');
$item->cssClass = 'minwidth500';
$item->fieldAttr = array('placeholder' => 'https://cfdi33.timbrado.com.mx/wsTimbrado.asmx?WSDL');

$item = $formSetup->newItem('CFDI_CANCEL_URL_TEST');
$item->nameText = $langs->trans('CfdiCancelUrlTest');
$item->cssClass = 'minwidth500';
$item->fieldAttr = array('placeholder' => 'https://develop.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL');

$item = $formSetup->newItem('CFDI_CANCEL_URL_PROD');
$item->nameText = $langs->trans('CfdiCancelUrlProd');
$item->cssClass = 'minwidth500';
$item->fieldAttr = array('placeholder' => 'https://cfdi.timbrado.com.mx/CancelacionServices/CancelacionServices.asmx?WSDL');

// Proveedor PAC: solo cambia el contrato SOAP (namespace/metodo) si el nuevo proveedor
// expone el mismo esquema (GeneraTimbre + AuthenticationHeader). Un PAC con un contrato
// distinto requiere su propio archivo de timbrado, esto no reemplaza eso.
$item = $formSetup->newItem('CFDI_PAC_PROVIDER');
$item->nameText = $langs->trans('CfdiPacProvider');
$item->cssClass = 'minwidth300';
$item->fieldAttr = array('placeholder' => 'ateb');
$item->helpText = $langs->trans('CfdiPacProviderHelp');

$item = $formSetup->newItem('CFDI_SOAP_NAMESPACE');
$item->nameText = $langs->trans('CfdiSoapNamespace');
$item->cssClass = 'minwidth500';
$item->fieldAttr = array('placeholder' => 'https://cfdi.timbrado.com.mx/timbradov2');

$item = $formSetup->newItem('CFDI_SOAP_METHOD');
$item->nameText = $langs->trans('CfdiSoapMethod');
$item->cssClass = 'minwidth300';
$item->fieldAttr = array('placeholder' => 'GeneraTimbre');


$setupnotempty += count($formSetup->items);


$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);


/*
 * Actions
 */

// For retrocompatibility Dolibarr < 15.0
if ( versioncompare(explode('.', DOL_VERSION), array(15)) < 0 && $action == 'update' && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
}

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

if ($action == 'updateMask') {
	$maskconst = GETPOST('maskconst', 'aZ09');
	$maskvalue = GETPOST('maskvalue', 'alpha');

	if ($maskconst && preg_match('/_MASK$/', $maskconst)) {
		$res = dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
} elseif ($action == 'specimen') {
	$modele = GETPOST('module', 'alpha');
	$tmpobjectkey = GETPOST('object');

	$tmpobject = new $tmpobjectkey($db);
	$tmpobject->initAsSpecimen();

	// Search template files
	$file = ''; $classname = ''; $filefound = 0;
	$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir) {
		$file = dol_buildpath($reldir."core/modules/cfdi/doc/pdf_".$modele."_".strtolower($tmpobjectkey).".modules.php", 0);
		if (file_exists($file)) {
			$filefound = 1;
			$classname = "pdf_".$modele."_".strtolower($tmpobjectkey);
			break;
		}
	}

	if ($filefound) {
		require_once $file;

		$module = new $classname($db);

		if ($module->write_file($tmpobject, $langs) > 0) {
			header("Location: ".DOL_URL_ROOT."/document.php?modulepart=cfdi-".strtolower($tmpobjectkey)."&file=SPECIMEN.pdf");
			return;
		} else {
			setEventMessages($module->error, null, 'errors');
			dol_syslog($module->error, LOG_ERR);
		}
	} else {
		setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
} elseif ($action == 'setmod') {
	// TODO Check if numbering module chosen can be activated by calling method canBeActivated
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'CFDI_'.strtoupper($tmpobjectkey)."_ADDON";
		dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
	}
} elseif ($action == 'set') {
	// Activate a model
	$ret = addDocumentModel($value, $type, $label, $scandir);
} elseif ($action == 'del') {
	$ret = delDocumentModel($value, $type);
	if ($ret > 0) {
		$tmpobjectkey = GETPOST('object');
		if (!empty($tmpobjectkey)) {
			$constforval = 'CFDI_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
			if ($conf->global->$constforval == "$value") {
				dolibarr_del_const($db, $constforval, $conf->entity);
			}
		}
	}
} elseif ($action == 'setdoc') {
	// Set or unset default model
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'CFDI_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
		if (dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity)) {
			// The constant that was read before the new set
			// We therefore requires a variable to have a coherent view
			$conf->global->$constforval = $value;
		}

		// We disable/enable the document template (into llx_document_model table)
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			$ret = addDocumentModel($value, $type, $label, $scandir);
		}
	}
} elseif ($action == 'unsetdoc') {
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'CFDI_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
		dolibarr_del_const($db, $constforval, $conf->entity);
	}
}

// ---------- inicio: handler para subir certificado .cer y llave .key ----------
if ($action == 'savecert' && $user->admin) {
    // Validacion de token CSRF obligatoria
    if (empty($_REQUEST['token']) || $_REQUEST['token'] !== $_SESSION['newtoken']) {
        setEventMessages($langs->trans('ErrorBadToken'), null, 'errors');
    } else {
        $certPsw = GETPOST('cert_psw', 'none');
        // Segunda autenticación: verificar contraseña del usuario logueado
        $confirmPwd = GETPOST('confirm_password', 'none');
        $tmpUser = new User($db);
        $tmpUser->fetch($user->id);
        // Dolibarr guarda el hash en pass_indatabase_crypted después de fetch()
        $storedHash = !empty($tmpUser->pass_indatabase_crypted) ? $tmpUser->pass_indatabase_crypted : $tmpUser->pass_crypted;
        if (function_exists('dol_verifyHash')) {
            $pwdOk = !empty($confirmPwd) && dol_verifyHash($confirmPwd, $storedHash);
        } else {
            $pwdOk = !empty($confirmPwd) && (
                password_verify($confirmPwd, $storedHash) ||
                dol_hash($confirmPwd) === $storedHash
            );
        }
        if (!$pwdOk) {
            setEventMessages('Contraseña de confirmación incorrecta.', null, 'errors');
        } elseif (
            empty($_FILES['cert_cer']['name'])     || empty($_FILES['cert_key']['name']) ||
            empty($_FILES['cert_cer_pem']['name']) || empty($_FILES['cert_key_pem']['name'])
        ) {
            setEventMessages('Se requieren los 4 archivos: .cer, .cer.pem, .key y .key.pem', null, 'errors');
        } else {
            $cer    = $_FILES['cert_cer'];
            $key    = $_FILES['cert_key'];
            $cerPem = $_FILES['cert_cer_pem'];
            $keyPem = $_FILES['cert_key_pem'];

            $extCer    = strtolower(pathinfo($cer['name'],    PATHINFO_EXTENSION));
            $extKey    = strtolower(pathinfo($key['name'],    PATHINFO_EXTENSION));
            $extCerPem = strtolower(pathinfo($cerPem['name'], PATHINFO_EXTENSION));
            $extKeyPem = strtolower(pathinfo($keyPem['name'], PATHINFO_EXTENSION));

            if ($extCer !== 'cer' || $extKey !== 'key' || $extCerPem !== 'pem' || $extKeyPem !== 'pem') {
                setEventMessages('Extensiones inválidas. Se esperan: .cer  .cer.pem  .key  .key.pem', null, 'errors');
            } elseif (
                $cer['error'] !== UPLOAD_ERR_OK    || $key['error'] !== UPLOAD_ERR_OK ||
                $cerPem['error'] !== UPLOAD_ERR_OK || $keyPem['error'] !== UPLOAD_ERR_OK
            ) {
                setEventMessages($langs->trans('UploadError'), null, 'errors');
            } else {
                $cerPemContent = file_get_contents($cerPem['tmp_name']);
                $keyPemContent = file_get_contents($keyPem['tmp_name']);

                // Validar con archivos PEM (PHP OpenSSL los lee nativamente sin conversión)
                $certRes = ($cerPemContent !== false) ? @openssl_x509_read($cerPemContent) : false;
                $pkeyRes = ($keyPemContent !== false) ? @openssl_pkey_get_private($keyPemContent, $certPsw) : false;

                if ($certRes === false || $pkeyRes === false) {
                    setEventMessages($langs->trans('InvalidCertOrPassword'), null, 'errors');
                } else {
                    // Nombre saneado: solo alfanumérico, guion y guion bajo
                    $certName = preg_replace('/[^A-Za-z0-9_-]/', '', basename(pathinfo($cer['name'], PATHINFO_FILENAME)));
                    $certName = substr($certName, 0, 64);
                    if ($certName === '' || $certName === '.' || $certName === '..') {
                        $certName = 'cert_'.time();
                    }

                    // Crea el directorio y escribe el .htaccess ANTES de mover los archivos.
                    $targetDir = cfdiEnsureCertDir($certName);
                    if ($targetDir === '') {
                        setEventMessages($langs->trans('CantCreateDir')." ".cfdiCertDir($certName), null, 'errors');
                        header('Location: '.$_SERVER['PHP_SELF']);
                        exit;
                    }

                    $dstCer    = cfdiCertFile($certName, '.cer');
                    $dstCerPem = cfdiCertFile($certName, '.cer.pem');
                    $dstKey    = cfdiCertFile($certName, '.key');
                    $dstKeyPem = cfdiCertFile($certName, '.key.pem');

                    $okCer    = move_uploaded_file($cer['tmp_name'],    $dstCer);
                    $okCerPem = move_uploaded_file($cerPem['tmp_name'], $dstCerPem);
                    $okKey    = move_uploaded_file($key['tmp_name'],    $dstKey);
                    $okKeyPem = move_uploaded_file($keyPem['tmp_name'], $dstKeyPem);

                    if ($okCer && $okCerPem && $okKey && $okKeyPem) {
                        // Si habia un certificado anterior con otro nombre, limpiar su carpeta (evita huerfanos)
                        $oldCertName = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
                        if (!empty($oldCertName) && $oldCertName !== $certName) {
                            $oldDir = cfdiCertDir($oldCertName);
                            if ($oldDir !== '' && is_dir($oldDir)) {
                                dol_delete_dir_recursive($oldDir);
                            }
                        }

                        // Guardar constantes Dolibarr para usar en el módulo (password cifrada en reposo)
                        dolibarr_set_const($db, 'MAIN_INFO_CFDI_CERT_NAME', $certName, 'chaine', 0, '', $conf->entity);
                        dolibarr_set_const($db, 'MAIN_INFO_CFDI_CERT_PSW', dolEncrypt($certPsw), 'chaine', 0, '', $conf->entity);

                        // Extraer fecha de vencimiento desde .cer.pem (ya es PEM, sin conversión)
                        $certInfo = false;
                        $certFileContent = file_get_contents($dstCerPem);
                        if ($certFileContent !== false) {
                            $certInfo = @openssl_x509_parse($certFileContent);
                            if ($certInfo && isset($certInfo['validTo_time_t'])) {
                                dolibarr_set_const($db, 'MAIN_INFO_CFDI_CERT_EXPIRY_TS', (int)$certInfo['validTo_time_t'], 'chaine', 0, '', $conf->entity);
                                dolibarr_del_const($db, 'CFDI_CERT_EXPIRY_NOTIF_SENT', $conf->entity);
                            }
                        }

                        setEventMessages($langs->trans('FilesSavedOK'), null, 'mesgs');
                        // Registrar en historial de reemplazos (sin contraseña)
                        $histJson = getDolGlobalString('CFDI_CERT_HISTORY');
                        $hist = ($histJson ? @json_decode($histJson, true) : array());
                        if (!is_array($hist)) $hist = array();
                        array_unshift($hist, array(
                            'date'      => date('Y-m-d H:i:s'),
                            'name'      => $certName,
                            'expiry'    => ($certInfo && isset($certInfo['validTo_time_t']) ? (int)$certInfo['validTo_time_t'] : 0),
                            'userId'    => (int)$user->id,
                            'userLogin' => $user->login,
                            'prev'      => $oldCertName,
                        ));
                        dolibarr_set_const($db, 'CFDI_CERT_HISTORY', json_encode(array_slice($hist, 0, 20)), 'chaine', 0, '', $conf->entity);
                    } else {
                        // Limpiar archivos parcialmente copiados
                        foreach (array($dstCer, $dstCerPem, $dstKey, $dstKeyPem) as $f) {
                            if (file_exists($f)) unlink($f);
                        }
                        setEventMessages($langs->trans('CantMoveUploadedFiles'), null, 'errors');
                    }
                }
            }
        }
    }
    // evitar repost al recargar
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}
// ---------- fin: handler para subir certificado .cer y llave .key ----------

// ---------- inicio: handler para eliminar el certificado configurado ----------
if ($action == 'deletecert' && $user->admin) {
    if (empty($_REQUEST['token']) || $_REQUEST['token'] !== $_SESSION['newtoken']) {
        setEventMessages($langs->trans('ErrorBadToken'), null, 'errors');
    } else {
        $certNameToDelete = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
        if (!empty($certNameToDelete)) {
            $certDir = cfdiCertDir($certNameToDelete);
            if ($certDir !== '' && is_dir($certDir)) {
                dol_delete_dir_recursive($certDir);
            }
            // Limpiar tambien la ruta antigua dentro de custom/ si quedo algo de una version previa.
            $legacyDir = DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/cfdi_Cert/'.cfdiCertSafeName($certNameToDelete).'/';
            if (cfdiCertSafeName($certNameToDelete) !== '' && is_dir($legacyDir)) {
                dol_delete_dir_recursive($legacyDir);
            }
        }
        dolibarr_del_const($db, 'MAIN_INFO_CFDI_CERT_NAME', $conf->entity);
        dolibarr_del_const($db, 'MAIN_INFO_CFDI_CERT_PSW', $conf->entity);
        setEventMessages($langs->trans('CertDeletedOK'), null, 'mesgs');
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}
// ---------- fin: handler para eliminar el certificado configurado ----------

// ---------- inicio: handler para mostrar contraseña del certificado ----------
if ($action == 'showpsw' && $user->admin) {
    if (empty($_REQUEST['token']) || $_REQUEST['token'] !== $_SESSION['newtoken']) {
        setEventMessages($langs->trans('ErrorBadToken'), null, 'errors');
    } else {
        $confirmPwd = GETPOST('confirm_password', 'none');
        $tmpUser = new User($db);
        $tmpUser->fetch($user->id);
        $storedHash = !empty($tmpUser->pass_indatabase_crypted) ? $tmpUser->pass_indatabase_crypted : $tmpUser->pass_crypted;
        if (function_exists('dol_verifyHash')) {
            $pwdOk = !empty($confirmPwd) && dol_verifyHash($confirmPwd, $storedHash);
        } else {
            $pwdOk = !empty($confirmPwd) && (
                password_verify($confirmPwd, $storedHash) ||
                dol_hash($confirmPwd) === $storedHash
            );
        }
        if (!$pwdOk) {
            setEventMessages('Contraseña de confirmación incorrecta.', null, 'errors');
        } else {
            $encryptedPsw = getDolGlobalString('MAIN_INFO_CFDI_CERT_PSW');
            if (!empty($encryptedPsw)) {
                $_SESSION['cfdi_decrypted_psw'] = dolDecrypt($encryptedPsw);
                setEventMessages('Contraseña descifrada con éxito.', null, 'mesgs');
            } else {
                setEventMessages('No hay contraseña guardada.', null, 'errors');
            }
        }
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}
// ---------- fin: handler para mostrar contraseña del certificado ----------

// ---------- inicio: handler para descargar archivos del certificado ----------
if ($action == 'downloadfile' && $user->admin) {
    if (empty($_REQUEST['token']) || $_REQUEST['token'] !== $_SESSION['newtoken']) {
        setEventMessages($langs->trans('ErrorBadToken'), null, 'errors');
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    } else {
        $confirmPwd = GETPOST('confirm_password', 'none');
        $tmpUser = new User($db);
        $tmpUser->fetch($user->id);
        $storedHash = !empty($tmpUser->pass_indatabase_crypted) ? $tmpUser->pass_indatabase_crypted : $tmpUser->pass_crypted;
        if (function_exists('dol_verifyHash')) {
            $pwdOk = !empty($confirmPwd) && dol_verifyHash($confirmPwd, $storedHash);
        } else {
            $pwdOk = !empty($confirmPwd) && (
                password_verify($confirmPwd, $storedHash) ||
                dol_hash($confirmPwd) === $storedHash
            );
        }
        if (!$pwdOk) {
            setEventMessages('Contraseña de confirmación incorrecta.', null, 'errors');
            header('Location: '.$_SERVER['PHP_SELF']);
            exit;
        } else {
            $certNameCfg = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
            $file = GETPOST('file', 'alpha');
            if (empty($certNameCfg) || empty($file)) {
                accessforbidden('Parámetros incorrectos');
            }
            $allowedFiles = array(
                $certNameCfg.'.cer',
                $certNameCfg.'.cer.pem',
                $certNameCfg.'.key',
                $certNameCfg.'.key.pem'
            );
            if (!in_array($file, $allowedFiles)) {
                accessforbidden('Archivo no permitido');
            }
            $filePath = cfdiCertDir($certNameCfg).$file;
            if (cfdiCertDir($certNameCfg) === '' || !file_exists($filePath)) {
                accessforbidden('El archivo no existe en el disco');
            }
            // Stream file download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    }
}
// ---------- fin: handler para descargar archivos del certificado ----------



/*
 * View
 */

$form = new Form($db);

$help_url = '';
$page_name = "CfdiSetup";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = cfdiAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, "cfdi@cfdi");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("CfdiSetupPage").'</span><br><br>';

// Alerta de vencimiento del certificado configurado
$currentCertName = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
if (!empty($currentCertName)) {
	$certStatus = cfdiParseCert($currentCertName);
	if (empty($certStatus['error'])) {
		if ($certStatus['expired']) {
			print '<div class="warning">'.$langs->trans('CertExpiredWarning', abs($certStatus['daysLeft'])).'</div><br>';
		} elseif ($certStatus['daysLeft'] <= 30) {
			print '<div class="warning">'.$langs->trans('CertExpiringWarning', $certStatus['daysLeft']).'</div><br>';
		}
	}
}

// Modal de confirmación de contraseña del usuario
print '<div id="cfdi-pwd-modal" style="display:none;position:fixed;z-index:10000;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.45);">';
print '  <div style="background:#fff;margin:14% auto;padding:24px 32px;max-width:440px;border-radius:4px;box-shadow:0 8px 28px rgba(0,0,0,0.22);">';
print '    <h4 style="margin-top:0"><span class="fas fa-lock" style="color:#888;margin-right:6px"></span>Confirmar identidad</h4>';
print '    <p id="cfdi-pwd-modal-text" style="color:#555;margin-bottom:14px">Por seguridad, ingresa tu contraseña de Dolibarr.</p>';
print '    <input type="password" id="cfdi-confirm-pwd" class="flat minwidth300" placeholder="Tu contraseña de acceso" autocomplete="current-password">';
print '    <div style="margin-top:18px;text-align:right;">';
print '      <button type="button" class="butActionRefused" onclick="document.getElementById(\'cfdi-pwd-modal\').style.display=\'none\'">Cancelar</button>';
print '      &nbsp;';
print '      <button type="button" class="butAction" onclick="cfdiSubmitWithPwd()">Confirmar</button>';
print '    </div>';
print '  </div>';
print '</div>';
print '<script>';
print 'var cfdiModalAction = "save";';
print 'var cfdiModalArg = "";';
print 'function cfdiOpenModal(action, arg, msg){';
print '  cfdiModalAction = action;';
print '  cfdiModalArg = arg || "";';
print '  if (msg) document.getElementById("cfdi-pwd-modal-text").innerText = msg;';
print '  document.getElementById("cfdi-confirm-pwd").value="";';
print '  document.getElementById("cfdi-pwd-modal").style.display="block";';
print '  setTimeout(function(){document.getElementById("cfdi-confirm-pwd").focus();},80);';
print '}';
print 'function cfdiSubmitWithPwd(){';
print '  var p=document.getElementById("cfdi-confirm-pwd").value;';
print '  if(!p){alert("Ingresa tu contraseña.");return;}';
print '  if (cfdiModalAction === "save") {';
print '    document.getElementById("cfdi-hidden-pwd").value=p;';
print '    document.getElementById("cfdi-cert-form").submit();';
print '  } else if (cfdiModalAction === "showpsw") {';
print '    document.getElementById("cfdi-action-showpsw-pwd").value=p;';
print '    document.getElementById("cfdi-form-showpsw").submit();';
print '  } else if (cfdiModalAction === "download") {';
print '    document.getElementById("cfdi-action-download-pwd").value=p;';
print '    document.getElementById("cfdi-action-download-file").value=cfdiModalArg;';
print '    document.getElementById("cfdi-form-download").submit();';
print '  }';
print '}';
print 'document.addEventListener("DOMContentLoaded",function(){var el=document.getElementById("cfdi-confirm-pwd");if(el)el.addEventListener("keydown",function(e){if(e.key==="Enter")cfdiSubmitWithPwd();});});';
print '</script>';

// Formulario oculto para revelar password
print '<form id="cfdi-form-showpsw" method="post" action="'.$_SERVER['PHP_SELF'].'?action=showpsw&token='.newToken().'">';
print '<input type="hidden" name="confirm_password" id="cfdi-action-showpsw-pwd" value="">';
print '</form>';

// Formulario oculto para descargar archivos
print '<form id="cfdi-form-download" method="post" action="'.$_SERVER['PHP_SELF'].'?action=downloadfile&token='.newToken().'">';
print '<input type="hidden" name="confirm_password" id="cfdi-action-download-pwd" value="">';
print '<input type="hidden" name="file" id="cfdi-action-download-file" value="">';
print '</form>';

// Formulario para subir certificado .cer/.pem y llave .key/.pem
print '<h4>'.$langs->transnoentities('UploadCertAndKey').'</h4>';
print '<form id="cfdi-cert-form" method="post" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'?action=savecert&token='.newToken().'">';
print '<input type="hidden" id="cfdi-hidden-pwd" name="confirm_password" value="">';
print '<table class="border" width="100%">';
print '<tr class="liste_titre"><td colspan="2" style="padding:6px 8px">Certificado SAT</td></tr>';
print '<tr><td width="240">Certificado DER <span style="color:#888;font-size:.85em">(.cer)</span></td><td><input type="file" name="cert_cer" accept=".cer" required></td></tr>';
print '<tr><td>Certificado PEM <span style="color:#888;font-size:.85em">(.cer.pem)</span></td><td><input type="file" name="cert_cer_pem" accept=".pem" required></td></tr>';
print '<tr class="liste_titre"><td colspan="2" style="padding:6px 8px">Llave privada</td></tr>';
print '<tr><td>Llave PKCS8 <span style="color:#888;font-size:.85em">(.key)</span></td><td><input type="file" name="cert_key" accept=".key" required></td></tr>';
print '<tr><td>Llave PEM <span style="color:#888;font-size:.85em">(.key.pem)</span></td><td><input type="file" name="cert_key_pem" accept=".pem" required></td></tr>';
print '<tr class="liste_titre"><td colspan="2" style="padding:6px 8px">Acceso</td></tr>';
print '<tr><td>'.$langs->transnoentities('CertPassword').'</td><td><input type="password" name="cert_psw" autocomplete="new-password"></td></tr>';
print '<tr><td></td><td><button class="butAction" type="button" onclick="cfdiOpenModal(\'save\', \'\', \'Por seguridad, ingresa tu contraseña de Dolibarr para guardar el certificado.\')">'.$langs->transnoentities('Save').'</button></td></tr>';
print '</table>';
print '</form>';

print '<br>';

// Mostrar estado del certificado SAT actualmente configurado
$certNameCfg   = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
$certExpiryCfg = (int) getDolGlobalString('MAIN_INFO_CFDI_CERT_EXPIRY_TS');
if (!empty($certNameCfg)) {
    print '<h4>Certificado SAT actual</h4>';
    print '<div class="div-table-responsive" style="max-width: 900px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; overflow: hidden;">';
    print '<table class="noborder" style="width: 100%; border-collapse: collapse; margin: 0;">';
    
    // Nombre
    print '<tr style="border-bottom: 1px solid #edf2f7;">';
    print '<td width="200" style="padding: 14px 18px; font-weight: bold; background: #f8fafc; color: #4a5568;"><span class="fa fa-info-circle" style="margin-right: 8px; color: #718096;"></span>Nombre</td>';
    print '<td style="padding: 14px 18px; color: #2d3748; font-weight: 500;">'.dol_escape_htmltag($certNameCfg).'</td>';
    print '</tr>';
    
    // Vence
    print '<tr style="border-bottom: 1px solid #edf2f7;">';
    print '<td style="padding: 14px 18px; font-weight: bold; background: #f8fafc; color: #4a5568;"><span class="fa fa-calendar-alt" style="margin-right: 8px; color: #718096;"></span>Vence</td>';
    if ($certExpiryCfg > 0) {
        $nowTsCfg   = time();
        $daysCfg    = (int) round(($certExpiryCfg - $nowTsCfg) / 86400);
        $expiredCfg = ($nowTsCfg > $certExpiryCfg);
        if ($expiredCfg)          { $clrCfg = '#d9534f'; $lblCfg = 'VENCIDO'; }
        elseif ($daysCfg <= 30)   { $clrCfg = '#f0ad4e'; $lblCfg = 'Vence en '.$daysCfg.' días'; }
        else                      { $clrCfg = '#22c55e'; $lblCfg = 'Vigente ('.$daysCfg.' días restantes)'; }
        print '<td style="padding: 14px 18px; color: #2d3748;">';
        print '<b style="font-size: 1.1em; color: #1a202c;">'.date('d/m/Y', $certExpiryCfg).'</b>';
        print '<span style="background: '.$clrCfg.'15; color: '.$clrCfg.'; font-weight: bold; padding: 4px 10px; border-radius: 12px; font-size: 0.85em; margin-left: 12px; border: 1px solid '.$clrCfg.'30; display: inline-block;">'.$lblCfg.'</span>';
        print '</td>';
    } else {
        print '<td style="padding: 14px 18px;"><span style="background: #fef3c7; color: #d97706; font-weight: bold; padding: 4px 10px; border-radius: 12px; font-size: 0.85em; border: 1px solid #fde68a;">No registrada — vuelva a subir el certificado para registrar la fecha</span></td>';
    }
    print '</tr>';

    // Contraseña
    $decryptedPsw = '';
    if (!empty($_SESSION['cfdi_decrypted_psw'])) {
        $decryptedPsw = $_SESSION['cfdi_decrypted_psw'];
        unset($_SESSION['cfdi_decrypted_psw']);
    }
    print '<tr style="border-bottom: 1px solid #edf2f7;">';
    print '<td style="padding: 14px 18px; font-weight: bold; background: #f8fafc; color: #4a5568;"><span class="fa fa-key" style="margin-right: 8px; color: #718096;"></span>Contraseña</td>';
    print '<td style="padding: 14px 18px;">';
    if (!empty($decryptedPsw)) {
        print '<span style="font-family: monospace; font-weight: bold; background: #f1f5f9; color: #0f172a; padding: 6px 12px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 1.1em; display: inline-block; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">'.dol_escape_htmltag($decryptedPsw).'</span>';
    } else {
        print '<button class="butAction" type="button" style="margin: 0; padding: 5px 12px;" onclick="cfdiOpenModal(\'showpsw\', \'\', \'Por seguridad, ingresa tu contraseña de Dolibarr para ver la contraseña del certificado.\')"><span class="fa fa-eye" style="margin-right: 6px;"></span>Ver contraseña</button>';
    }
    print '</td>';
    print '</tr>';

    // Archivos asociados
    print '<tr style="border-bottom: 1px solid #edf2f7;">';
    print '<td style="padding: 14px 18px; font-weight: bold; background: #f8fafc; color: #4a5568;"><span class="fa fa-folder-open" style="margin-right: 8px; color: #718096;"></span>Archivos asociados</td>';
    print '<td style="padding: 14px 18px;">';
    print '<table class="noborder" style="width: 100%; margin: 0; padding: 0; border-collapse: collapse;">';
    $assocFiles = array(
        '.cer'     => array('Certificado DER', 'fa-file-contract'),
        '.cer.pem' => array('Certificado PEM', 'fa-file-alt'),
        '.key'     => array('Llave PKCS8', 'fa-key'),
        '.key.pem' => array('Llave PEM', 'fa-key')
    );
    foreach ($assocFiles as $ext => $fileInfo) {
        $label = $fileInfo[0];
        $icon  = $fileInfo[1];
        $filename = $certNameCfg.$ext;
        print '<tr style="border-bottom: 1px dashed #e2e8f0;">';
        print '<td width="200" style="padding: 10px 0; color: #4a5568;"><span class="fa '.$icon.'" style="margin-right: 8px; color: #a0aec0; width: 14px; text-align: center;"></span>'.$label.' <code style="font-size: 0.85em; background: #f1f5f9; padding: 2px 4px; border-radius: 3px; color: #64748b;">'.$ext.'</code></td>';
        print '<td style="padding: 10px 0;">';
        print '<span style="font-family: monospace; margin-right: 20px; display: inline-block; min-width: 200px; color: #1e293b; font-weight: 500;">'.dol_escape_htmltag($filename).'</span>';
        print '<button class="butAction" type="button" style="padding: 4px 10px; font-size: 0.9em; margin: 0;" onclick="cfdiOpenModal(\'download\', \''.dol_escape_js($filename).'\', \'Por seguridad, ingresa tu contraseña de Dolibarr para descargar '.dol_escape_js($filename).'.\')"><span class="fa fa-download" style="margin-right: 6px;"></span>Descargar</button>';
        print '</td>';
        print '</tr>';
    }
    print '</table>';
    print '</td>';
    print '</tr>';

    // Acciones (Eliminar)
    print '<tr>';
    print '<td style="padding: 16px 18px; background: #f8fafc;"></td>';
    print '<td style="padding: 16px 18px; text-align: right;">';
    print '<form method="post" action="'.$_SERVER['PHP_SELF'].'?action=deletecert&token='.newToken().'" onsubmit="return confirm('."'".dol_escape_js($langs->trans('DeleteCertConfirm'))."'".');" style="margin: 0; display: inline-block;">';
    print '<button class="butActionDelete" type="submit" style="margin: 0; padding: 6px 14px;"><span class="fa fa-trash" style="margin-right: 6px;"></span>'.$langs->transnoentities('DeleteCert').'</button>';
    print '</form>';
    print '</td>';
    print '</tr>';
    
    print '</table>';
    print '</div>';
}

// Historial de reemplazos de certificado
$histJson = getDolGlobalString('CFDI_CERT_HISTORY');
$hist = ($histJson ? @json_decode($histJson, true) : array());
if (is_array($hist) && !empty($hist)) {
    print '<h4>Historial de certificados</h4>';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>Fecha</td><td>Certificado</td><td>Vencimiento</td><td>Subido por</td><td>Reemplazó a</td>';
    print '</tr>';
    foreach ($hist as $entry) {
        $expHtml = !empty($entry['expiry']) ? date('d/m/Y', (int)$entry['expiry']) : '<span style="color:#aaa">—</span>';
        $prevHtml = !empty($entry['prev']) ? dol_escape_htmltag($entry['prev']) : '<span style="color:#aaa">(ninguno)</span>';
        print '<tr class="oddeven">';
        print '<td>'.dol_escape_htmltag($entry['date']).'</td>';
        print '<td><b>'.dol_escape_htmltag($entry['name']).'</b></td>';
        print '<td>'.$expHtml.'</td>';
        print '<td>'.dol_escape_htmltag($entry['userLogin']).'</td>';
        print '<td>'.$prevHtml.'</td>';
        print '</tr>';
    }
    print '</table><br>';
}

if ($action == 'edit') {
	print $formSetup->generateOutput(true);
	print '<br>';
} elseif (!empty($formSetup->items)) {
	print $formSetup->generateOutput();
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
	print '</div>';
} else {
	print '<br>'.$langs->trans("NothingToSetup");
}


$moduledir = 'cfdi';
$myTmpObjects = array();
// TODO Scan list of objects
$myTmpObjects['myobject'] = array('label'=>'MyObject', 'includerefgeneration'=>0, 'includedocgeneration'=>0);


foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
	if ($myTmpObjectKey != $type) {
		continue;
	}
	if ($myTmpObjectArray['includerefgeneration']) {
		/*
		 * Orders Numbering model
		 */
		$setupnotempty++;

		print load_fiche_titre($langs->trans("NumberingModules", $myTmpObjectArray['label']), '', '');

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Name").'</td>';
		print '<td>'.$langs->trans("Description").'</td>';
		print '<td class="nowrap">'.$langs->trans("Example").'</td>';
		print '<td class="center" width="60">'.$langs->trans("Status").'</td>';
		print '<td class="center" width="16">'.$langs->trans("ShortInfo").'</td>';
		print '</tr>'."\n";

		clearstatcache();

		foreach ($dirmodels as $reldir) {
			$dir = dol_buildpath($reldir."core/modules/".$moduledir);

			if (is_dir($dir)) {
				$handle = opendir($dir);
				if (is_resource($handle)) {
					while (($file = readdir($handle)) !== false) {
						if (strpos($file, 'mod_'.strtolower($myTmpObjectKey).'_') === 0 && substr($file, dol_strlen($file) - 3, 3) == 'php') {
							$file = substr($file, 0, dol_strlen($file) - 4);

							require_once $dir.'/'.$file.'.php';

							$module = new $file($db);

							// Show modules according to features level
							if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2) {
								continue;
							}
							if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) {
								continue;
							}

							if ($module->isEnabled()) {
								dol_include_once('/'.$moduledir.'/class/'.strtolower($myTmpObjectKey).'.class.php');

								print '<tr class="oddeven"><td>'.$module->name."</td><td>\n";
								print $module->info();
								print '</td>';

								// Show example of numbering model
								print '<td class="nowrap">';
								$tmp = $module->getExample();
								if (preg_match('/^Error/', $tmp)) {
									$langs->load("errors");
									print '<div class="error">'.$langs->trans($tmp).'</div>';
								} elseif ($tmp == 'NotConfigured') {
									print $langs->trans($tmp);
								} else {
									print $tmp;
								}
								print '</td>'."\n";

								print '<td class="center">';
								$constforvar = 'CFDI_'.strtoupper($myTmpObjectKey).'_ADDON';
								if (getDolGlobalString($constforvar) == $file) {
									print img_picto($langs->trans("Activated"), 'switch_on');
								} else {
									print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmod&token='.newToken().'&object='.strtolower($myTmpObjectKey).'&value='.urlencode($file).'">';
									print img_picto($langs->trans("Disabled"), 'switch_off');
									print '</a>';
								}
								print '</td>';

								$mytmpinstance = new $myTmpObjectKey($db);
								$mytmpinstance->initAsSpecimen();

								// Info
								$htmltooltip = '';
								$htmltooltip .= ''.$langs->trans("Version").': <b>'.$module->getVersion().'</b><br>';

								$nextval = $module->getNextValue($mytmpinstance);
								if ("$nextval" != $langs->trans("NotAvailable")) {  // Keep " on nextval
									$htmltooltip .= ''.$langs->trans("NextValue").': ';
									if ($nextval) {
										if (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured') {
											$nextval = $langs->trans($nextval);
										}
										$htmltooltip .= $nextval.'<br>';
									} else {
										$htmltooltip .= $langs->trans($module->error).'<br>';
									}
								}

								print '<td class="center">';
								print $form->textwithpicto('', $htmltooltip, 1, 0);
								print '</td>';

								print "</tr>\n";
							}
						}
					}
					closedir($handle);
				}
			}
		}
		print "</table><br>\n";
	}

	if ($myTmpObjectArray['includedocgeneration']) {
		/*
		 * Document templates generators
		 */
		$setupnotempty++;
		$type = strtolower($myTmpObjectKey);

		print load_fiche_titre($langs->trans("DocumentModules", $myTmpObjectKey), '', '');

		// Load array def with activated templates
		$def = array();
		$sql = "SELECT nom";
		$sql .= " FROM ".MAIN_DB_PREFIX."document_model";
		$sql .= " WHERE type = '".$db->escape($type)."'";
		$sql .= " AND entity = ".$conf->entity;
		$resql = $db->query($sql);
		if ($resql) {
			$i = 0;
			$num_rows = $db->num_rows($resql);
			while ($i < $num_rows) {
				$array = $db->fetch_array($resql);
				array_push($def, $array[0]);
				$i++;
			}
		} else {
			dol_print_error($db);
		}

		print "<table class=\"noborder\" width=\"100%\">\n";
		print "<tr class=\"liste_titre\">\n";
		print '<td>'.$langs->trans("Name").'</td>';
		print '<td>'.$langs->trans("Description").'</td>';
		print '<td class="center" width="60">'.$langs->trans("Status")."</td>\n";
		print '<td class="center" width="60">'.$langs->trans("Default")."</td>\n";
		print '<td class="center" width="38">'.$langs->trans("ShortInfo").'</td>';
		print '<td class="center" width="38">'.$langs->trans("Preview").'</td>';
		print "</tr>\n";

		clearstatcache();

		foreach ($dirmodels as $reldir) {
			foreach (array('', '/doc') as $valdir) {
				$realpath = $reldir."core/modules/".$moduledir.$valdir;
				$dir = dol_buildpath($realpath);

				if (is_dir($dir)) {
					$handle = opendir($dir);
					if (is_resource($handle)) {
						while (($file = readdir($handle)) !== false) {
							$filelist[] = $file;
						}
						closedir($handle);
						arsort($filelist);

						foreach ($filelist as $file) {
							if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file)) {
								if (file_exists($dir.'/'.$file)) {
									$name = substr($file, 4, dol_strlen($file) - 16);
									$classname = substr($file, 0, dol_strlen($file) - 12);

									require_once $dir.'/'.$file;
									$module = new $classname($db);

									$modulequalified = 1;
									if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2) {
										$modulequalified = 0;
									}
									if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) {
										$modulequalified = 0;
									}

									if ($modulequalified) {
										print '<tr class="oddeven"><td width="100">';
										print (empty($module->name) ? $name : $module->name);
										print "</td><td>\n";
										if (method_exists($module, 'info')) {
											print $module->info($langs);
										} else {
											print $module->description;
										}
										print '</td>';

										// Active
										if (in_array($name, $def)) {
											print '<td class="center">'."\n";
											print '<a href="'.$_SERVER["PHP_SELF"].'?action=del&token='.newToken().'&value='.urlencode($name).'">';
											print img_picto($langs->trans("Enabled"), 'switch_on');
											print '</a>';
											print '</td>';
										} else {
											print '<td class="center">'."\n";
											print '<a href="'.$_SERVER["PHP_SELF"].'?action=set&token='.newToken().'&value='.urlencode($name).'&scan_dir='.urlencode($module->scandir).'&label='.urlencode($module->name).'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
											print "</td>";
										}

										// Default
										print '<td class="center">';
										$constforvar = 'CFDI_'.strtoupper($myTmpObjectKey).'_ADDON_PDF';
										if (getDolGlobalString($constforvar) == $name) {
											//print img_picto($langs->trans("Default"), 'on');
											// Even if choice is the default value, we allow to disable it. Replace this with previous line if you need to disable unset
											print '<a href="'.$_SERVER["PHP_SELF"].'?action=unsetdoc&token='.newToken().'&object='.urlencode(strtolower($myTmpObjectKey)).'&value='.urlencode($name).'&scan_dir='.urlencode($module->scandir).'&label='.urlencode($module->name).'&amp;type='.urlencode($type).'" alt="'.$langs->trans("Disable").'">'.img_picto($langs->trans("Enabled"), 'on').'</a>';
										} else {
											print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&token='.newToken().'&object='.urlencode(strtolower($myTmpObjectKey)).'&value='.urlencode($name).'&scan_dir='.urlencode($module->scandir).'&label='.urlencode($module->name).'" alt="'.$langs->trans("Default").'">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
										}
										print '</td>';

										// Info
										$htmltooltip = ''.$langs->trans("Name").': '.$module->name;
										$htmltooltip .= '<br>'.$langs->trans("Type").': '.($module->type ? $module->type : $langs->trans("Unknown"));
										if ($module->type == 'pdf') {
											$htmltooltip .= '<br>'.$langs->trans("Width").'/'.$langs->trans("Height").': '.$module->page_largeur.'/'.$module->page_hauteur;
										}
										$htmltooltip .= '<br>'.$langs->trans("Path").': '.preg_replace('/^\//', '', $realpath).'/'.$file;

										$htmltooltip .= '<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
										$htmltooltip .= '<br>'.$langs->trans("Logo").': '.yn($module->option_logo, 1, 1);
										$htmltooltip .= '<br>'.$langs->trans("MultiLanguage").': '.yn($module->option_multilang, 1, 1);

										print '<td class="center">';
										print $form->textwithpicto('', $htmltooltip, 1, 0);
										print '</td>';

										// Preview
										print '<td class="center">';
										if ($module->type == 'pdf') {
											$newname = preg_replace('/_'.preg_quote(strtolower($myTmpObjectKey), '/').'/', '', $name);
											print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&module='.urlencode($newname).'&object='.urlencode($myTmpObjectKey).'">'.img_object($langs->trans("Preview"), 'pdf').'</a>';
										} else {
											print img_object($langs->trans("PreviewNotAvailable"), 'generic');
										}
										print '</td>';

										print "</tr>\n";
									}
								}
							}
						}
					}
				}
			}
		}

		print '</table>';
	}
}

if (empty($setupnotempty)) {
	print '<br>'.$langs->trans("NothingToSetup");
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
