<?php

/**
 * Bold fastlane payment method model.
 */
class Bold_CheckoutPaymentBooster_Model_Payment_Fastlane extends Bold_CheckoutPaymentBooster_Model_Payment_Bold
{
    const CODE = 'bold_fastlane';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'bold_checkout_payment_booster/payment_form_fastlane';

    /**
     * @inheritDoc
     */
    public function isEnabled($quote = null)
    {
        /** @var Mage_Sales_Model_Quote|null $quote */
        $websiteId = $quote ? $quote->getStore()->getWebsiteId() : Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->isFastlaneEnabled($websiteId) && !Mage::getSingleton('customer/session')->isLoggedIn();
    }
}
