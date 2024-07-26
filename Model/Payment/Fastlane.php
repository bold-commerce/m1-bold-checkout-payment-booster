<?php

/**
 * Bold fastlane payment method model.
 */
class Bold_CheckoutPaymentBooster_Model_Payment_Fastlane extends Mage_Payment_Model_Method_Abstract
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
        return $config->isFastlaneEnabled($websiteId);
    }

    /**
     * @inheritDoc
     */
    public function isAvailable($quote = null)
    {
        return Bold_CheckoutPaymentBooster_Service_Fastlane::getGatewayData() !== null;
    }
}
