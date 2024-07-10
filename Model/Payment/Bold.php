<?php

/**
 * Bold payment method model.
 */
class Bold_CheckoutPaymentBooster_Model_Payment_Bold extends Mage_Payment_Model_Method_Abstract
{
    const CODE = 'bold';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'bold_checkout_payment_booster/payment_form_bold';

    /**
     * @inheritDoc
     */
    public function isAvailable($quote = null)
    {
        if ($quote && $quote->getIsMultiShipping()) {
            return false;
        }
        return Mage::getSingleton('checkout/session')->getBoldCheckoutData() !== null && $this->isEnabled($quote);
    }

    /**
     * Check if payment method is enabled.
     *
     * @param $quote
     * @return bool
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function isEnabled($quote = null)
    {
        $websiteId = $quote ? $quote->getStore()->getWebsiteId() : Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->isPaymentBoosterEnabled($websiteId);
    }
}
