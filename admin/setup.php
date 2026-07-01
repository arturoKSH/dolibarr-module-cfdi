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
        $certPsw = GETPOST('cert_psw', 'alpha');
        if (empty($_FILES['cert_cer']) || empty($_FILES['cert_key'])) {
            setEventMessages($langs->trans('MissingFile'), null, 'errors');
        } else {
            $cer = $_FILES['cert_cer'];
            $key = $_FILES['cert_key'];

            $extCer = strtolower(pathinfo($cer['name'], PATHINFO_EXTENSION));
            $extKey = strtolower(pathinfo($key['name'], PATHINFO_EXTENSION));

            // Validar extensiones básicas
            if (!in_array($extCer, ['cer','pem']) || !in_array($extKey, ['key','pem'])) {
                setEventMessages($langs->trans('InvalidFileType'), null, 'errors');
            } elseif ($cer['error'] !== UPLOAD_ERR_OK || $key['error'] !== UPLOAD_ERR_OK) {
                setEventMessages($langs->trans('UploadError'), null, 'errors');
            } else {
                $cerContent = file_get_contents($cer['tmp_name']);
                $keyContent = file_get_contents($key['tmp_name']);

                // Validar que el .cer sea realmente un certificado X509 (DER o PEM)
                $certRes = ($cerContent !== false) ? @openssl_x509_read($cerContent) : false;
                // Validar que el .key sea realmente una llave privada y que la contraseña la abra
                $pkeyRes = ($keyContent !== false) ? @openssl_pkey_get_private($keyContent, $certPsw) : false;

                if ($certRes === false || $pkeyRes === false) {
                    setEventMessages($langs->trans('InvalidCertOrPassword'), null, 'errors');
                } else {
                    // Nombre saneado del certificado: solo alfanumerico, guion y guion bajo
                    $certName = preg_replace('/[^A-Za-z0-9_-]/', '', basename(pathinfo($cer['name'], PATHINFO_FILENAME)));
                    $certName = substr($certName, 0, 64);
                    if ($certName === '' || $certName === '.' || $certName === '..') {
                        $certName = 'cert_'.time();
                    }

                    $targetDir = DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/cfdi_Cert/'.$certName.'/';
                    if (!is_dir($targetDir)) {
                        if (!dol_mkdir($targetDir)) {
                            setEventMessages($langs->trans('CantCreateDir')." ".$targetDir, null, 'errors');
                            // evitar continuar si no se puede crear carpeta
                            header('Location: '.$_SERVER['PHP_SELF']);
                            exit;
                        }
                    }
                    // Bloquear acceso web directo al directorio de certificados (defensa en profundidad)
                    $certRootDir = DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/cfdi_Cert/';
                    if (!file_exists($certRootDir.'.htaccess')) {
                        file_put_contents($certRootDir.'.htaccess', "Require all denied\nphp_flag engine off\n");
                    }

                    $dstCer = $targetDir.$certName.'.cer';
                    $dstKey = $targetDir.$certName.'.key';

                    $okCer = move_uploaded_file($cer['tmp_name'], $dstCer);
                    $okKey = move_uploaded_file($key['tmp_name'], $dstKey);

                    if ($okCer && $okKey) {
                        // Si habia un certificado anterior con otro nombre, limpiar su carpeta (evita huerfanos)
                        $oldCertName = getDolGlobalString('MAIN_INFO_CFDI_CERT_NAME');
                        if (!empty($oldCertName) && $oldCertName !== $certName) {
                            $oldDir = DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/cfdi_Cert/'.$oldCertName.'/';
                            if (is_dir($oldDir)) {
                                dol_delete_dir_recursive($oldDir);
                            }
                        }

                        // Guardar constantes Dolibarr para usar en el módulo (password cifrada en reposo)
                        dolibarr_set_const($db, 'MAIN_INFO_CFDI_CERT_NAME', $certName, 'chaine', 0, '', $conf->entity);
                        dolibarr_set_const($db, 'MAIN_INFO_CFDI_CERT_PSW', dolEncrypt($certPsw), 'chaine', 0, '', $conf->entity);

                        setEventMessages($langs->trans('FilesSavedOK'), null, 'mesgs');
                    } else {
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
            $certDir = DOL_DOCUMENT_ROOT.'/custom/cfdi/elcInv/cfdi_Cert/'.$certNameToDelete.'/';
            if (is_dir($certDir)) {
                dol_delete_dir_recursive($certDir);
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
			print '<div class="warning">'.sprintf($langs->trans('CertExpiredWarning'), abs($certStatus['daysLeft'])).'</div><br>';
		} elseif ($certStatus['daysLeft'] <= 30) {
			print '<div class="warning">'.sprintf($langs->trans('CertExpiringWarning'), $certStatus['daysLeft']).'</div><br>';
		}
	}
}

// Formulario para subir certificado .cer y llave .key
print '<h4>'.$langs->transnoentities('UploadCertAndKey').'</h4>';
print '<form method="post" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'?action=savecert&token='.newToken().'">';
print '<table class="border" width="100%">';
print '<tr><td width="200">'.$langs->transnoentities('CertFile (.cer)').'</td><td><input type="file" name="cert_cer" accept=".cer,.pem" required></td></tr>';
print '<tr><td>'.$langs->transnoentities('KeyFile (.key)').'</td><td><input type="file" name="cert_key" accept=".key,.pem" required></td></tr>';
print '<tr><td>'.$langs->transnoentities('CertPassword').'</td><td><input type="password" name="cert_psw" value=""></td></tr>';
print '<tr><td></td><td><button class="butAction" type="submit">'.$langs->transnoentities('Save').'</button></td></tr>';
print '</table>';
print '</form>';

if (!empty($currentCertName)) {
	print '<form method="post" action="'.$_SERVER['PHP_SELF'].'?action=deletecert&token='.newToken().'" onsubmit="return confirm('."'".dol_escape_js($langs->trans('DeleteCertConfirm'))."'".');">';
	print '<button class="butActionDelete" type="submit">'.$langs->transnoentities('DeleteCert').'</button>';
	print '</form>';
}
print '<br>';

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
