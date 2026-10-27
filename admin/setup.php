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

// La contraseña nunca se manda al navegador: el campo va vacío y, si se deja así, se conserva
// la guardada. dolibarr_set_const() la cifra por el sufijo _PASSWORD.
$item = $formSetup->newItem('CFDI_PAC_PASSWORD');
$item->nameText = $langs->transnoentities('CfdiPacPassword');
$item->setAsGenericPassword();
$item->fieldInputOverride = '<input type="password" name="CFDI_PAC_PASSWORD" class="flat minwidth300" value="" autocomplete="new-password" placeholder="'.dol_escape_htmltag($langs->trans('CfdiPacPasswordKeep')).'">';
$item->fieldOutputOverride = getDolGlobalString('CFDI_PAC_PASSWORD') !== '' ? '********' : '';
$pacPasswordItem = $item;
$item->setValueFromPostCallBack(function () use ($pacPasswordItem) {
	$pacPasswordItem->fieldValue = GETPOST('CFDI_PAC_PASSWORD', 'none');
	return 1;
});
$item->saveCallBack = function ($item) use ($db, $conf) {
	if ($item->fieldValue === '') {
		return 1;
	}
	return dolibarr_set_const($db, 'CFDI_PAC_PASSWORD', $item->fieldValue, 'chaine', 0, '', $conf->entity) >= 0 ? 1 : -1;
};

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

// Ajuste manual de la hora de emisión, para servidores con el reloj o la zona mal (ej. IONOS).
$item = $formSetup->newItem('CFDI_TIME_ADJUST_HOURS');
$item->nameText = $langs->trans('CfdiTimeAdjustHours');
$item->setAsNumber(-23, 23, 1);
$item->helpText = $langs->trans('CfdiTimeAdjustHoursHelp');


