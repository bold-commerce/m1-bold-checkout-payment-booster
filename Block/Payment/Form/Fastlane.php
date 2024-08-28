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
        if ($this->isAvailable()) {
            $this->quote = Mage::getSingleton('checkout/session')->getQuote();
            $this->setTemplate('bold/checkout_payment_booster/payment/form/bold_fastlane_method.phtml');
        }
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
     * Get payment gateway data.
     *
     * @return string
     */
    public function getGatewayData()
    {
        return json_encode(
            Bold_CheckoutPaymentBooster_Service_Fastlane::getGatewayData()
        );
    }

    /**
     * Retrieve Fastlane styles.
     *
     * @return string
     */
    public function getFastlaneStyles()
    {
        $boldCheckoutData = Bold_CheckoutPaymentBooster_Service_Bold::getBoldCheckoutData();
        $styles = (object)[];
        if (!$boldCheckoutData) {
            return json_encode($styles);
        }

        // TODO: Need to implement styles retrieving from Checkout admin
        // (for now there is no ability to get this information if order was created using checkout_sidekick)

        return json_encode($styles);
    }

    /**
     * Retrieve Bold Storefront API URL.
     *
     * @return string|null
     */
    public function getBoldApiUrl()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
        if (!$publicOrderId) {
            return null;
        }
        $websiteId = $checkoutSession->getQuote()->getStore()->getWebsiteId();
        try {
            $shopId = Bold_CheckoutPaymentBooster_Service_ShopId::get($websiteId);
        } catch (Mage_Core_Exception $e) {
            return null;
        }
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $apiUrl = $config->getApiUrl($websiteId);
        return $apiUrl . self::PATH . $shopId . '/' . $publicOrderId . '/';
    }

    /**
     * Retrieve JWT token.
     *
     * @return string|null
     */
    public function getJwtToken()
    {
        return Bold_CheckoutPaymentBooster_Service_Bold::getJwtToken();
    }
}
