<?php

/**
 * Bold checkout service.
 */
class Bold_CheckoutPaymentBooster_Service_Bold
{
    /**
     * Init and load Bold Checkout Data to the checkout session.
     *
     * @param Mage_Sales_Model_Quote $quote
     */
    public static function initBoldCheckoutData(Mage_Sales_Model_Quote $quote)
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->setBoldCheckoutData(null);
        if (!self::isAvailable()) {
            return;
        }
        $flowId = Bold_CheckoutPaymentBooster_Service_Flow::getId($quote);
        $checkoutData = Bold_CheckoutPaymentBooster_Service_Order_Init::init($quote, $flowId);
        $checkoutSession->setBoldCheckoutData($checkoutData);
    }

    /**
     * Clear Bold checkout data in checkout session.
     */
    public static function clearBoldCheckoutData()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->setBoldCheckoutData(null);
    }

    /**
     * Get Bold checkout data.
     *
     * @return stdClass|null
     */
    public static function getBoldCheckoutData()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        return $checkoutSession->getBoldCheckoutData();
    }

    /**
     * Get JWT token.
     *
     * @return string|null
     */
    public static function getJwtToken()
    {
        $checkoutData = self::getBoldCheckoutData();
        return $checkoutData ? $checkoutData->jwt_token : null;
    }

    /**
     * Get public order id.
     *
     * @return string|null
     */
    public static function getPublicOrderId()
    {
        $checkoutData = self::getBoldCheckoutData();
        return $checkoutData ? $checkoutData->public_order_id : null;
    }

    /**
     * Check if Bold payment methods are available.
     *
     * @return bool
     */
    private static function isAvailable()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $isEnabled = $config->isPaymentBoosterEnabled($websiteId);
        if (!$isEnabled) {
            return false;
        }
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        return $quote && !$quote->getIsMultiShipping();
    }
}
