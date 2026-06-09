<?php

class Varien_Object
{
    protected $_data = array();

    public function __construct($data = array())
    {
        $this->_data = $data;
    }

    public function getId()
    {
        return isset($this->_data['entity_id']) ? $this->_data['entity_id'] : null;
    }

    public function getData($key = '', $index = null)
    {
        if ($key === '') {
            return $this->_data;
        }

        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            $this->_data = array_merge($this->_data, $key);
        } else {
            $this->_data[$key] = $value;
        }

        return $this;
    }

    public function unsetData($key = null)
    {
        if ($key === null) {
            $this->_data = array();
        } else {
            unset($this->_data[$key]);
        }

        return $this;
    }

    public function getQuoteId()
    {
        return $this->getData('quote_id');
    }

    public function getCustomerId()
    {
        return $this->getData('customer_id');
    }

    public function getCustomerEmail()
    {
        return $this->getData('customer_email');
    }

    public function getIsActive()
    {
        return $this->getData('is_active');
    }

    public function getIncrementId()
    {
        return $this->getData('increment_id');
    }

    public function getBillingAddress()
    {
        return $this->getData('billing_address');
    }

    public function getQuote()
    {
        return $this->getData('quote');
    }

    public function getPayment()
    {
        return $this->getData('payment');
    }

    public function getStore()
    {
        return $this->getData('store');
    }
}

class Mage_Sales_Model_Order extends Varien_Object
{
    public function getCustomerEmail()
    {
        $email = parent::getCustomerEmail();
        if ($email) {
            return $email;
        }

        return $this->getData('customer_email_direct');
    }
}

class Mage_Sales_Model_Quote extends Varien_Object
{
    public function load($id)
    {
        $quote = Bold_CheckoutPaymentBooster_Test_Stub_Mage::getQuoteById($id);
        if ($quote) {
            $this->_data = $quote->getData();
        }

        return $this;
    }
}

class Mage_Checkout_Model_Session extends Varien_Object
{
    public function getQuote()
    {
        return $this->getData('quote');
    }

    public function getQuoteId()
    {
        return $this->getData('quote_id');
    }
}

class Mage_Core_Helper_Abstract
{
    public function __call($name, $arguments)
    {
        if ($name === '__') {
            return $arguments[0];
        }

        return null;
    }
}

class Mage_Core_Exception extends Exception
{
}

class Bold_CheckoutPaymentBooster_Model_Config
{
    const RESOURCE = 'bold_checkout_payment_booster/config';
    const LOG_FILE_NAME = 'bold_checkout_payment_booster.log';

