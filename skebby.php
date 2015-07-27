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

if (!defined('_PS_VERSION_'))
	exit('');

require_once (dirname(__FILE__).'/lib/Skebby/ApiClient.php');

class Skebby extends Module
{

	/**
	 * A logger instance for skebby module.
	 * writes in file located in ./logs/skebby.log
	 *
	 * @var unknown
	 */
	private $logger;

	/**
	 * Are we in develoment mode?
	 * In develoment mode the log is active.
	 *
	 * @var boolean
	 */
	private $development_mode = false;

	/**
	 * Should we log to file?
	 *
	 * @var boolean
	 */
	private $log_enabled = true;

	/**
	 *
	 * @var SkebbyApiClient
	 */
	private $api_client;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->name = 'skebby';

		$this->tab = 'emailing';

		$this->page = basename(__FILE__, '.php');

		$this->displayName = $this->l('Skebby SMS');

		$this->version = '1.0.0';

		$this->author = 'Skebby Dev Team';

		$this->need_instance = 0;

		$this->ps_versions_compliancy = array(
			'min' => '1.6',
			'max' => _PS_VERSION_
		);

		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Skebby SMS');

		$this->description = $this->l(
			'With Skebby SMS module for Prestashop you will be able to integrate all the Skebby features with no coding. This module requires to  have an account with skebby and have available credit.');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall? You will not be able to send sms notifications.');

		$this->langid = !empty($this->context->language->id) ? $this->context->language->id : '';
		$this->lang_cookie = $this->context->cookie;

		if (!Configuration::get('SKEBBY_DEFAULT_NUMBER'))
			$this->warning = $this->l('Missing sender mobile number');

		if (!Configuration::get('SKEBBY_PASSWORD'))
			$this->warning = $this->l('Missing Skebby Account Password');

		if (!Configuration::get('SKEBBY_USERNAME'))
			$this->warning = $this->l('Missing Skebby Account Username');

			// Checking Extension
		if (!extension_loaded('curl') || !ini_get('allow_url_fopen'))
		{
			if (!extension_loaded('curl') && !ini_get('allow_url_fopen'))
				$this->warning = $this->l('You must enable cURL extension and allow_url_fopen option on your server if you want to use this module.');
			else
				if (!extension_loaded('curl'))
					$this->warning = $this->l('You must enable cURL extension on your server if you want to use this module.');
				else
					if (!ini_get('allow_url_fopen'))
						$this->warning = $this->l('You must enable allow_url_fopen option on your server if you want to use this module.');
		}

		$this->initLogger();

