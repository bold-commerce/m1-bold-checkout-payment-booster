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
                    'value' => number_format((float)$total->getValue(), 2)
                ];
            },
            $quote->getTotals()
        );
        $totals['discount'] = [
            'code' => 'discount',
            'value' => number_format((float)($quote->getSubtotal() - $quote->getSubtotalWithDiscount()), 2)
        ];

        return $totals;
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
