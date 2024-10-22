<?php

class Bold_CheckoutPaymentBooster_Block_Checkout_Expresspay extends Mage_Core_Block_Template
{
    /**
     * @return bool
     */
    public function isEnabled()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->isExpressPayEnabled($websiteId);
    }

    /**
     * @return string
     */
    public function getEpsApiUrl()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->getEpsUrl($websiteId) ?: '';
    }

    /**
     * @return string
     */
    public function getEpsStaticApiUrl()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->getEpsStaticUrl($websiteId) ?: '';
    }

    /**
     * @return string
     */
    public function getShopDomain()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->getShopDomain($websiteId) ?: '';
    }

    /**
     * @return Mage_Sales_Model_Quote|null
     */
    public function getQuote()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');

        return $checkoutSession->getQuote();
    }

    /**
     * @return stdClass|null
     */
    public function getBoldCheckoutData()
    {
        return Bold_CheckoutPaymentBooster_Service_Bold::getBoldCheckoutData();
    }

    /**
     * @return string
     */
    public function getFormKey()
    {
        return Mage::getSingleton('core/session')->getFormKey();
    }

    /**
     * @return string
     */
    public function getRegionsAsJson()
    {
        return Mage::helper('directory')->getRegionJsonByStore($this->getQuote()->getStoreId());
    }
}
