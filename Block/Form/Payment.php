<?php

/**
 * Bold payment method block.
 */
class Bold_CheckoutPaymentBooster_Block_Form_Payment extends Mage_Payment_Block_Form
{
    /**
     * @var array
     */
    private $countries = [];

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();
        if ($this->getAction() instanceof TM_FireCheckout_IndexController) {
            $this->setTemplate('bold/firecheckout/payment/form/bold_method.phtml');
            return;
        }
        $this->setTemplate('bold/checkout_payment_booster/payment/form/bold_method.phtml');
    }

    /**
     * Retrieve customer data.
     *
     * @return string
     */
    public function getCustomerData()
    {
        $customerData = [
            'id' => null,
            'email' => '',
        ];
        $customer = Mage::getSingleton('checkout/session')->getQuote()->getCustomer();
        if ($customer->getId()) {
            $customerData['id'] = $customer->getId();
            $customerData['email'] = $customer->getEmail();
        }

        return json_encode($customerData);
    }

    /**
     * Get allowed countries.
     *
     * @return string
     * @throws Mage_Core_Exception
     */
    public function getAllowedCountries()
    {
        if (!empty($this->countries)) {
            return json_encode($this->countries);
        }

        $storeId = Mage::getSingleton('checkout/session')->getQuote()->getStoreId();
        /** @var Mage_Directory_Model_Resource_Country_Collection $countriesCollection */
        $countriesCollection = Mage::getModel('directory/country')->getCollection();
        $countriesCollection->loadByStore($storeId);
        foreach ($countriesCollection as $country) {
            $locale = Mage::getModel('core/locale', Mage_Core_Model_Locale::DEFAULT_LOCALE);
            $this->countries[] = [
                'value' => $country->getCountryId(),
                'label' => $locale->getTranslation($country->getCountryId(), 'country'),
            ];
        }

        return json_encode($this->countries);
    }
}
