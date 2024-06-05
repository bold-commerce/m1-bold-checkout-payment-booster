<?php

/**
 * Bold payment method block.
 */
class Bold_CheckoutPaymentBooster_Block_Form_Payment extends Mage_Payment_Block_Form
{
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
}
