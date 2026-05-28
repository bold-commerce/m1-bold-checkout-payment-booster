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

class Zend_Log
{
    const WARN = 4;
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
}

class Bold_CheckoutPaymentBooster_Test_Stub_Mage
{
    public static $singletons = array();

    public static $models = array();

    public static $quotesById = array();

    public static $publicOrderId = null;

    public static $logMessages = array();

    public static function reset()
    {
        self::$singletons = array();
        self::$models = array();
        self::$quotesById = array();
        self::$publicOrderId = null;
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
}