// Campo vacío = el código usa su valor por defecto: se muestra en gris para que no parezca un hueco.
foreach ($formSetup->items as $setupItem) {
	$defaultValue = $setupItem->fieldAttr['placeholder'] ?? '';
	if ($defaultValue !== '' && getDolGlobalString($setupItem->confKey) === '') {
		$setupItem->fieldOutputOverride = '<span class="opacitymedium">'.dol_escape_htmltag($langs->trans('CfdiDefaultValue', $defaultValue)).'</span>';
	}
}

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
        } elseif (empty($_FILES['cert_cer']['name']) || empty($_FILES['cert_key']['name'])) {
            setEventMessages($langs->trans('CfdiCertFilesRequired'), null, 'errors');
        } else {
            $cer = $_FILES['cert_cer'];
            $key = $_FILES['cert_key'];

            $extCer = strtolower(pathinfo($cer['name'], PATHINFO_EXTENSION));
            $extKey = strtolower(pathinfo($key['name'], PATHINFO_EXTENSION));

            if (!in_array($extCer, array('cer', 'pem'), true) || !in_array($extKey, array('key', 'pem'), true)) {
                setEventMessages($langs->trans('CfdiCertBadExtension'), null, 'errors');
            } elseif ($cer['error'] !== UPLOAD_ERR_OK || $key['error'] !== UPLOAD_ERR_OK) {
                setEventMessages($langs->trans('UploadError'), null, 'errors');
            } else {
                $cerPemContent = cfdiCertificateToPem(file_get_contents($cer['tmp_name']));
                $certRes = @openssl_x509_read($cerPemContent);
                list($pkeyRes, $keyPemContent) = cfdiPrivateKeyToPem(file_get_contents($key['tmp_name']), $certPsw);

                if ($certRes === false || $pkeyRes === false) {
                    setEventMessages($langs->trans('InvalidCertOrPassword'), null, 'errors');
                } elseif (!openssl_x509_check_private_key($certRes, $pkeyRes)) {
                    setEventMessages($langs->trans('CfdiCertKeyMismatch'), null, 'errors');
                } elseif (!openssl_pkey_export($pkeyRes, $keyPemContent)) {
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

                    $okCer    = move_uploaded_file($cer['tmp_name'], $dstCer);
                    $okKey    = move_uploaded_file($key['tmp_name'], $dstKey);
                    $okCerPem = file_put_contents($dstCerPem, $cerPemContent) !== false;
                    // La llave PEM queda sin contraseña (stampStr() la lee así): solo permisos del dueño.
                    $okKeyPem = file_put_contents($dstKeyPem, $keyPemContent) !== false && chmod($dstKeyPem, 0600);

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
            // Se descargan los dos archivos originales del SAT (.cer y .key) en un .zip;
            // los .pem se regeneran al volver a subirlos.
            $certNameCfg = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
            if (empty($certNameCfg) || cfdiCertDir($certNameCfg) === '') {
                accessforbidden('Parámetros incorrectos');
            }
            $zipPath = tempnam(sys_get_temp_dir(), 'cfdi');
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
                accessforbidden('No se pudo preparar la descarga');
            }
            foreach (array('.cer', '.key') as $suffix) {
                $certFile = cfdiCertFile($certNameCfg, $suffix);
                if (file_exists($certFile)) {
                    $zip->addFile($certFile, basename($certFile));
                }
            }
            $zip->close();
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'.cfdiCertSafeName($certNameCfg).'.zip"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            unlink($zipPath);
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
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'file-signature');

// Estado del certificado: si falta o venció, la acción principal es subirlo (sin él no se timbra).
$certNameCfg   = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
$certExpiryCfg = (int) getDolGlobalString('MAIN_INFO_CFDI_CERT_EXPIRY_TS');
$certExpired   = ($certExpiryCfg > 0 && time() > $certExpiryCfg);
$certNeedsUpload = empty($certNameCfg) || $certExpired;

print '<style>
.cfdi-block { margin-bottom: 32px; max-width: 900px; }
.cfdi-block h3 { margin: 0 0 12px; font-size: 1.1em; }
.cfdi-card { border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 20px; background: #fff; }
.cfdi-card table.noborder tr.oddeven td { background: transparent; }
.cfdi-cert-line { display: flex; align-items: baseline; gap: 16px; flex-wrap: wrap; }
.cfdi-cert-name { font-weight: bold; font-size: 1.1em; }
.cfdi-badge { padding: 2px 10px; border-radius: 12px; font-size: .85em; font-weight: bold; }
.cfdi-links { margin-top: 12px; }
.cfdi-links a, .cfdi-links button.cfdi-link { background: none; border: 0; padding: 0; color: inherit; text-decoration: underline; cursor: pointer; font: inherit; }
.cfdi-links .cfdi-sep { margin: 0 8px; color: #a0aec0; }
.cfdi-links button.cfdi-link-danger { color: #c0392b; }
.cfdi-upload { margin-top: 16px; padding-top: 16px; border-top: 1px solid #edf2f7; }
.cfdi-upload td { padding: 6px 8px 6px 0; }
.cfdi-block details > summary { cursor: pointer; margin-top: 12px; }
.cfdi-actions { margin-top: 16px; text-align: right; }
</style>';

// Modal de confirmación de contraseña del usuario (subir y descargar certificado)
print '<div id="cfdi-pwd-modal" style="display:none;position:fixed;z-index:10000;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.45);">';
print '  <div style="background:#fff;margin:14% auto;padding:24px 32px;max-width:440px;border-radius:4px;box-shadow:0 8px 28px rgba(0,0,0,0.22);">';
print '    <h4 style="margin-top:0">Confirmar identidad</h4>';
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
print 'function cfdiOpenModal(action, msg){';
print '  cfdiModalAction = action;';
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
print '  } else if (cfdiModalAction === "download") {';
print '    document.getElementById("cfdi-action-download-pwd").value=p;';
print '    document.getElementById("cfdi-form-download").submit();';
print '    document.getElementById("cfdi-pwd-modal").style.display="none";';
print '  }';
print '}';
print 'function cfdiSaveCert(){';
print '  var f=document.getElementById("cfdi-cert-form");';
print '  if(!f.reportValidity()) return;';
print '  cfdiOpenModal("save", '.json_encode('Por seguridad, ingresa tu contraseña de Dolibarr para guardar el certificado.').');';
print '}';
print 'function cfdiToggleUpload(){var u=document.getElementById("cfdi-upload");u.style.display=(u.style.display==="none")?"block":"none";}';
print 'document.addEventListener("DOMContentLoaded",function(){var el=document.getElementById("cfdi-confirm-pwd");if(el)el.addEventListener("keydown",function(e){if(e.key==="Enter")cfdiSubmitWithPwd();});});';
print '</script>';

print '<form id="cfdi-form-download" method="post" action="'.$_SERVER['PHP_SELF'].'?action=downloadfile&token='.newToken().'">';
print '<input type="hidden" name="confirm_password" id="cfdi-action-download-pwd" value="">';
print '</form>';

// ── Bloque 1: Certificado SAT ─────────────────────────────────────────────
print '<div class="cfdi-block">';
print '<h3>'.$langs->trans('CfdiCertTitle').'</h3>';
print '<div class="cfdi-card">';

if (!empty($certNameCfg)) {
	print '<div class="cfdi-cert-line">';
	print '<span class="cfdi-cert-name">'.dol_escape_htmltag($certNameCfg).'</span>';
	if ($certExpiryCfg > 0) {
		$daysCfg = (int) round(($certExpiryCfg - time()) / 86400);
		if ($certExpired) {
			$clrCfg = '#c0392b'; $lblCfg = $langs->trans('CfdiCertExpired');
		} elseif ($daysCfg <= 30) {
			$clrCfg = '#d97706'; $lblCfg = $langs->trans('CfdiCertExpiresIn', $daysCfg);
		} else {
			$clrCfg = '#16a34a'; $lblCfg = $langs->trans('CfdiCertValid');
		}
		print '<span>'.$langs->trans('CfdiCertExpiresOn', date('d/m/Y', $certExpiryCfg)).'</span>';
		print '<span class="cfdi-badge" style="color:'.$clrCfg.';background:'.$clrCfg.'15;">'.$lblCfg.'</span>';
	} else {
		print '<span class="cfdi-badge" style="color:#d97706;background:#fef3c7;">'.$langs->trans('CfdiCertNoExpiry').'</span>';
	}
	print '</div>';

	print '<div class="cfdi-links">';
	print '<button type="button" class="cfdi-link" onclick="cfdiToggleUpload()">'.$langs->trans('CfdiCertReplace').'</button>';
	print '<span class="cfdi-sep">·</span>';
	print '<button type="button" class="cfdi-link" onclick="cfdiOpenModal(\'download\', '.dol_escape_htmltag(json_encode('Por seguridad, ingresa tu contraseña de Dolibarr para descargar el certificado.')).')">'.$langs->trans('CfdiCertDownload').'</button>';
	print '<span class="cfdi-sep">·</span>';
	print '<form method="post" action="'.$_SERVER['PHP_SELF'].'?action=deletecert&token='.newToken().'" onsubmit="return confirm('.dol_escape_htmltag(json_encode($langs->trans('DeleteCertConfirm'))).');" style="display:inline;">';
	print '<button type="submit" class="cfdi-link cfdi-link-danger">'.$langs->trans('CfdiCertDelete').'</button>';
	print '</form>';
	print '</div>';
} else {
	print '<span class="opacitymedium">'.$langs->trans('CfdiCertMissing').'</span>';
}

// Subida: visible cuando falta o venció el certificado; si no, detrás de "Reemplazar certificado".
print '<div id="cfdi-upload" class="cfdi-upload"'.($certNeedsUpload ? '' : ' style="display:none"').'>';
print '<form id="cfdi-cert-form" method="post" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'?action=savecert&token='.newToken().'">';
print '<input type="hidden" id="cfdi-hidden-pwd" name="confirm_password" value="">';
print '<table>';
print '<tr><td width="200">'.$langs->trans('CfdiCertFileCer').'</td><td><input type="file" name="cert_cer" accept=".cer,.pem" required></td></tr>';
print '<tr><td>'.$langs->trans('CfdiCertFileKey').'</td><td><input type="file" name="cert_key" accept=".key,.pem" required></td></tr>';
print '<tr><td>'.$langs->trans('CertPassword').'</td><td><input type="password" name="cert_psw" class="flat minwidth200" autocomplete="new-password"><br><span class="opacitymedium">'.$langs->trans('CfdiCertPasswordOptionalHelp').'</span></td></tr>';
print '</table>';
print '<div class="cfdi-actions"><button type="button" class="'.($certNeedsUpload ? 'butAction' : 'button').'" onclick="cfdiSaveCert()">'.$langs->trans('CfdiCertUpload').'</button></div>';
print '</form>';
print '</div>';

print '</div>';
print '</div>';

// ── Bloque 2: Conexión con el PAC ─────────────────────────────────────────
// Lo de todos los días arriba; URLs del otro ambiente, cancelación y contrato SOAP en "avanzadas".
$activeEnv = getDolGlobalString('CFDI_ENV', 'test') === 'prod' ? 'PROD' : 'TEST';
$advancedKeys = array(
	'CFDI_STAMP_URL_'.($activeEnv === 'PROD' ? 'TEST' : 'PROD'),
	'CFDI_CANCEL_URL_TEST', 'CFDI_CANCEL_URL_PROD',
	'CFDI_PAC_PROVIDER', 'CFDI_SOAP_NAMESPACE', 'CFDI_SOAP_METHOD', 'CFDI_TIME_ADJUST_HOURS',
);
$editPac = ($action == 'edit');
$mainRows = '';
$advancedRows = '';
foreach ($formSetup->items as $setupItem) {
	if (in_array($setupItem->confKey, $advancedKeys, true)) {
		$advancedRows .= $formSetup->generateLineOutput($setupItem, $editPac);
	} else {
		$mainRows .= $formSetup->generateLineOutput($setupItem, $editPac);
	}
}

print '<div class="cfdi-block">';
print '<h3>'.$langs->trans('CfdiPacTitle').'</h3>';
print '<div class="cfdi-card">';
if ($editPac) {
	print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
}
print '<table class="noborder centpercent">'.$mainRows.'</table>';
print '<details'.($editPac ? ' open' : '').'><summary>'.$langs->trans('CfdiAdvancedOptions').'</summary>';
print '<table class="noborder centpercent">'.$advancedRows.'</table>';
print '</details>';
print '<div class="cfdi-actions">';
if ($editPac) {
	print '<a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('Cancel').'</a> ';
	print '<input class="button button-save" type="submit" value="'.$langs->trans('Save').'">';
} elseif ($certNeedsUpload) {
	print '<a href="'.$_SERVER['PHP_SELF'].'?action=edit&token='.newToken().'">'.$langs->trans('Modify').'</a>';
} else {
	print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=edit&token='.newToken().'">'.$langs->trans('Modify').'</a>';
}
print '</div>';
if ($editPac) {
	print '</form>';
}
print '</div>';
print '</div>';

// ── Bloque 3: Historial de certificados (colapsado) ───────────────────────
$histJson = getDolGlobalString('CFDI_CERT_HISTORY');
$hist = ($histJson ? @json_decode($histJson, true) : array());
if (is_array($hist) && !empty($hist)) {
	print '<div class="cfdi-block">';
	print '<details><summary><b>'.$langs->trans('CfdiCertHistory', count($hist)).'</b></summary>';
	print '<table class="noborder centpercent" style="margin-top:12px">';
	print '<tr class="liste_titre"><td>Fecha</td><td>Certificado</td><td>Vencimiento</td><td>Subido por</td></tr>';
	foreach ($hist as $entry) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($entry['date']).'</td>';
		print '<td>'.dol_escape_htmltag($entry['name']).'</td>';
		print '<td>'.(!empty($entry['expiry']) ? date('d/m/Y', (int) $entry['expiry']) : '—').'</td>';
		print '<td>'.dol_escape_htmltag($entry['userLogin']).'</td>';
		print '</tr>';
	}
	print '</table>';
	print '</details>';
	print '</div>';
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
