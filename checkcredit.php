<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/skebby.php');

if (Tools::getValue('token') != Tools::encrypt(Configuration::get('PS_SHOP_NAME'))){
	die('Error: Invalid Token');	
}

$skebby = new Skebby();
echo json_encode($skebby->getCredit());