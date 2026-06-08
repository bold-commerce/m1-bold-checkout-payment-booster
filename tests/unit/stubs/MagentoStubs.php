<?php

class Bold_CheckoutPaymentBooster_Test_Stub_Mage
{
    private static $singletons = array();
    private static $registry = array();
    private static $websites = array();
    private static $logMessages = array();

    public static function reset()
    {
        self::$singletons = array();
        self::$registry = array();
        self::$websites = array();
        self::$logMessages = array();
    }

    public static function setSingleton($key, $object)
    {
        self::$singletons[$key] = $object;
    }

    public static function getSingleton($key)
    {
        return isset(self::$singletons[$key]) ? self::$singletons[$key] : null;
    }

    public static function register($key, $value)
    {
        self::$registry[$key] = $value;
    }

    public static function registry($key)
    {
        return isset(self::$registry[$key]) ? self::$registry[$key] : null;
    }

    public static function setWebsite($websiteId, $defaultStore)
    {
        self::$websites[$websiteId] = $defaultStore;
    }

    public static function app()
    {
        return new Bold_CheckoutPaymentBooster_Test_Stub_Mage_App();
    }

    public static function log($message)
    {
        self::$logMessages[] = $message;
    }

    public static function getLogMessages()
    {
        return self::$logMessages;
    }

    public static function throwException($message)
    {
        throw new Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Exception($message);
    }
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Exception extends Exception
{
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_App
{
    public function getWebsite($websiteId)
    {
        return new Bold_CheckoutPaymentBooster_Test_Stub_Mage_Website($websiteId);
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

class Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Model_Store
{
    const URL_TYPE_WEB = 'web';

    private $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function getBaseUrl($type)
    {
        return $this->baseUrl;
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

    public function getShopDomain($websiteId)
    {
        return isset($this->values['shop_domain']) ? $this->values['shop_domain'] : '';
    }
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

if (!class_exists('Mage', false)) {
    class Mage
    {
        public static function getSingleton($key)
        {
            return Bold_CheckoutPaymentBooster_Test_Stub_Mage::getSingleton($key);
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

        public static function log($message)
        {
            Bold_CheckoutPaymentBooster_Test_Stub_Mage::log($message);
        }

        public static function throwException($message)
        {
            Bold_CheckoutPaymentBooster_Test_Stub_Mage::throwException($message);
        }
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
        const DEBUG = 7;
        const WARN = 4;
    }
}
