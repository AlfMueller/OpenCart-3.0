<?php

$root = dirname(__DIR__);
$languageMock = file_get_contents(
		$root . '/upload/system/library/wallee/dynamic/admin/language.mock');
$ocmod = file_get_contents(
		$root . '/upload/system/library/wallee/modification/WalleeCore.ocmod.xml');

function assertAdminTitle($condition, $scenario){
	if (!$condition) {
		fwrite(STDERR, $scenario . " failed.\n");
		exit(1);
	}
}

assertAdminTitle(strpos($languageMock, '$_[\'heading_title\']') !== false,
		'Dynamic Wallee language must retain the standard extension-list heading');
assertAdminTitle(strpos($ocmod, "strpos(\$order_info['payment_code'], 'wallee_') === 0") !== false,
		'Order-tab title selection must be restricted to Wallee payment methods');
assertAdminTitle(strpos($ocmod,
		"load->language('extension/payment/' . \$order_info['payment_code'], 'wallee_payment')") !== false,
		'Wallee order-tab language must load into an isolated scope');
assertAdminTitle(strpos($ocmod, "get('wallee_payment')->get('heading_title')") !== false,
		'Wallee order tabs must read the isolated heading');
assertAdminTitle(strpos($ocmod, ": \$this->language->get('heading_title')") !== false,
		'Non-Wallee order tabs must retain the OpenCart heading behavior');

$language = array(
	'heading_title' => 'Orders',
	'wallee_payment' => array('heading_title' => 'wallee - TWINT')
);
assertAdminTitle($language['heading_title'] === 'Orders',
		'Loading the Wallee tab title must preserve the order-page heading');
assertAdminTitle($language['wallee_payment']['heading_title'] === 'wallee - TWINT',
		'Wallee payment tab title must remain available');

echo "Admin order-title scenarios passed.\n";
