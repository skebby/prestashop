<?php
if (! defined('_PS_VERSION_'))
    exit('');

require_once (dirname(__FILE__) . '/lib/Skebby/ApiClient.php');

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
     *
     * @var SkebbyApiClient
     */
    private $apiClient;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'skebby';
        
        $this->tab = 'emailing';
        
        // if (version_compare(_PS_VERSION_, '1.5', '>')){
        // $this->tab = 'emailing';
        // }else {
        // $this->tab = 'advertising_marketing';
        // }
        
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
        
        $this->description = $this->l('With Skebby SMS module for Prestashop you will be able to integrate all the Skebby features with no coding. This module requires to  have an account with skebby and have available credit.');
        
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall? You will not be able to send sms notifications.');
        
        $this->langid = ! empty($this->context->language->id) ? $this->context->language->id : '';
        $this->lang_cookie = $this->context->cookie;
        
        if (! Configuration::get('SKEBBY_DEFAULT_NUMBER'))
            $this->warning = $this->l('Missing sender mobile number');
        
        if (! Configuration::get('SKEBBY_PASSWORD'))
            $this->warning = $this->l('Missing Skebby Account Password');
        
        if (! Configuration::get('SKEBBY_USERNAME'))
            $this->warning = $this->l('Missing Skebby Account Username');
            
            // Checking Extension
        if (! extension_loaded('curl') || ! ini_get('allow_url_fopen')) {
            if (! extension_loaded('curl') && ! ini_get('allow_url_fopen'))
                $this->warning = $this->l('You must enable cURL extension and allow_url_fopen option on your server if you want to use this module.');
            else 
                if (! extension_loaded('curl'))
                    $this->warning = $this->l('You must enable cURL extension on your server if you want to use this module.');
                else 
                    if (! ini_get('allow_url_fopen'))
                        $this->warning = $this->l('You must enable allow_url_fopen option on your server if you want to use this module.');
        }
        
        $this->initLogger();
        
        // instance the Skebby Api Client
        $this->apiClient = new SkebbyApiClient();
        $this->apiClient->setCredentials(Configuration::get('SKEBBY_USERNAME'), Configuration::get('SKEBBY_PASSWORD'));
    }

    /**
     * Install the Plugin registering to the payment and order hooks
     *
     * @return boolean
     */
    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }
        
        $this->logMessage("Installing Skebby Module");
        
        $success = (parent::install() && $this->registerHook('orderConfirmation'));
        
        if ($success) {
            
            Configuration::updateValue('SKEBBY_DEFAULT_QUALITY', 'classic');
            
            $this->logMessage("Successfully installed Skebby Module");
            $this->logMessage("Default Quality is: " . Tools::getValue('SKEBBY_DEFAULT_QUALITY'));
        } else {
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
        
        $success = (parent::uninstall() && Configuration::deleteByName('SKEBBY_USERNAME') && Configuration::deleteByName('SKEBBY_PASSWORD') && Configuration::deleteByName('SKEBBY_DEFAULT_QUALITY') && Configuration::deleteByName('SKEBBY_DEFAULT_ALPHASENDER') && Configuration::deleteByName('SKEBBY_DEFAULT_NUMBER'));
        
        if ($success) {
            $this->logMessage("Skebby Module Uninstalled Successfully");
        }
        
        $this->dumpConfig();
        
        return $success;
    }

    /**
     * When a user places an order, the tracking code integrates in the order confirmation page.
     *
     * @param unknown $params            
     * @return boolean
     */
    public function hookOrderConfirmation($params)
    {
        if (! $this->checkModuleStatus()) {
            return false;
        }
        
        $this->logMessage("hookOrderConfirmation");
        $this->logMessage(print_r($params, 1));
        
        $data = array();
        $data['text'] = $this->buildMessageBody($params, 'SKEBBY_ORDER_TEMPLATE');
        $data['from'] = Configuration::get('SKEBBY_DEFAULT_NUMBER');
        $data['to'] = Configuration::get('SKEBBY_ORDER_RECIPIENT');
        $data['quality'] = Configuration::get('SKEBBY_DEFAULT_QUALITY');
        
        // Do Send Message
        return $this->sendSmsApi($data);
    }

    /**
     * Return the user's credit.
     *
     * @return number
     */
    public function getCredit()
    {
        return $this->apiClient->getGatewayCredit();
    }
    
    // ********************************************************************************************************
    // PRIVATES
    // ********************************************************************************************************
    private function buildMessageBody($params, $template_id)
    {
        $template = Configuration::get($template_id);
        $message = $template;
        
        $message = str_replace("%currency%", $params['currency'], $message);
        $message = str_replace("%total_to_pay%", $params['total_to_pay'], $message);
        
        return $message;
    }

    /**
     * Send out a SMS
     *
     * @param array $data            
     */
    private function sendSmsApi(array $data)
    {
        $this->logMessage("*********************** sendSmsApi ***********************");
        $this->logMessage(print_r($data, 1));
        
        $recipients = $data['to'];
        $text = $data['text'];
        $sms_type = $data['quality'];
        $sender_number = $data['from'];
        
        $result = $this->apiClient->sendSMS($recipients, $text, $text, $sender_number);
        
        $this->logMessage($result);
        
        return $result;
    }

    /**
     *
     * @return unknown
     */
    public function displayForm()
    {
        $data = array();
        $data['token'] = Tools::encrypt(Configuration::get('PS_SHOP_NAME'));
        $this->context->smarty->assign($data);
        
        // Get default language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        
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
        
        // Configuration Form
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
                'image' => '/modules/skebby/logo.png'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Skebby Account Username'),
                    'desc' => $this->l('The username to access Skebby services. You cannot use your email or phone number, only username is allowed on gateway.'),
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
                    'hint' => $this->l('Invalid characters:') . ' <>;=#{}',
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
                array(
                    'type' => 'text',
                    'label' => $this->l('Order Recipient'),
                    'desc' => $this->l('Recipient receiving SMS Order Notifications'),
                    'hint' => $this->l('Please refer to website docs for AGCOM specifications'),
                    'name' => 'SKEBBY_ORDER_RECIPIENT',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Order message template'),
                    'desc' => $this->l('Type the message template for orders. you can use the variables %currency% and %total_to_pay% that will be replaced in the message.'),
                    'name' => 'SKEBBY_ORDER_TEMPLATE',
                    'cols' => 40,
                    'rows' => 5,
                    'required' => true
                ),
                array(
                    'type' => 'free',
                    'label' => $this->l('Check the Credit'),
                    'desc' => $this->display(__FILE__, 'views/templates/admin/scripts.tpl'),
                    'name' => 'FREE_TEXT',
                    'required' => false
                )
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
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        
        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        
        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true; // false -> remove toolbar
        $helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules')
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );
        
        // Load current value
        $helper->fields_value['SKEBBY_USERNAME'] = Configuration::get('SKEBBY_USERNAME');
        $helper->fields_value['SKEBBY_PASSWORD'] = Configuration::get('SKEBBY_PASSWORD');
        $helper->fields_value['SKEBBY_DEFAULT_QUALITY'] = Configuration::get('SKEBBY_DEFAULT_QUALITY');
        $helper->fields_value['SKEBBY_DEFAULT_NUMBER'] = Configuration::get('SKEBBY_DEFAULT_NUMBER');
        $helper->fields_value['SKEBBY_DEFAULT_ALPHASENDER'] = Configuration::get('SKEBBY_DEFAULT_ALPHASENDER');
        $helper->fields_value['SKEBBY_ORDER_RECIPIENT'] = Configuration::get('SKEBBY_ORDER_RECIPIENT');
        $helper->fields_value['SKEBBY_ORDER_TEMPLATE'] = Configuration::get('SKEBBY_ORDER_TEMPLATE');
        $helper->fields_value['FREE_TEXT'] = Configuration::get('FREE_TEXT');
        
        $theform = '';
        
        $this->context->smarty->assign($data);
        
        $theform .= $this->display(__FILE__, 'views/templates/admin/intro.tpl');
        $theform .= $helper->generateForm($fields_form);
        // $data = array ();
        // $data ['token'] = Tools::encrypt ( Configuration::get ( 'PS_SHOP_NAME' ) );
        // $this->context->smarty->assign ( $data );
        // $theform .= $this->display ( __FILE__, 'views/templates/admin/scripts.tpl' );
        
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
        
        if (Tools::isSubmit('submit' . $this->name)) {
            $skebby_username = strval(Tools::getValue('SKEBBY_USERNAME'));
            if (! $skebby_username || empty($skebby_username) || ! Validate::isGenericName($skebby_username))
                $output .= $this->displayError($this->l('Invalid username'));
            else {
                Configuration::updateValue('SKEBBY_USERNAME', $skebby_username);
                $output .= $this->displayConfirmation($this->l('Username updated'));
            }
            
            // Password field
            
            $skebby_password = strval(Tools::getValue('SKEBBY_PASSWORD'));
            if (! $skebby_password || empty($skebby_password) || ! Validate::isGenericName($skebby_password))
                $output .= $this->displayError($this->l('Invalid password'));
            else {
                Configuration::updateValue('SKEBBY_PASSWORD', $skebby_password);
                $output .= $this->displayConfirmation($this->l('Password updated'));
            }
            
            // Mobile number field
            
            $skebby_mobile_number = strval(Tools::getValue('SKEBBY_DEFAULT_NUMBER'));
            $skebby_mobile_number = $this->normalizeNumber($skebby_mobile_number);
            
            if (! $skebby_mobile_number || empty($skebby_mobile_number) || ! Validate::isGenericName($skebby_mobile_number) || ! $this->isValidMobileNumber($skebby_mobile_number))
                $output .= $this->displayError($this->l('Invalid Sender Mobile Number'));
            else {
                
                Configuration::updateValue('SKEBBY_DEFAULT_NUMBER', $skebby_mobile_number);
                $output .= $this->displayConfirmation($this->l('Sender Number updated'));
            }
            
            // Default quality
            
            $skebby_default_quality = strval(Tools::getValue('SKEBBY_DEFAULT_QUALITY'));
            if (! $skebby_default_quality || empty($skebby_default_quality) || ! Validate::isGenericName($skebby_default_quality))
                $output .= $this->displayError($this->l('Invalid quality'));
            else {
                Configuration::updateValue('SKEBBY_DEFAULT_QUALITY', $skebby_default_quality);
                $output .= $this->displayConfirmation($this->l('SMS Quality updated'));
            }
            
            // Order Template
            
            $skebby_order_template = strval(Tools::getValue('SKEBBY_ORDER_TEMPLATE'));
            if (! $skebby_order_template || empty($skebby_order_template) || ! Validate::isGenericName($skebby_order_template))
                $output .= $this->displayError($this->l('Invalid order template'));
            else {
                Configuration::updateValue('SKEBBY_ORDER_TEMPLATE', $skebby_order_template);
                $output .= $this->displayConfirmation($this->l('Order Template updated'));
            }
            
            // Order Recipient
            
            $skebby_order_recipient = strval(Tools::getValue('SKEBBY_ORDER_RECIPIENT'));
            $skebby_order_recipient = $this->normalizeNumber($skebby_order_recipient);
            
            if (! $skebby_order_recipient || empty($skebby_order_recipient) || ! Validate::isGenericName($skebby_order_recipient) || ! $this->isValidMobileNumber($skebby_order_recipient))
                $output .= $this->displayError($this->l('Invalid Order Recipient'));
            else {
                Configuration::updateValue('SKEBBY_ORDER_RECIPIENT', $skebby_order_recipient);
                $output .= $this->displayConfirmation($this->l('Order Recipient Updated'));
            }
            
            $this->logMessage('Updated config Values');
            
            $this->dumpConfig();
        }
        
        return $output . $this->displayForm();
    }

    /**
     */
    private function dumpConfig()
    {
        $this->logMessage("SKEBBY_PASSWORD: " . Tools::getValue('SKEBBY_PASSWORD'));
        $this->logMessage("SKEBBY_USERNAME: " . Tools::getValue('SKEBBY_USERNAME'));
        $this->logMessage("SKEBBY_DEFAULT_QUALITY: " . Tools::getValue('SKEBBY_DEFAULT_QUALITY'));
        $this->logMessage("SKEBBY_DEFAULT_NUMBER: " . Tools::getValue('SKEBBY_DEFAULT_NUMBER'));
        $this->logMessage("SKEBBY_DEFAULT_ALPHASENDER: " . Tools::getValue('SKEBBY_DEFAULT_ALPHASENDER'));
        $this->logMessage("SKEBBY_ORDER_RECIPIENT: " . Tools::getValue('SKEBBY_ORDER_RECIPIENT'));
        $this->logMessage("SKEBBY_ORDER_TEMPLATE: " . Tools::getValue('SKEBBY_ORDER_TEMPLATE'));
    }

    /**
     *
     * @param unknown $mobile_number            
     * @return boolean number
     */
    private function isValidMobileNumber($mobile_number)
    {
        return preg_match("/^[0-9]{8,12}$/", $mobile_number);
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
        $this->logger->logDebug($message);
    }

    /**
     * Initialize the logger
     */
    private function initLogger()
    {
        $this->logger = new FileLogger(0);
        $this->logger->setFilename(_PS_ROOT_DIR_ . '/log/skebby.log');
    }
}