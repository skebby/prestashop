<?php
if (! defined ( '_PS_VERSION_' ))
	exit ();

require_once (dirname(__FILE__).'/lib/Skebby/ApiClient.php');


class Skebby extends Module {
	
	
	private $logger;

	/**
	 * 
	 * @var SkebbyApiClient
	 */
	private $apiClient;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		
		$this->name = 'skebby';
		
		
// 		if (version_compare(_PS_VERSION_, '1.5', '>')){
			$this->tab = 'emailing';
// 		}else {
// 			$this->tab = 'advertising_marketing';
// 		}
		
		$this->version = '1.0.0';
		
		$this->author = 'Skebby Dev Team';
		
		$this->need_instance = 0;
		
		$this->ps_versions_compliancy = array (
				'min' => '1.6',
				'max' => _PS_VERSION_ 
		);
		$this->bootstrap = true;
		
		parent::__construct ();
		
		$this->displayName = $this->l ( 'Skebby SMS Plugin' );
		$this->description = $this->l ( 'With Skebby SMS Plugin for Prestashop you will be able to integrate all the Skebby features with no coding. This plugin requires to  have an account with skebby and have available credit.' );
		
		$this->confirmUninstall = $this->l ( 'Are you sure you want to uninstall? You will not be able to send sms notifications.' );
		
		$this->langid = !empty($this->context->language->id) ? $this->context->language->id :'';
		$this->lang_cookie = $this->context->cookie;
		
		
		if (! Configuration::get ( 'SKEBBY_DEFAULT_NUMBER' ))
			$this->warning = $this->l ( 'Missing sender mobile number' );

		if (! Configuration::get ( 'SKEBBY_PASSWORD' ))
			$this->warning = $this->l ( 'Missing Skebby Account Password' );
		
		if (! Configuration::get ( 'SKEBBY_USERNAME' ))
			$this->warning = $this->l ( 'Missing Skebby Account Username' );


		
		
		// Checking Extension
		if (!extension_loaded('curl') || !ini_get('allow_url_fopen'))
		{
			if (!extension_loaded('curl') && !ini_get('allow_url_fopen'))
				$this->warning = $this->l('You must enable cURL extension and allow_url_fopen option on your server if you want to use this module.');
			else if (!extension_loaded('curl'))
				$this->warning = $this->l('You must enable cURL extension on your server if you want to use this module.');
			else if (!ini_get('allow_url_fopen'))
				$this->warning = $this->l('You must enable allow_url_fopen option on your server if you want to use this module.');
		}
		