    public function isLogEnabled($websiteId)
    {
        return true;
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Model_Store
{
    const URL_TYPE_WEB = 'web';
    const URL_TYPE_LINK = 'link';

    private $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function getBaseUrl($type, $secure = null)
    {
        return $this->baseUrl;
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_Website
{
    private $websiteId;

    public function __construct($websiteId)
    {
        $this->websiteId = $websiteId;
    }

    public function getId()
    {
        return $this->websiteId;
    }

    public function getDefaultStore()
    {
        $store = Bold_CheckoutPaymentBooster_Test_Stub_Mage::getSingleton(
            'website_default_store_' . $this->websiteId
        );
        if (!$store) {
            throw new RuntimeException('Default store not configured for website ' . $this->websiteId);
        }

        return $store;
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_App
{
    public function getWebsite($websiteId)
    {
        return new Bold_CheckoutPaymentBooster_Test_Stub_Mage_Website($websiteId);
    }

    public function getStore()
    {
        return new Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Model_Store_Website('https://example.com/');
    }

    public function getRequest()
    {
        return new Bold_CheckoutPaymentBooster_Test_Stub_Mage_Request();
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Model_Store_Website extends Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Model_Store
{
    public function getWebsiteId()
    {
        return 1;
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_Request
{
    public function getRequestUri()
    {
        return '/checkout/onepage/saveOrder';
    }

    public function getParam($key)
    {
        return null;
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Config
{
    private $values = array();

    public function setValue($path, $value)
    {
        $this->values[$path] = $value;
    }

    public function getShopId($websiteId)
    {
        return isset($this->values['shop_id']) ? $this->values['shop_id'] : null;
    }

    public function getSharedSecret($websiteId)
    {
        return isset($this->values['shared_secret']) ? $this->values['shared_secret'] : null;
    }

    public function getApiTokenFingerprint($websiteId)
    {
        return isset($this->values['api_token_fingerprint']) ? $this->values['api_token_fingerprint'] : null;
    }

    public function buildApiTokenFingerprint($websiteId)
    {
        if (empty($this->values['api_token'])) {
            return null;
        }

        return hash('sha256', $this->values['api_token']);
    }

    public function setSharedSecret($secret, $websiteId)
    {
        $this->values['shared_secret'] = $secret;
    }

    public function getShopDomain($websiteId)
    {
        return isset($this->values['shop_domain']) ? $this->values['shop_domain'] : '';
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_BoldClient
{
    /** @var array<string, stdClass|null> Map of 'METHOD:url' → response object */
    public static $responses = array();

    public static function reset()
    {
        self::$responses = array();
    }

    public static function setResponse($method, $url, $response)
    {
        self::$responses[strtoupper($method) . ':' . $url] = $response;
    }

    public static function patch($url, $websiteId, $body)
    {
        return self::getResponse('PATCH', $url);
    }

    public static function post($url, $websiteId, $body)
    {
        return self::getResponse('POST', $url);
    }

    public static function get($url, $websiteId)
    {
        return self::getResponse('GET', $url);
    }

    private static function getResponse($method, $url)
    {
        $key = $method . ':' . $url;
        if (array_key_exists($key, self::$responses)) {
            return self::$responses[$key];
        }

        if (strpos($url, 'checkShared') !== false) {
            return (object)array('data' => 1);
        }

        return (object)array('data' => (object)array('ok' => true));
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_Config
{
    public function deleteConfig($path, $scope, $scopeId) {}
    public function cleanCache() {}
}

if (!class_exists('Bold_CheckoutPaymentBooster_Service_BoldClient', false)) {
    class Bold_CheckoutPaymentBooster_Service_BoldClient extends Bold_CheckoutPaymentBooster_Test_Stub_BoldClient {}
}

class Bold_CheckoutPaymentBooster_Test_Stub_Request
{
    private $headers = array();

    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    public function getHeader($name)
    {
        return isset($this->headers[$name]) ? $this->headers[$name] : false;
    }
}

class Bold_CheckoutPaymentBooster_Model_Payment_Bold
{
    const CODE = 'bold';
}

class Bold_CheckoutPaymentBooster_Model_Payment_Fastlane
{
    const CODE = 'bold_fastlane';
}

class Bold_CheckoutPaymentBooster_Service_Bold
{
    public static function getPublicOrderId()
    {
        return Bold_CheckoutPaymentBooster_Test_Stub_Mage::$publicOrderId;
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Resource
{
    public function getConnection($name)
    {
        return new Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Resource_Connection();
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Resource_Connection
{
    public function fetchOne($sql, $bind = array())
    {
        if (stripos($sql, 'GET_LOCK') !== false) {
            return '1';
        }

        return null;
    }

    public function query($sql, $bind = array())
    {
        return null;
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage
{
    public static $singletons = array();

    public static $models = array();

    public static $quotesById = array();

    public static $publicOrderId = null;

    public static $logMessages = array();

    private static $registry = array();

    public static function reset()
    {
        self::$singletons = array();
        self::$models = array();
        self::$quotesById = array();
        self::$publicOrderId = null;
        self::$logMessages = array();
        self::$registry = array();
    }

    public static function setSingleton($key, $object)
    {
        self::$singletons[$key] = $object;
    }

    public static function getSingleton($key)
    {
        return isset(self::$singletons[$key]) ? self::$singletons[$key] : null;
    }

    public static function setModel($name, $object)
    {
        self::$models[$name] = $object;
    }

    public static function getModel($name)
    {
        if (isset(self::$models[$name])) {
            return self::$models[$name];
        }

        if ($name === 'sales/quote') {
            return new Mage_Sales_Model_Quote();
        }

        return new Varien_Object();
    }

    public static function setQuoteById($id, Mage_Sales_Model_Quote $quote)
    {
        self::$quotesById[$id] = $quote;
    }

    public static function getQuoteById($id)
    {
        return isset(self::$quotesById[$id]) ? self::$quotesById[$id] : null;
    }

    public static function register($key, $value)
    {
        self::$registry[$key] = $value;
    }

    public static function registry($key)
    {
        return isset(self::$registry[$key]) ? self::$registry[$key] : null;
    }

    public static function unregister($key)
    {
        unset(self::$registry[$key]);
    }

    public static function app()
    {
        return new Bold_CheckoutPaymentBooster_Test_Stub_Mage_App();
    }

    public static function throwException($message)
    {
        throw new Mage_Core_Exception($message);
    }
}

if (!class_exists('Mage', false)) {
    class Mage
    {
        public static function helper($name)
        {
            return new Mage_Core_Helper_Abstract();
        }

        public static function getSingleton($key)
        {
            return Bold_CheckoutPaymentBooster_Test_Stub_Mage::getSingleton($key);
        }

        public static function getModel($name)
        {
            return Bold_CheckoutPaymentBooster_Test_Stub_Mage::getModel($name);
        }

        public static function log($message, $level = null, $file = null, $forceLog = false)
        {
            Bold_CheckoutPaymentBooster_Test_Stub_Mage::$logMessages[] = array(
                'message' => $message,
                'file' => $file,
            );
        }

        public static function app()
        {
            return Bold_CheckoutPaymentBooster_Test_Stub_Mage::app();
        }

        public static function register($key, $value, $graceful = false)
        {
            Bold_CheckoutPaymentBooster_Test_Stub_Mage::register($key, $value);
        }

        public static function registry($key)
        {
            return Bold_CheckoutPaymentBooster_Test_Stub_Mage::registry($key);
        }

        public static function unregister($key)
        {
            Bold_CheckoutPaymentBooster_Test_Stub_Mage::unregister($key);
        }

        public static function throwException($message)
        {
            Bold_CheckoutPaymentBooster_Test_Stub_Mage::throwException($message);
        }

        public static function getConfig()
        {
            return new Bold_CheckoutPaymentBooster_Test_Stub_Mage_Config();
        }

        public static function logException($exception) {}
    }
}

if (!class_exists('Mage_Core_Model_Store', false)) {
    class Mage_Core_Model_Store extends Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Model_Store
    {
    }
}

if (!class_exists('Zend_Controller_Request_Http', false)) {
    class Zend_Controller_Request_Http extends Bold_CheckoutPaymentBooster_Test_Stub_Request
    {
    }
}

if (!class_exists('Zend_Log', false)) {
    class Zend_Log
    {
        const EMERG  = 0;
        const ALERT  = 1;
        const CRIT   = 2;
        const ERR    = 3;
        const WARN   = 4;
        const NOTICE = 5;
        const INFO   = 6;
        const DEBUG  = 7;
    }
}
