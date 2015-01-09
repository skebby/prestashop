<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/skebby.php');

if (Tools::getValue('token') != Tools::encrypt(Configuration::get('PS_SHOP_NAME'))){
	die('Error: Invalid Token');	
}

$skebby_module = new Skebby();


$params = array();
$params['customer_mobile'] = Configuration::get('SKEBBY_DEFAULT_NUMBER');
$params['civility'] = 'Mr.';
$params['first_name'] = 'Matteo';
$params['last_name'] = 'Monti';
$params['order_price'] = 'EUR 10.15';
$params['order_date'] = '2015-01-09 14:16:47';
$params['order_reference'] = 'ABCDEFGHI';

echo json_encode($skebby_module->sendMessageForOrder($params, 'SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE'));