		$this->initLogger();
		
		
		// instance the Skebby Api Client
		$this->apiClient = new SkebbyApiClient();
		
	}


	

	
	
	
	
	/**
	 * Install the Plugin registering to the payment and order hooks
	 * 
	 * @return boolean
	 */
	public function install()
	{
	  if (Shop::isFeatureActive()){
	  	Shop::setContext(Shop::CONTEXT_ALL);
	  }
	  
	  $this->logMessage("Installing Skebby Module");

	  $success = (parent::install() &&
				$this->registerHook('payment') &&
				$this->registerHook('newOrder') &&
				$this->registerHook('actionOrderStatusPostUpdate') &&
				$this->registerHook('orderConfirmation'));
	  
	  if($success){
	  	
	  	  Configuration::updateValue ( 'SKEBBY_DEFAULT_QUALITY', 'classic' );
		  
	  	  $this->logMessage("Successfully installed Skebby Module");
		  $this->logMessage("Default Quality is: " . Tools::getValue('SKEBBY_DEFAULT_QUALITY'));
	  }else{
		  $this->logMessage("Error Installing Skebby Module");
	  }

	  return $success;
	}

	
	
	
	/**
	 * 
	 * @return boolean
	 */
	public function uninstall()
	{
		
		$this->logMessage("Uninstalling Skebby Module");
		
		$success =  (parent::uninstall() &&
		Configuration::deleteByName('SKEBBY_USERNAME') &&
		Configuration::deleteByName('SKEBBY_PASSWORD') &&
		Configuration::deleteByName('SKEBBY_DEFAULT_QUALITY') &&
		Configuration::deleteByName('SKEBBY_DEFAULT_ALPHASENDER') &&
		Configuration::deleteByName('SKEBBY_DEFAULT_NUMBER')
		);
		
		
		if($success){
			$this->logMessage("Skebby Module Uninstalled Successfully");
		}
		
		$this->dumpConfig();
		
		
		return $success;
		
	}
	

	
	
	/**
	 * Register the Module to the payment Hook 
	 * 
	 * @param unknown $params
	 * @return boolean
	 */
	public function hookPayment($params)
	{
		
		if (!$this->checkModuleStatus())
			return false;
		
		$this->logMessage("hookOrderConfirmation");
		$this->logMessage(print_r($params, 1));
		
		$data = array();
		$data['from'] = Configuration::get('SKEBBY_DEFAULT_NUMBER');
		$data['text'] = $this->buildMessageBody($params);
		$data['to'] = Configuration::get('SKEBBY_DEFAULT_NUMBER');
			
		// Do Send Message
		$this->sendSmsApi($msgbody);
		
	}
	
	


	public function hookNewOrder($params)
	{
		return $this->hookActionOrderStatusPostUpdate($params);
	}
	
	
	
	public function hookActionOrderStatusPostUpdate($params)
	{
		
		if (!$this->checkModuleStatus())
			return false;
		
		$this->logMessage("hookActionOrderStatusPostUpdate");
		$this->logMessage(print_r($params, 1));
		
		
		//$params['newOrderStatus'] // after status changed
		//$params['orderStatus'] // after order is placed
	}
	
	
	
	/**
	 * When a user places an order, the tracking code integrates in the order confirmation page.
	 * 
	 * @param unknown $params
	 * @return boolean
	 */
	public function hookOrderConfirmation($params)
	{
		if (!$this->checkModuleStatus()){
			return false;			
		}

		$this->logMessage("hookOrderConfirmation");
		$this->logMessage(print_r($params, 1));

		$data = array();
		$data['text'] = $this->buildMessageBody($params);

		
		// Do Send Message
		$this->sendSmsApi($data);
	}
	
	
	
	
	// ********************************************************************************************************
	// PRIVATES 
	// ********************************************************************************************************
	
	

	
	
	private function buildMessageBody($params){
		$this->logMessage("buildMessageBody");
		$this->logMessage(print_r($params, 1));
		return 'Test Message';
	}
		
		
		
	/**
	 * Send out a SMS
	 * @param unknown $params
	 */
	private function sendSmsApi($params){

		$this->logMessage("sendSmsApi");
		$this->logMessage(print_r($params, 1));
		
		$data = array();
		$data['from'] = Configuration::get('SKEBBY_DEFAULT_NUMBER');
		$data['text'] = 'Missing Message Body';
		$data['to'] = Configuration::get('SKEBBY_DEFAULT_NUMBER');
		$data['quality'] = Configuration::get('SKEBBY_DEFAULT_QUALITY');

		// Merge params
		$data = array_merge_recursive($data, $params);
		
		$this->logMessage(print_r($data, 1));
		
		$this->apiClient->sendSMS($data);
		
	}
	
	


	public function displayForm()
	{
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
				),
		);
	
	
	
		// Display Some Info
		$fields_form[0]['form'] = array(
				'legend' => array(
						'title' => $this->l('Skebby'),
						'image' => 'img/icons/skebby.png'
				),
		);
	
	
	
	
			
		// Configuration Form
		$fields_form[1]['form'] = array(
				'legend' => array(
						'title' => $this->l('Settings'),
						'image' => 'img/icons/skebby.png'
				),
				'input' => array(
						array(
								'type' => 'text',
								'label' => $this->l('Skebby Account Username'),
								'desc' => $this->l('The username to access Skebby services'),
								'name' => 'SKEBBY_USERNAME',
								'size' => 20,
								'required' => true
						),
						array(
								'type' => 'password',
								'label' => $this->l('Skebby Account Password'),
								'desc' => $this->l('The password to access Skebby services'),
								'name' => 'SKEBBY_PASSWORD',
								'size' => 20,
								'required' => true
						),
						array(
								'type' => 'select',
								'label' => $this->l('Default SMS Quality:'),
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
				'type' => 'text',
				'label' => $this->l('Alphanumeric Sender'),
				'desc' => $this->l('An alphanumeric sender registered on Skebby'),
				'hint' => $this->l('Please refer to website docs for AGCOM specifications'),
				'name' => 'SKEBBY_DEFAULT_ALPHASENDER',
				'size' => 20,
				'required' => true
				),
				),
				'submit' => array(
				'title' => $this->l('Save'),
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
		$helper->show_toolbar = true;        // false -> remove toolbar
		$helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
				'save' =>
				array(
						'desc' => $this->l('Save'),
						'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
						'&token='.Tools::getAdminTokenLite('AdminModules'),
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
			
		return $helper->generateForm($fields_form);
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
			$skebby_username = strval(Tools::getValue('SKEBBY_USERNAME'));
			if (!$skebby_username
			|| empty($skebby_username)
			|| !Validate::isGenericName($skebby_username))
				$output .= $this->displayError($this->l('Invalid username'));
			else
			{
				Configuration::updateValue('SKEBBY_USERNAME', $skebby_username);
				$output .= $this->displayConfirmation($this->l('Username updated'));
			}
				
			// Password field
	
			$skebby_password = strval(Tools::getValue('SKEBBY_PASSWORD'));
			if (!$skebby_password
			|| empty($skebby_password)
			|| !Validate::isGenericName($skebby_password))
				$output .= $this->displayError($this->l('Invalid password'));
			else
			{
				Configuration::updateValue('SKEBBY_PASSWORD', $skebby_password);
				$output .= $this->displayConfirmation($this->l('Password updated'));
			}
	
			// Mobile number field
				
			$skebby_mobile_number = strval(Tools::getValue('SKEBBY_DEFAULT_NUMBER'));
			if (!$skebby_mobile_number
			|| empty($skebby_mobile_number)
			|| !Validate::isGenericName($skebby_mobile_number))
				$output .= $this->displayError($this->l('Invalid number'));
			else
			{
				Configuration::updateValue('SKEBBY_DEFAULT_NUMBER', $skebby_mobile_number);
				$output .= $this->displayConfirmation($this->l('Sender Number updated'));
			}
	
				
			// Default quality
				
			$skebby_default_quality = strval(Tools::getValue('SKEBBY_DEFAULT_QUALITY'));
			if (!$skebby_default_quality
			|| empty($skebby_default_quality)
			|| !Validate::isGenericName($skebby_default_quality))
				$output .= $this->displayError($this->l('Invalid quality'));
			else
			{
				Configuration::updateValue('SKEBBY_DEFAULT_QUALITY', $skebby_default_quality);
				$output .= $this->displayConfirmation($this->l('SMS Quality updated'));
			}
				
				
			$this->logMessage('Updated config Values');
			
			$this->dumpConfig();
	
		}
	
		return $output.$this->displayForm();
	}
	
	
	private function dumpConfig()
	{
		$this->logMessage("SKEBBY_PASSWORD: " . Tools::getValue('SKEBBY_PASSWORD'));
		$this->logMessage("SKEBBY_USERNAME: " . Tools::getValue('SKEBBY_USERNAME'));
		$this->logMessage("SKEBBY_DEFAULT_QUALITY: " . Tools::getValue('SKEBBY_DEFAULT_QUALITY'));
		$this->logMessage("SKEBBY_DEFAULT_NUMBER: " . Tools::getValue('SKEBBY_DEFAULT_NUMBER'));
		$this->logMessage("SKEBBY_DEFAULT_ALPHASENDER: " . Tools::getValue('SKEBBY_DEFAULT_ALPHASENDER'));
	}
	
	
	
	private function isValidNumber($mobile_number)
	{
		return true;
		return preg_match('^[a-zA-Z]{2}[0-9]{8,10}$', $mobile_number);
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
		$this->logger->logDebug($message);
	}
	


	/**
	 * Initialize the logger
	 */
	private function initLogger(){
	
		$this->logger = new FileLogger(0);
		$this->logger->setFilename(_PS_ROOT_DIR_.'/log/skebby.log');
	
	}
	
	
}