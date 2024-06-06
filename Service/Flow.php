<?php

/**
 * Flow identifier retrieve service.
 */
class Bold_CheckoutPaymentBooster_Service_Flow
{
    /**
     * Get Bold flow ID.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     * @throws Mage_Core_Exception
     */
    public static function get(Mage_Sales_Model_Quote $quote)
    {
        $websiteId = $quote->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        if ($config->isFastlaneEnabled($websiteId)
            && !$quote->getCustomer()->getId()
        ) {
            return 'Payment-Booster-Fastlane-M1';  //todo: check if api should be used instead.
        }

        return 'Payment-Booster-M1'; //todo: check if api should be used instead.
    }
}
