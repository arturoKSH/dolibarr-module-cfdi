<?php
/*
 * Compatibility endpoint for the historical related-invoices URL.
 * The maintained implementation lives in elcInv/compta/facsrel.php.
 */

require '../../../main.inc.php';

// Keep the historical URL working while using the maintained invoice relation page.
$url = DOL_URL_ROOT . '/custom/cfdi/elcInv/compta/facsrel.php';
$params = array();

$facid = GETPOST('facid', 'int');
$ref = GETPOST('ref', 'alpha');
if ($facid > 0) {
	$params[] = 'facid=' . $facid;
}
if ($ref !== '') {
	$params[] = 'ref=' . urlencode($ref);
}

header('Location: ' . $url . (!empty($params) ? '?' . implode('&', $params) : ''));
exit;
