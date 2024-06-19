<?php

/**
 * Bold payment method block.
 */
class Bold_CheckoutPaymentBooster_Block_Payment_Form_Bold extends Mage_Payment_Block_Form
{
    const PATH = '/checkout/storefront/';

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
     * Get quote address id.
     *
     * @return string
     */
    public function getAddressId()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if (!$quote->getCustomer()->getId()) {
            return '';
        }
        return $this->billingAddress->getId() ?: '';
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
     * Get quote address city.
     *
     * @return string
     */
    public function getCity()
    {
        return $this->billingAddress->getCity() ?: '';
    }

    /**
     * Get quote address country id.
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
     * Get quote address region id.
     *
     * @return string
     */
    public function getRegionId()
    {
        return $this->billingAddress->getRegionId() ?: '';
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
     * Get quote address street.
     *
     * @return string
     */
    public function getTelephone()
    {
        return $this->billingAddress->getTelephone() ?: '';
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
     * Get quote customer email.
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->billingAddress->getEmail() ?: '';
    }

    /**
     * Get quote customer first name.
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->billingAddress->getFirstname() ?: '';
    }

    /**
     * Get quote customer last name.
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->billingAddress->getLastname() ?: '';
    }

    /**
     * Get payment iframe url.
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
        $orderId = $boldCheckoutData->public_order_id;
        $jwtToken = $boldCheckoutData->jwt_token;
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $apiUrl = $config->getApiUrl($websiteId);
        return $apiUrl . self::PATH . $shopId . '/' . $orderId . '/payments/iframe?token=' . $jwtToken;
    }
}
