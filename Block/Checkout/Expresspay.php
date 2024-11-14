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

        switch ($this->getAction()->getFullActionName()) {
            case 'checkout_cart_index':
                return $config->isExpressPayEnabledInCart($websiteId);
            case 'checkout_onepage_index':
                return $config->isExpressPayEnabled($websiteId);
            default:
                return false;
        }
    }

    public function isCheckoutActive()
    {
        return $this->getAction()->getFullActionName() === 'checkout_onepage_index';
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
     * @return string[][]
     */
    public function getQuoteTotals()
    {
        $quote = $this->getQuote();

        if ($quote === null) {
            return [];
        }

        $totals = array_map(
            static function (Mage_Sales_Model_Quote_Address_Total $total) {
                return [
                    'code' => $total->getCode(),
                    'value' => number_format((float)$total->getValue(), 2, '.', '')
                ];
            },
            $quote->getTotals()
        );

        return $totals;
    }

    /**
     * @return string[][]
     */
    public function getQuoteItems()
    {
        $quote = $this->getQuote();

        if ($quote === null) {
            return [];
        }

        $quoteItems = array_map(
            static function (Mage_Sales_Model_Quote_Item $quoteItem) {
                return [
                    'sku' => $quoteItem->getSku(),
                    'price' => number_format((float)$quoteItem->getPrice(), 2, '.', ''),
                    'name' => $quoteItem->getName()
                ];
            },
            $quote->getAllVisibleItems()
        );

        return $quoteItems;
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

    /**
     * @return string[]
     */
    public function getAllowedCountries()
    {
        return explode(',', (string)Mage::getStoreConfig('general/country/allow')) ?: [];
    }

    /**
     * @return bool
     */
    public function isFastlaneEnabled()
    {
        return Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE)
            ->isFastlaneEnabled(Mage::app()->getStore()->getWebsiteId());
    }
}
