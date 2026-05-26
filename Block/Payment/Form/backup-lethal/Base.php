<?php

/**
 * Bold payment method block.
 */
class Bold_CheckoutPaymentBooster_Block_Payment_Form_Base extends Mage_Payment_Block_Form
{
    const PATH = '/checkout/storefront/';

    /**
     * Billing address.
     *
     * @var Mage_Sales_Model_Quote_Address|null
     */
    private $billingAddress = null;

    /**
     * @var Mage_Sales_Model_Quote|null
     */
    private $quote;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $this->quote = $checkoutSession->getQuote();
        $this->billingAddress = $this->quote->getBillingAddress();
        $this->setTemplate('bold/checkout_payment_booster/payment/form/base.phtml');
    }

    /**
     * Get customer quote ID.
     *
     * @return int
     */
    public function getQuoteId()
    {
        return $this->quote->getId();
    }

    /**
     * Check if Fastlane payment method is available.
     *
     * @return int
     */
    public function isFastlaneAvailable()
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Payment_Fastlane $fastlane */
        $fastlane = Mage::getModel('bold_checkout_payment_booster/payment_fastlane');
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $quote = $checkoutSession->getQuote();
        $isAvailable = $fastlane->isAvailable($quote);

        return (int)$isAvailable;
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
     * Get Bold Frontend API URL.
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
            $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        } catch (Mage_Core_Exception $e) {
            return null;
        }
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $apiUrl = $config->getApiUrl($websiteId);
        return $apiUrl . self::PATH . $shopId . '/' . $publicOrderId . '/';
    }

    /**
     * Get JWT token for the Bold frontend api calls.
     *
     * @return string|null
     */
    public function getJwtToken()
    {
        return Bold_CheckoutPaymentBooster_Service_Bold::getJwtToken();
    }

    /**
     * Get quote currency code for EPS SKD init.
     *
     * @return string
     */
    public function getQuoteCurrencyCode()
    {
        return $this->quote->getQuoteCurrencyCode();
    }

    /**
     * Get public order ID for EPS SDK init.
     *
     * @return string|null
     */
    public function getPublicOrderID()
    {
        return Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
    }

    /**
     * Get group label for EPS SDK init.
     *
     * @return string
     */
    public function getGroupLabel()
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        try {
            return $config->getShopDomain($this->quote->getStore()->getWebsiteId());
        } catch (Mage_Core_Exception $e) {
            return '';
        }
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
     * Get quote customer email.
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->billingAddress->getEmail() ?: '';
    }

    /**
     * Check if Bold payment method is available.
     *
     * @return int
     */
    public function isAvailable()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $quote = $checkoutSession->getQuote();
        return (int)(Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId() && !$quote->getIsMultiShipping());
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
        return $config->getEpsUrl($websiteId);
    }

    /**
     * Retrieve EPS static URL.
     *
     * @return string
     */
    public function getEpsStaticUrl()
    {
        $websiteId = $this->quote->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        return $config->getEpsStaticUrl($websiteId);
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