		// instance the Skebby Api Client
		$this->api_client = new SkebbyApiClient();
		$this->api_client->setCredentials(Configuration::get('SKEBBY_USERNAME'), Configuration::get('SKEBBY_PASSWORD'));
	}


	/**
	 * Return a boolean with
	 *
	 * @return boolean
	 */
	public function isConfigured(){

		if (!Configuration::get('SKEBBY_DEFAULT_NUMBER'))
		    return false;

		if (!Configuration::get('SKEBBY_PASSWORD'))
		   return false;

		return true;

	}



	/**
	 * Install the Plugin registering to the payment and order hooks
	 *
	 * @return boolean
	 */
	public function install()
	{
		if (Shop::isFeatureActive())
			Shop::setContext(Shop::CONTEXT_ALL);

		$this->logMessage('Installing Skebby Module');

		$success = (parent::install() && $this->hookInstall());

		if ($success)
		{

			Configuration::updateValue('SKEBBY_DEFAULT_QUALITY', 'classic');

			$suggested_order_template = '';
			$suggested_order_template .= 'New order %order_reference%'."\n";
			$suggested_order_template .= 'from  %civility% %first_name% %last_name% ,'."\n";
			$suggested_order_template .= 'placed on  %order_date%'."\n";
			$suggested_order_template .= 'for amount %order_price%'."\n";
			$suggested_order_template .= 'has been placed.'."\n";

			Configuration::updateValue('SKEBBY_ORDER_TEMPLATE', $suggested_order_template);

			$suggested_shipment_template = '';
			$suggested_shipment_template .= 'Dear %civility% %first_name% %last_name%,'."\n";
			$suggested_shipment_template .= 'your order  %order_reference%'."\n";
			$suggested_shipment_template .= 'placed on  %order_date%'."\n";
			$suggested_shipment_template .= 'for amount %order_price%'."\n";
			$suggested_shipment_template .= 'has been shipped.'."\n";

			Configuration::updateValue('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE', $suggested_shipment_template);

			$this->logMessage('Successfully installed Skebby Module');
			$this->logMessage('Default Quality is: '.Tools::getValue('SKEBBY_DEFAULT_QUALITY'));


			Db::getInstance()->execute('
				CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'skebby_log` (
					`id` int(6) NOT NULL AUTO_INCREMENT,
					`id_shop` INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
					`id_shop_group` INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
					`email` varchar(255) NOT NULL,
					`newsletter_date_add` DATETIME NULL,
					`ip_registration_newsletter` varchar(15) NOT NULL,
					`http_referer` VARCHAR(255) NULL,
					`active` TINYINT(1) NOT NULL DEFAULT \'0\',
					PRIMARY KEY(`id`)
				) ENGINE='._MYSQL_ENGINE_.' default CHARSET=utf8');



		}
		else
			$this->logMessage('Error Installing Skebby Module');

		return $success;
	}

	/**
	 * Delete custom configuration keys.
	 *
	 * @return boolean
	 */
	private function removeConfigKeys()
	{
		if (!Configuration::deleteByName('SKEBBY_USERNAME'))
			return false;
		if (!Configuration::deleteByName('SKEBBY_PASSWORD'))
			return false;
		if (!Configuration::deleteByName('SKEBBY_DEFAULT_QUALITY'))
			return false;
		if (!Configuration::deleteByName('SKEBBY_DEFAULT_ALPHASENDER'))
			return false;
		if (!Configuration::deleteByName('SKEBBY_ALPHASENDER_ACTIVE'))
			return false;
		if (!Configuration::deleteByName('SKEBBY_DEFAULT_NUMBER'))
			return false;
		if (!Configuration::deleteByName('SKEBBY_ORDER_TEMPLATE'))
			return false;
		if (!Configuration::deleteByName('SKEBBY_ORDER_RECIPIENT'))
			return false;
		if (!Configuration::deleteByName('SKEBBY_ORDER_NOTIFICATION_ACTIVE'))
			return false;
		if (!Configuration::deleteByName('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE'))
			return false;
		if (!Configuration::deleteByName('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_ACTIVE'))
			return false;
	}

	/**
	 * Uninstall of hooks
	 *
	 * @return boolean
	 */
	private function hookUninstall()
	{
		return ($this->unregisterHook('orderConfirmation') && $this->unregisterHook('updateOrderStatus'));
	}

	/**
	 * Installation of hooks
	 *
	 * @return boolean
	 */
	private function hookInstall()
	{
		return ($this->registerHook('orderConfirmation') && $this->registerHook('updateOrderStatus'));
	}

	/**
	 *
	 * @return boolean
	 */
	public function uninstall()
	{
		$this->logMessage('Uninstalling Skebby Module');

		$success = (parent::uninstall() && $this->removeConfigKeys() && $this->hookUninstall());

		Db::getInstance()->execute('DROP TABLE '._DB_PREFIX_.'skebby_log');

		if ($success)
			$this->logMessage('Skebby Module Uninstalled Successfully');

		$this->dumpConfig();

		return $success;
	}

	/**
	 * Returns true if the user has opted in for shipping notification.
	 *
	 * @return boolean
	 */
	private function shouldNotifyUponShipment()
	{
		return Configuration::get('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_ACTIVE') == 1 &&
			Configuration::get('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE') != '';
	}

	/**
	 * Returns true if the user has opted in for new Order notification.
	 *
	 * @return boolean
	 */
	private function shouldNotifyUponNewOrder()
	{
		return Configuration::get('SKEBBY_ORDER_NOTIFICATION_ACTIVE') == 1 && Configuration::get('SKEBBY_ORDER_TEMPLATE') != '';
	}

	/**
	 * Should we use the specified Alphanumeric Sender instead of a mobile number?
	 *
	 * @return boolean
	 */
	private function shouldUseAlphasender()
	{
		return Configuration::get('SKEBBY_ALPHASENDER_ACTIVE') == 1 && Configuration::get('SKEBBY_DEFAULT_ALPHASENDER') != '';
	}

	/**
	 * Hook the event of shipping an order.
	 *
	 * @param unknown $params
	 * @return boolean
	 */
	public function hookUpdateOrderStatus($params)
	{
		$this->logMessage('Enter hookUpdateOrderStatus');

		if (!$this->checkModuleStatus())
		{
			$this->logMessage('Skebby module not enabled');
			return false;
		}

		$id_order_state = Tools::getValue('id_order_state');

		// if the order is not being shipped. Exit.
		if ($id_order_state != 4)
		{
			$this->logMessage("Order state do not match state 4. state is $id_order_state");
			return false;
		}

		// If the user didn't opted for notifications. Exit.
		if (!$this->shouldNotifyUponShipment())
		{
			$this->logMessage('User did not opted in for shipment notification');
			return false;
		}

		$this->logMessage('Valid hookUpdateOrderStatus');

		$params = $this->getParamsFromOrder();

		if (!$params)
		{
			$this->logMessage('Unable to load order data');
			return false;
		}

		return $this->sendMessageForOrder($params, 'SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE');
	}

	/**
	 *
	 * @return null Ambigous null, mixed>
	 */
	private function getParamsFromOrder()
	{
		$order = new Order(Tools::getValue('id_order'));
		$address = new Address((int)$order->id_address_delivery);

		$params = $this->populateOrderVariables($order, $address);

		$customer_mobile = $this->buildCustomerMobileNumber($address);

		if (!$customer_mobile)
		{
			$this->logMessage('Unable to retrive customers mobile number');
			return null;
		}

		$params['customer_mobile'] = $customer_mobile;

		return $params;
	}

	/**
	 *
	 * @param array $params
	 * @param string $template_id
	 */
	public function sendMessageForOrder($params, $template_id)
	{
		$this->logMessage(print_r($params, 1));

		$template = Configuration::get($template_id);

		$data = array();
		$data['text'] = $this->buildMessageBody($params, $template);
		$data['from'] = Configuration::get('SKEBBY_DEFAULT_NUMBER');
		$data['to'] = $params['customer_mobile'];
		$data['quality'] = Configuration::get('SKEBBY_DEFAULT_QUALITY');

		return $this->sendSmsApi($data);
	}

	/**
	 *
	 * @param Order $order
	 * @param Address $address
	 * @return array
	 */
	private function populateOrderVariables($order, $address)
	{
		$params = array();

		$customer_civility_result = Db::getInstance()->ExecuteS(
			'SELECT id_gender,firstname,lastname FROM '._DB_PREFIX_.'customer WHERE `id_customer` = '.(int)$order->id_customer);
		$firstname = (isset($address->firstname)) ? $address->firstname : '';
		$lastname = (isset($address->lastname)) ? $address->lastname : '';

		// Try to gess the civilty about the user.

		$civility_value = '';
		if(isset($customer_civility_result[0])){
			if (Tools::strtolower($firstname) === Tools::strtolower($customer_civility_result[0]['firstname']) &&
				Tools::strtolower($lastname) === Tools::strtolower($customer_civility_result[0]['lastname']))
				$civility_value = (isset($customer_civility_result['0']['id_gender'])) ? $customer_civility_result['0']['id_gender'] : '';
		}

			// Guess the civilty for given user. Defaults to no civilty.

		switch ($civility_value)
		{
			case 1:
				$civility = 'Mr.';
				break;
			case 2:
				$civility = 'Ms.';
				break;
			case 3:
				$civility = 'Miss.';
				break;
			default:
				$civility = '';
				break;
		}

		// get order date.
		// try to format the date according to language context

		$order_date = (isset($order->date_upd)) ? $order->date_upd : 0;

		// if ($this->context->language->id == 1) {
		// $order_date = date('m/d/Y', strtotime($order_date));
		// } else {
		// $order_date = date('d/m/Y', strtotime($order_date));
		// }

		// the order amount and currency.
		$order_price = (isset($order->total_paid)) ? $order->total_paid : 0;
		if(isset($this->context->currency->iso_code)){
			$order_price = $this->context->currency->iso_code.' '.$order_price;
		}

		if (_PS_VERSION_ < '1.5.0.0')
			$order_reference = (isset($order->id)) ? $order->id : '';
		else
			$order_reference = (isset($order->reference)) ? $order->reference : '';

			// Prepare variables for message template replacement.
			// We assume the user have specified a template for the message.

		$params['civility'] = $civility;
		$params['first_name'] = $firstname;
		$params['last_name'] = $lastname;
		$params['order_price'] = $order_price;
		$params['order_date'] = $order_date;
		$params['order_reference'] = $order_reference;

		return $params;
	}

	/**
	 * When a user places an order, the tracking code integrates in the order confirmation page.
	 *
	 * @param unknown $params
	 * @return boolean
	 */
	public function hookOrderConfirmation($params)
	{
		$params = $this->getParamsFromOrder();

		return $this->doOrderConfirmation($params);
	}



	/**
	 * When a user places an order, the tracking code integrates in the order confirmation page.
	 *
	 * @param unknown $params
	 * @return boolean
	 */
	public function doOrderConfirmation($params)
	{
		if (!$this->checkModuleStatus())
		{
		    $this->logMessage('Skebby module not enabled');
		    return false;
		}

		// If the user didn't opted for New Order notifications. Exit.
		if (!$this->shouldNotifyUponNewOrder())
		{
		    $this->logMessage('Used did not opted in for New Order notification');
		    return false;
		}

		if (!$params)
		{
			$this->logMessage('Unable to retreive params from order');
			return false;
		}

		$this->logMessage('hookOrderConfirmation');
		$this->logMessage(print_r($params, 1));

		$template = Configuration::get('SKEBBY_ORDER_TEMPLATE');

		$data = array();
		$data['text'] = $this->buildMessageBody($params, $template);
		$data['from'] = Configuration::get('SKEBBY_DEFAULT_NUMBER');
		$data['to'] = Configuration::get('SKEBBY_ORDER_RECIPIENT');
		$data['quality'] = Configuration::get('SKEBBY_DEFAULT_QUALITY');

		// Do Send Message
		return $this->sendSmsApi($data);
	}

	/**
	 * The user should have specified a country and mobile number.
	 *
	 * @param string $mobile_number
	 * @param Address $address
	 *
	 * @return string null mobile number or null
	 */
	private function buildCustomerMobileNumber($address)
	{
		// If for some reason the mobile number not specified in customer address. Exit.
		if (!isset($address->phone_mobile) || empty($address->phone_mobile))
		{
			$this->logMessage('Invalid customer mobile');
			return null;
		}

		$mobile_number = $address->phone_mobile;

		// Fetch the international prefix.
		// if not specified. Exit.

		$call_prefix_query = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
			'
				SELECT `call_prefix`
				FROM `'._DB_PREFIX_.'country`
				WHERE `id_country` = '.(int)$address->id_country);

		if (!isset($call_prefix_query['call_prefix']) || empty($call_prefix_query['call_prefix']))
		{
			$this->logMessage('Invalid customer country');
			return null;
		}

		$prefix = $call_prefix_query['call_prefix'];

		$this->logMessage("buildCustomerMobileNumber: $mobile_number / $prefix ");

		$mobile_number = trim($mobile_number);

		// replace double zero with plus
		if ($this->startsWith($mobile_number, '00'))
		{
			$mobile_number = str_replace('00', '', $mobile_number);
			return $mobile_number;
		}

		if ($this->startsWith($mobile_number, '+'))
		{
			$mobile_number = str_replace('+', '', $mobile_number);
			return $mobile_number;
		}

		return $prefix.$mobile_number;
	}

	/**
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @return boolean
	 */
	private function startsWith($haystack, $needle)
	{
		return $needle === '' || strrpos($haystack, $needle, -Tools::strlen($haystack)) !== false;
	}

	/**
	 * Return the user's credit.
	 *
	 * @return number
	 */
	public function getCredit()
	{
		return $this->api_client->getGatewayCredit();
	}

	/**
	 * Build an sms message merging a specified template, and given params array.
	 *
	 * @param array $params
	 * @param string $template
	 * @return string
	 */
	private function buildMessageBody($params, $template)
	{
		// TODO: we should perparse and notify the user if the message excedes a single message.
		if (isset($params['civility']))
			$template = str_replace('%civility%', $params['civility'], $template);

		if (isset($params['first_name']))
			$template = str_replace('%first_name%', $params['first_name'], $template);

		if (isset($params['last_name']))
			$template = str_replace('%last_name%', $params['last_name'], $template);

		if (isset($params['order_price']))
			$template = str_replace('%order_price%', $params['order_price'], $template);

		if (isset($params['order_date']))
			$template = str_replace('%order_date%', $params['order_date'], $template);

		if (isset($params['order_reference']))
			$template = str_replace('%order_reference%', $params['order_reference'], $template);

		return $template;
	}

	/**
	 * Send out a SMS using skebby API Client
	 *
	 * @param array $data
	 */
	private function sendSmsApi(array $data)
	{
		$this->logMessage('*********************** sendSmsApi ***********************');
		$this->logMessage(print_r($data, 1));

		$recipients = $data['to'];
		$text = $data['text'];
		$sms_type = $data['quality'];

		if ($this->shouldUseAlphasender())
		{
			$sender_number = '';
			$sender_string = Configuration::get('SKEBBY_DEFAULT_ALPHASENDER');
		}
		else
		{
			$sender_string = '';
			$sender_number = $data['from'];
		}

		$result = $this->api_client->sendSMS($recipients, $text, $sms_type, $sender_number, $sender_string);

		$this->logMessage($result);

		return $result;
	}

	/**
	 * Configure end render the admin's module form.
	 *
	 * @return string
	 */
	public function displayForm()
	{
		$data = array();
		$data['token'] = Tools::encrypt(Configuration::get('PS_SHOP_NAME'));
		$this->context->smarty->assign($data);

		// Get default language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		$options = array(
			array(
				'id_option' => 'basic',
				'name' => 'Basic'
			),
			array(
				'id_option' => 'classic',
				'name' => 'Classic'
			),
			array(
				'id_option' => 'classic_report',
				'name' => 'Classic+'
			)
		);

		$fields_form = array();
		array_push($fields_form, array());

		// Configuration Form
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Settings')
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Skebby Account Username'),
					'desc' => $this->l(
						'The username to access Skebby services. You cannot use your email or phone number, only username is allowed on gateway.'),
					'name' => 'SKEBBY_USERNAME',
					'size' => 20,
					'required' => true
				),
				array(
					'type' => 'text',
					'label' => $this->l('Skebby Account Password'),
					'desc' => $this->l('The password to access Skebby services'),
					'name' => 'SKEBBY_PASSWORD',
					'size' => 20,
					'required' => true
				),
				array(
					'type' => 'select',
					'label' => $this->l('SMS Quality:'),
					'desc' => $this->l('Choose the quality of the sent SMSs. Every quality has a different billing'),
					'hint' => $this->l('Describe here qualities'),
					'name' => 'SKEBBY_DEFAULT_QUALITY',
					'required' => true,
					'options' => array(
						'query' => $options,
						'id' => 'id_option',
						'name' => 'name'
					)
				),
				array(
					'type' => 'text',
					'label' => $this->l('Sender Mobile Number'),
					'desc' => $this->l('A verified number in your Skebby Account'),
					'hint' => $this->l('Invalid characters:').' <>;=#{}',
					'name' => 'SKEBBY_DEFAULT_NUMBER',
					'size' => 20,
					'required' => true
				),
				array(
					'type' => 'checkbox',
					'label' => $this->l('Use Alphanumeric Sender instead of mobile number?'),
					'desc' => $this->l(
						'Check this option if you prefer to send your sms using a string alias instead of a mobile number. Some restrictions apply.'),
					'name' => 'SKEBBY_ALPHASENDER',
					'required' => false,
					'values' => array(
						'query' => array(
							array(
								'id' => 'ACTIVE',
								'name' => $this->l('Prefer Alphanumeric Sender'),
								'val' => '1'
							)
						),
						'id' => 'id',
						'name' => 'name'
					)
				),
				array(
					'type' => 'text',
					'label' => $this->l('Alphanumeric Sender'),
					'desc' => $this->l('An Alphanumeric Sender registered on Skebby. Please refer to website docs for AGCOM specifications.'),
					'hint' => $this->l('Please refer to website docs for Italian AGCOM specifications'),
					'name' => 'SKEBBY_DEFAULT_ALPHASENDER',
					'size' => 20,
					'required' => false
				),
				array(
					'type' => 'checkbox',
					'label' => $this->l('New Order notification enabled?'),
					'desc' => $this->l('Check this option in order to receive a notification when a New Order is placed.'),
					'name' => 'SKEBBY_ORDER_NOTIFICATION',
					'required' => false,
					'values' => array(
						'query' => array(
							array(
								'id' => 'ACTIVE',
								'name' => $this->l('Enabled'),
								'val' => '1'
							)
						),
						'id' => 'id',
						'name' => 'name'
					)
				),
				array(
					'type' => 'text',
					'label' => $this->l('Order Recipient'),
					'desc' => $this->l('Recipient receiving SMS Order Notifications'),
					'hint' => $this->l('Please refer to website docs for AGCOM specifications'),
					'name' => 'SKEBBY_ORDER_RECIPIENT',
					'size' => 20,
					'required' => false
				),
				array(
					'type' => 'textarea',
					'label' => $this->l('Order message template'),
					'desc' => $this->l(
						'Type the message template for orders. You can use the variables %civility% %first_name% %last_name% %order_price% %order_date% %order_reference% that will be replaced in the message.'),
					'name' => 'SKEBBY_ORDER_TEMPLATE',
					'cols' => 40,
					'rows' => 5,
					'required' => false
				),
				array(
					'type' => 'checkbox',
					'label' => $this->l('Shipment Status notification enabled?'),
					'desc' => $this->l(
						'Check this option in order to send automatically a message to your customer when an order is shipped. The message will be sent if customer mobile phone and country are specified.'),
					'name' => 'SKEBBY_SHIPMENTSTATUS_NOTIFICATION',
					'required' => false,
					'values' => array(
						'query' => array(
							array(
								'id' => 'ACTIVE',
								'name' => $this->l('Enabled'),
								'val' => '1'
							)
						),
						'id' => 'id',
						'name' => 'name'
					)
				),
				array(
					'type' => 'textarea',
					'label' => $this->l('Shipment Status template'),
					'desc' => $this->l(
						'Type the message a customer receive when the order status transitions to SHIPPED. You can use the variables %civility% %first_name% %last_name% %order_price% %order_date% %order_reference% that will be replaced in the message.'),
					'name' => 'SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE',
					'cols' => 40,
					'rows' => 5,
					'required' => false
				),
				array(
					'type' => 'free',
					'label' => $this->l('Check the integration'),
					'desc' => $this->display(__FILE__, 'views/templates/admin/scripts.tpl'),
					'name' => 'FREE_TEXT',
					'required' => false
				)
			),
			'submit' => array(
				'title' => $this->l('Save settings'),
				'class' => 'button'
			)
		);

		$helper = new HelperForm();

		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true; // false -> remove toolbar
		$helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' => array(
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.
				Tools::getAdminTokenLite('AdminModules')
			),
			'back' => array(
				'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);

		// Load current value
		$helper->fields_value['SKEBBY_USERNAME'] = Configuration::get('SKEBBY_USERNAME');
		$helper->fields_value['SKEBBY_PASSWORD'] = Configuration::get('SKEBBY_PASSWORD');
		$helper->fields_value['SKEBBY_DEFAULT_QUALITY'] = Configuration::get('SKEBBY_DEFAULT_QUALITY');
		$helper->fields_value['SKEBBY_DEFAULT_NUMBER'] = Configuration::get('SKEBBY_DEFAULT_NUMBER');
		$helper->fields_value['SKEBBY_DEFAULT_ALPHASENDER'] = Configuration::get('SKEBBY_DEFAULT_ALPHASENDER');
		$helper->fields_value['SKEBBY_ALPHASENDER_ACTIVE'] = ((string)Configuration::get('SKEBBY_ALPHASENDER_ACTIVE') == '1');
		$helper->fields_value['SKEBBY_ORDER_NOTIFICATION_ACTIVE'] = ((string)Configuration::get('SKEBBY_ORDER_NOTIFICATION_ACTIVE') == '1');
		$helper->fields_value['SKEBBY_ORDER_RECIPIENT'] = Configuration::get('SKEBBY_ORDER_RECIPIENT');
		$helper->fields_value['SKEBBY_ORDER_TEMPLATE'] = Configuration::get('SKEBBY_ORDER_TEMPLATE');
		$helper->fields_value['SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE'] = Configuration::get('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE');
		$helper->fields_value['SKEBBY_SHIPMENTSTATUS_NOTIFICATION_ACTIVE'] = ((string)Configuration::get('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_ACTIVE') ==
			'1');
		$helper->fields_value['FREE_TEXT'] = Configuration::get('FREE_TEXT');

		$theform = '';

		$this->context->smarty->assign($data);

		$theform .= $this->display(__FILE__, 'views/templates/admin/logo.tpl');
		if(!$this->isConfigured()){
			$theform .= $this->display(__FILE__, 'views/templates/admin/intro.tpl');
		}else{
			$theform .= $this->display(__FILE__, 'views/templates/admin/configured.tpl');
		}

		$theform .= $this->display(__FILE__, 'views/templates/admin/tabs_start.tpl');


		$theform .= '<div class="tab-content">';
		$theform .= '<div class="tab-pane active" id="settings">';
		$theform .= $helper->generateForm($fields_form);
		$theform .= '</div>';
		$theform .= '<div class="tab-pane" id="customers">';
		$theform .= $this->renderList();
		$theform .= '</div>';
		$theform .= '<div class="tab-pane" id="messages">';
		$theform .= 'messages';
		$theform .= '</div>';
		$theform .= '</div>';

		$theform .= $this->display(__FILE__, 'views/templates/admin/tabs_end.tpl');

		return $theform;
	}

	/**
	 * When submitted the config form!
	 *
	 * @return string
	 */
	public function getContent()
	{
		$output = null;

		if (Tools::isSubmit('submit'.$this->name))
		{
			$skebby_username = (string)Tools::getValue('SKEBBY_USERNAME');
			if (!$skebby_username || empty($skebby_username) || !Validate::isGenericName($skebby_username))
				$output .= $this->displayError($this->l('Invalid username'));
			else
			{
				Configuration::updateValue('SKEBBY_USERNAME', $skebby_username);
				$output .= $this->displayConfirmation($this->l('Username updated'));
			}

			// Password field

			$skebby_password = (string)Tools::getValue('SKEBBY_PASSWORD');
			if (!$skebby_password || empty($skebby_password) || !Validate::isGenericName($skebby_password))
				$output .= $this->displayError($this->l('Invalid password'));
			else
			{
				Configuration::updateValue('SKEBBY_PASSWORD', $skebby_password);
				$output .= $this->displayConfirmation($this->l('Password updated'));
			}

			// Alpha sender Opt-in

			$use_alpha_sender = Tools::getValue('SKEBBY_ALPHASENDER_ACTIVE');
			Configuration::updateValue('SKEBBY_ALPHASENDER', $use_alpha_sender);
			Configuration::updateValue('SKEBBY_ALPHASENDER_ACTIVE', $use_alpha_sender);

			$this->logMessage('Use alpha sender instead of a sender number');
			$this->logMessage($use_alpha_sender);

			// Alphanumeric sender. we validate just if the user opted in.

			if ($use_alpha_sender)
			{
				$skebby_alpha_sender = (string)Tools::getValue('SKEBBY_DEFAULT_ALPHASENDER');
				$skebby_alpha_sender = trim($skebby_alpha_sender);

				if (!$skebby_alpha_sender || empty($skebby_alpha_sender) || !$this->isValidAlphasender($skebby_alpha_sender))
					$output .= $this->displayError($this->l('Invalid Alpha Sender'));

				else
				{
					Configuration::updateValue('SKEBBY_DEFAULT_ALPHASENDER', $skebby_alpha_sender);
					$output .= $this->displayConfirmation($this->l('Alpha Sender updated'));
				}
			}

			// Mobile number field. only if not alpha sender

			$skebby_mobile_number = (string)Tools::getValue('SKEBBY_DEFAULT_NUMBER');
			$skebby_mobile_number = $this->normalizeNumber($skebby_mobile_number);

			if (!$skebby_mobile_number || empty($skebby_mobile_number) || !$this->isValidMobileNumber($skebby_mobile_number))
				$output .= $this->displayError($this->l('Invalid Sender Mobile Number'));
			else
			{

				Configuration::updateValue('SKEBBY_DEFAULT_NUMBER', $skebby_mobile_number);
				$output .= $this->displayConfirmation($this->l('Sender Number updated'));
			}

			// Default quality

			$skebby_default_quality = (string)Tools::getValue('SKEBBY_DEFAULT_QUALITY');
			if (!$skebby_default_quality || empty($skebby_default_quality) || !Validate::isGenericName($skebby_default_quality))
				$output .= $this->displayError($this->l('Invalid quality'));

			else
			{
				Configuration::updateValue('SKEBBY_DEFAULT_QUALITY', $skebby_default_quality);
				$output .= $this->displayConfirmation($this->l('SMS Quality updated'));
			}

			// New Order Notification active

			$skebby_neworder_active = Tools::getValue('SKEBBY_ORDER_NOTIFICATION_ACTIVE');
			Configuration::updateValue('SKEBBY_ORDER_NOTIFICATION', $skebby_neworder_active);
			Configuration::updateValue('SKEBBY_ORDER_NOTIFICATION_ACTIVE', $skebby_neworder_active);

			$this->logMessage('New order notification active');
			$this->logMessage($skebby_neworder_active);

			if ($skebby_neworder_active)
			{

				// New Order notification Template

				$skebby_order_template = (string)Tools::getValue('SKEBBY_ORDER_TEMPLATE');
				if (!$skebby_order_template || empty($skebby_order_template))
					$output .= $this->displayError($this->l('Invalid order template'));
				else
				{
					Configuration::updateValue('SKEBBY_ORDER_TEMPLATE', $skebby_order_template);
					$output .= $this->displayConfirmation($this->l('Order Template updated'));
				}

				// New Order Recipient

				$skebby_order_recipient = (string)Tools::getValue('SKEBBY_ORDER_RECIPIENT');
				$skebby_order_recipient = $this->normalizeNumber($skebby_order_recipient);

				if (!$skebby_order_recipient || empty($skebby_order_recipient) || !Validate::isGenericName($skebby_order_recipient) ||
					!$this->isValidMobileNumber($skebby_order_recipient))
					$output .= $this->displayError($this->l('Invalid Order Recipient'));
				else
				{
					Configuration::updateValue('SKEBBY_ORDER_RECIPIENT', $skebby_order_recipient);
					$output .= $this->displayConfirmation($this->l('Order Recipient Updated'));
				}
			}

			// Shipment active
			// Update the checkbox

			$skebby_shipment_active = Tools::getValue('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_ACTIVE');
			Configuration::updateValue('SKEBBY_SHIPMENTSTATUS_NOTIFICATION', $skebby_shipment_active);
			Configuration::updateValue('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_ACTIVE', $skebby_shipment_active);

			$this->logMessage('shipment active');
			$this->logMessage($skebby_shipment_active);

			// Shipment Template
			if ($skebby_shipment_active)
			{

				$skebby_shipment_template = (string)Tools::getValue('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE');
				if (!$skebby_shipment_template || empty($skebby_shipment_template) || !Validate::isGenericName($skebby_shipment_template))
					$output .= $this->displayError($this->l('Invalid Shipment template'));
				else
				{
					Configuration::updateValue('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE', $skebby_shipment_template);
					$output .= $this->displayConfirmation($this->l('Shipment Template updated'));
				}
			}

			$this->logMessage('Updated config Values');

			$this->dumpConfig();
		}

		return $output.$this->displayForm();
	}

	/**
	 */
	private function dumpConfig()
	{
		if (!$this->development_mode)
			return;

			// general
		$this->logMessage('SKEBBY_PASSWORD: '.Tools::getValue('SKEBBY_PASSWORD'));
		$this->logMessage('SKEBBY_USERNAME: '.Tools::getValue('SKEBBY_USERNAME'));
		$this->logMessage('SKEBBY_DEFAULT_QUALITY: '.Tools::getValue('SKEBBY_DEFAULT_QUALITY'));

		// Sender number or alphanumeric sender
		$this->logMessage('SKEBBY_DEFAULT_NUMBER: '.Tools::getValue('SKEBBY_DEFAULT_NUMBER'));
		$this->logMessage('SKEBBY_DEFAULT_ALPHASENDER: '.Tools::getValue('SKEBBY_DEFAULT_ALPHASENDER'));
		$this->logMessage('SKEBBY_ALPHASENDER_ACTIVE: '.Tools::getValue('SKEBBY_ALPHASENDER_ACTIVE'));

		// feature new order
		$this->logMessage('SKEBBY_ORDER_NOTIFICATION_ACTIVE: '.Tools::getValue('SKEBBY_ORDER_NOTIFICATION_ACTIVE'));
		$this->logMessage('SKEBBY_ORDER_RECIPIENT: '.Tools::getValue('SKEBBY_ORDER_RECIPIENT'));
		$this->logMessage('SKEBBY_ORDER_TEMPLATE: '.Tools::getValue('SKEBBY_ORDER_TEMPLATE'));

		// feature shipment
		$this->logMessage('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE: '.Tools::getValue('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE'));
		$this->logMessage('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_ACTIVE: '.Tools::getValue('SKEBBY_SHIPMENTSTATUS_NOTIFICATION_ACTIVE'));
		$this->logMessage('SKEBBY_SHIPMENTSTATUS_NOTIFICATION: '.Tools::getValue('SKEBBY_SHIPMENTSTATUS_NOTIFICATION'));
	}

	/**
	 *
	 * @param unknown $mobile_number
	 * @return boolean number
	 */
	private function isValidMobileNumber($mobile_number)
	{
		return preg_match('/^[0-9]{8,12}$/', $mobile_number);
	}

	/**
	 * We do not implement the validation now.
	 * Is too complex.
	 * Will be done in next release.
	 *
	 * @param string $alpha_sender
	 * @return boolean
	 */
	private function isValidAlphasender($alpha_sender)
	{
		return (trim($alpha_sender) !== '');
	}

	/**
	 * Normalize a mobile number string
	 *
	 * @param unknown $mobile_number
	 * @return boolean number
	 */
	private function normalizeNumber($mobile_number)
	{
		$mobile_number = str_replace('+', '', $mobile_number);
		$mobile_number = preg_replace('/\s+/', '', $mobile_number);
		return $mobile_number;
	}

	/**
	 * Method is used to check the current status of the module whether its active or not.
	 */
	private function checkModuleStatus()
	{
		return Module::isEnabled('skebby');
	}

	/**
	 * Add a message to the Log.
	 *
	 * @param unknown $message
	 */
	private function logMessage($message)
	{
		if (!$this->log_enabled)
			return;

		$this->logger->logDebug($message);
	}

	/**
	 * Initialize the logger
	 */
	private function initLogger()
	{
		if (!$this->log_enabled)
			return;

		$this->logger = new FileLogger(0);
		$this->logger->setFilename(_PS_ROOT_DIR_.'/log/skebby.log');
	}



	public function renderList()
	{
	    $fields_list = array(
	        'id' => array(
	            'title' => $this->l('ID'),
	            'search' => false,
	        ),
	        'shop_name' => array(
	            'title' => $this->l('Shop'),
	            'search' => false,
	        ),
	        'gender' => array(
	            'title' => $this->l('Gender'),
	            'search' => false,
	        ),
	        'lastname' => array(
	            'title' => $this->l('Lastname'),
	            'search' => true,
	        ),
	        'firstname' => array(
	            'title' => $this->l('Firstname'),
	            'search' => true,
	        ),
	        'email' => array(
	            'title' => $this->l('Email'),
	            'search' => false,
	        ),
	        'phone_mobile' => array(
	            'title' => $this->l('Mobile'),
	            'search' => true,
	        ),
	        'subscribed' => array(
	            'title' => $this->l('Subscribed'),
	            'type' => 'bool',
	            'active' => 'subscribed',
	            'search' => false,
	        ),
	        'newsletter_date_add' => array(
	            'title' => $this->l('Subscribed on'),
	            'type' => 'date',
	            'search' => false,
	        )
	    );

	    if (!Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE'))
	        unset($fields_list['shop_name']);


	    $helper_list = New HelperList();
	    $helper_list->module = $this;
	    $helper_list->title = $this->l('Registered Customers');
	    $helper_list->shopLinkType = '';
	    $helper_list->no_link = true;
	    $helper_list->show_toolbar = true;
	    $helper_list->simple_header = false;
	    $helper_list->identifier = 'id';
	    $helper_list->table = 'customers';
	    $helper_list->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name;
	    $helper_list->token = Tools::getAdminTokenLite('AdminModules');
	    $helper_list->actions = array('viewCustomer');
	   /* $helper_list->toolbar_btn['export'] = array(
	        'href' => $helper_list->currentIndex.'&exportSubscribers&token='.$helper_list->token,
	        'desc' => $this->l('Export')
	    );*/
	    /* Before 1.6.0.7 displayEnableLink() could not be overridden in Module class
	     we declare another row action instead
	    if (version_compare(_PS_VERSION_, '1.6.0.7', '<'))
	    {
	        unset($fields_list['subscribed']);
	        $helper_list->actions = array_merge($helper_list->actions, array('unsubscribe'));
	    }*/
	    // This is needed for displayEnableLink to avoid code duplication
	    $this->_helperlist = $helper_list;
	    /* Retrieve list data */
	    $customers = $this->getCustomers();
	    $helper_list->listTotal = count($customers);
	    /* Paginate the result */
	    $page = ($page = Tools::getValue('submitFilter'.$helper_list->table)) ? $page : 1;
	    $pagination = ($pagination = Tools::getValue($helper_list->table.'_pagination')) ? $pagination : 50;
	    $subscribers = $this->paginateSubscribers($customers, $page, $pagination);
	    return $helper_list->generateList($customers, $fields_list);
	}

	public function paginateSubscribers($subscribers, $page = 1, $pagination = 50)
	{
	    if(count($subscribers) > $pagination)
	        $subscribers = array_slice($subscribers, $pagination * ($page - 1), $pagination);
	    return $subscribers;
	}

	public function renderSearchForm()
	{
	    $fields_form = array(
	        'form' => array(
	            'legend' => array(
	                'title' => $this->l('Search for addresses'),
	                'icon' => 'icon-search'
	            ),
	            'input' => array(
	                array(
	                    'type' => 'text',
	                    'label' => $this->l('Email address to search'),
	                    'name' => 'searched_email',
	                    'class' => 'fixed-width-xxl',
	                    'desc' => $this->l('Example: contact@prestashop.com or @prestashop.com')
	                ),
	            ),
	            'submit' => array(
	                'title' => $this->l('Search'),
	                'icon' => 'process-icon-refresh',
	            )
	        ),
	    );
	    $helper = new HelperForm();
	    $helper->table = $this->table;
	    $helper->identifier = $this->identifier;
	    $helper->submit_action = 'searchEmail';
	    $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
	    $helper->token = Tools::getAdminTokenLite('AdminModules');
	    $helper->tpl_vars = array(
	        'fields_value' => array('searched_email' => $this->_searched_email),
	        'languages' => $this->context->controller->getLanguages(),
	        'id_language' => $this->context->language->id
	    );
	    return $helper->generateForm(array($fields_form));
	}


	public function getCustomers()
	{
	    $dbquery = new DbQuery();
	    $dbquery->select('c.`id_customer` AS `id`, CONCAT("+", CONCAT(p.`call_prefix`,a.`phone_mobile` )) AS `phone_mobile`, s.`name` AS `shop_name`, gl.`name` AS `gender`, c.`lastname`, c.`firstname`, c.`email`, c.`newsletter` AS `subscribed`, c.`newsletter_date_add`');
	    $dbquery->from('customer', 'c');
	    $dbquery->leftJoin('shop', 's', 's.id_shop = c.id_shop');
	    $dbquery->leftJoin('gender', 'g', 'g.id_gender = c.id_gender');
	    $dbquery->leftJoin('address', 'a', 'a.id_customer = c.id_customer');
	    $dbquery->leftJoin('country', 'p', 'p.id_country = a.id_country');
	    $dbquery->leftJoin('gender_lang', 'gl', 'g.id_gender = gl.id_gender AND gl.id_lang = '.(int)$this->context->employee->id_lang);
// 	    $dbquery->where('c.`newsletter` = 1');
// 	    if ($this->_searched_email)
// 	        $dbquery->where('c.`email` LIKE \'%'.pSQL($this->_searched_email).'%\' ');
	    $customers = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery->build());
// 	    $dbquery = new DbQuery();
// 	    $dbquery->select('CONCAT(\'N\', n.`id`) AS `id`, s.`name` AS `shop_name`, NULL AS `gender`, NULL AS `lastname`, NULL AS `firstname`, n.`email`, n.`active` AS `subscribed`, n.`newsletter_date_add`');
// 	    $dbquery->from('newsletter', 'n');
// 	    $dbquery->leftJoin('shop', 's', 's.id_shop = n.id_shop');
// 	    $dbquery->where('n.`active` = 1');
// 	    if ($this->_searched_email)
// 	        $dbquery->where('n.`email` LIKE \'%'.pSQL($this->_searched_email).'%\' ');
// 	    $non_customers = Db::getInstance()->executeS($dbquery->build());
// 	    $subscribers = array_merge($customers, $non_customers);






	    return $customers;
	}



}