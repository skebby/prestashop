<?php
/**
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
* @author    PrestaShop SA <contact@prestashop.com>
* @copyright 2007-2015 PrestaShop SA
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
* International Registered Trademark & Property of PrestaShop SA
*/

include (dirname(__FILE__).'/../../config/config.inc.php');
include (dirname(__FILE__).'/skebby.php');

header('Content-Type: application/json');

if (Tools::getValue('token') != Tools::encrypt(Configuration::get('PS_SHOP_NAME')))
	die('Error: Invalid Token');


$date=date_create();

$skebby_module = new Skebby();

$params = array();

$params['civility'] = 'Mr.';
$params['first_name'] = 'Matteo';
$params['last_name'] = 'Monti';
$params['order_price'] = 'EUR 10.15';
$params['order_date'] = date_format($date,'Y-m-d H:i:s');
$params['order_reference'] = 'ABCDEFGHI';

$params['currency'] = '€';
$params['total_to_pay'] = '100.0000';

echo Tools::jsonEncode($skebby_module->doOrderConfirmation($params));