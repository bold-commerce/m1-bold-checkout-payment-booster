<?php

/**
 * Bold payment method block.
 */
class Bold_CheckoutPaymentBooster_Block_Form_Payment extends Mage_Payment_Block_Form
{
    const CHECKOUT_STOREFRONT_API_PATH = '/checkout/storefront/';

    /**
     * Billing address.
     *
     * @var Mage_Sales_Model_Quote_Address|null
     */
    private $billingAddress = null;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->billingAddress = Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress();
        $this->setTemplate('bold/checkout_payment_booster/payment/form/bold_method.phtml');
    }

    /**
     * Get quote address ID.
     *
     * @return string
     */
    public function getAddressId()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if (!$quote->getCustomer()->getId()) {
            return '';
        }
        return (string)$this->billingAddress->getId() ?: '';
    }

    /**
     * Get quote customer firstname.
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->billingAddress->getFirstname() ?: '';
    }

    /**
     * Get quote customer lastname.
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->billingAddress->getLastname() ?: '';
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
     * Get quote address company.
     *
     * @return string
     */
    public function getCompany()
    {
        return $this->billingAddress->getCompany() ?: '';
    }

    /**
     * Get quote address street 1.
     *
     * @return string
     */
    public function getStreet1()
    {
        return $this->billingAddress->getStreet1() ?: '';
    }

    /**
     * Get quote address street 2.
     *
     * @return string
     */
    public function getStreet2()
    {
        return $this->billingAddress->getStreet2() ?: '';
    }

    /**
     * Get quote address city.
     *
     * @return string
     */
    public function getCity()
    {
        return $this->billingAddress->getCity() ?: '';
    }

    /**
     * Get quote address country ID.
     *
     * @return string
     */
    public function getCountryId()
    {
        return $this->billingAddress->getCountryId() ?: '';
    }

    /**
     * Get quote address postcode.
     *
     * @return string
     */
    public function getPostcode()
    {
        return $this->billingAddress->getPostcode() ?: '';
    }

    /**
     * Get quote address region ID.
     *
     * @return string
     */
    public function getRegionId()
    {
        return (string)$this->billingAddress->getRegionId() ?: '';
    }

    /**
     * Get quote address phone number.
     *
     * @return string
     */
    public function getTelephone()
    {
        return $this->billingAddress->getTelephone() ?: '';
    }

    /**
     * Get Bold payments iframe URL.
     *
     * @return string|null
     */
    public function getIframeUrl()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $boldCheckoutData = $checkoutSession->getBoldCheckoutData();

        if (!$boldCheckoutData) {
            return null;
        }

        $websiteId = $checkoutSession->getQuote()->getStore()->getWebsiteId();
        try {
            $shopId = Bold_CheckoutPaymentBooster_Service_ShopId::get($websiteId);
        } catch (Mage_Core_Exception $e) {
            return null;
        }

        $publicOrderId = $boldCheckoutData->public_order_id;
        $jwtToken = $boldCheckoutData->jwt_token;
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $apiUrl = $config->getApiUrl($websiteId);

        return $apiUrl . self::CHECKOUT_STOREFRONT_API_PATH . $shopId . '/'
            . $publicOrderId . '/payments/iframe?token=' . $jwtToken;
    }
}
