<?php

/**
 * Bold payment method block.
 */
class Bold_CheckoutPaymentBooster_Block_Payment_Form_Fastlane extends Mage_Payment_Block_Form
{
    const PATH = '/checkout/storefront/';

    /**
     * @var Mage_Sales_Model_Quote|null
     */
    private $quote = null;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->quote = Mage::getSingleton('checkout/session')->getQuote();
        if ($this->isAvailable()) {
            $this->setTemplate('bold/checkout_payment_booster/payment/form/bold_fastlane_method.phtml');
        }
    }

    /**
     * Retrieve Fastlane email container styles.
     *
     * @return string
     */
    public function getWatermarkContainerStyle()
    {
        $websiteId = $this->quote->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        return $config->getFastlaneWatermarkContainerStyles($websiteId);
    }

    /**
     * Check if fastlane payment method is available.
     *
     * @return int
     */
    public function isAvailable()
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Payment_Fastlane $fastlane */
        $fastlane = Mage::getModel('bold_checkout_payment_booster/payment_fastlane');
        return (int)$fastlane->isAvailable($this->quote);
    }

    /**
     * Get public order ID.
     *
     * @return string
     */
    public function getPublicOrderId()
    {
        return Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
    }

    /**
     * Get address container style.
     *
     * @return string
     */
    public function getAddressContainerStyle()
    {
        $websiteId = $this->quote->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        return $config->getFastlaneAddressContainerStyles($websiteId);
    }

    /**
     * Get EPS gateway ID from Bold checkout data.
     *
     * @return string|null
     */
    public function getEpsGatewayId()
    {
        $boldCheckoutData = Bold_CheckoutPaymentBooster_Service_Bold::getBoldCheckoutData();
        if (!$boldCheckoutData) {
            return null;
        }
        return isset($boldCheckoutData->flow_settings->eps_gateway_id)
            ? $boldCheckoutData->flow_settings->eps_gateway_id
            : null;
    }

    /**
     * Retrieve Fastlane styles.
     *
     * @return string|null
     */
    public function getFastlaneStyles()
    {
        $fastlaneStyles = Bold_CheckoutPaymentBooster_Service_Bold::getFastlaneStyles();
        return $fastlaneStyles ? json_encode($fastlaneStyles) : null;
    }

    /**
     * Retrieve EPS URL.
     *
     * @return string
     */
    public function getEpsUrl()
    {
        $websiteId = $this->quote->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        return $config->getEpsUrl($websiteId) . '/' . $config->getShopDomain($websiteId) . '/';
    }

    /**
     * Retrieve EPS auth token.
     *
     * @return string|null
     */
    public function getEpsAuthToken()
    {
        return Bold_CheckoutPaymentBooster_Service_Bold::getEpsAuthToken();
    }
}
