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
     * @throws Mage_Core_Exception
     */
    public static function initBoldCheckoutData(Mage_Sales_Model_Quote $quote)
    {
        self::clearBoldCheckoutData();
        if (!self::isAvailable()) {
            return;
        }
        $checkoutData = Bold_CheckoutPaymentBooster_Service_Order_Init::init($quote);
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
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

    /**
     * Retrieve saved eps auth token from flow settings.
     *
     * @return null|string
     */
    public static function getEpsAuthToken()
    {
        $checkoutData = self::getBoldCheckoutData();
        if (!$checkoutData) {
            return null;
        }
        return isset($checkoutData->flow_settings->eps_auth_token) ? $checkoutData->flow_settings->eps_auth_token : null;
    }

    /**
     * Retrieve fastlane styles from flow settings.
     *
     * @return null|string
     */
    public static function getFastlaneStyles()
    {
        $checkoutData = self::getBoldCheckoutData();
        if (!$checkoutData) {
            return null;
        }
        return isset($checkoutData->flow_settings->fastlane_styles)
            ? $checkoutData->flow_settings->fastlane_styles
            : null;
    }

    /**
     * Retrieve saved jwt token for Bold storefront api.
     *
     * @return null|string
     */
    public static function getJwtToken()
    {
        $checkoutData = self::getBoldCheckoutData();
        if (!$checkoutData) {
            return null;
        }
        return isset($checkoutData->jwt_token) ? $checkoutData->jwt_token : null;
    }
}
