<?php

/**
 * Bold checkout service.
 */
class Bold_CheckoutPaymentBooster_Service_Bold
{
    const SESSION_BOUND_QUOTE_ID = 'bold_checkout_bound_quote_id';

    const SESSION_BOUND_CUSTOMER_EMAIL = 'bold_checkout_bound_customer_email';

    /**
     * Init and load Bold Checkout Data to the checkout session.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @throws Mage_Core_Exception
     */
    public static function initBoldCheckoutData(
        Mage_Sales_Model_Quote $quote,
        $flowId = Bold_CheckoutPaymentBooster_Service_Flow::DEFAULT_FLOW_ID
    ) {
        if (!self::isAvailable()) {
            return;
        }

        if (self::shouldRotatePublicOrder($quote)) {
            self::clearBoldCheckoutData();
        }

        $publicOrderId = self::getPublicOrderId();
        if ($publicOrderId) {
            $orderData = Bold_CheckoutPaymentBooster_Service_Order_Resume::resumeOrder(
                $quote,
                $publicOrderId
            );
            if ($orderData) {
                $checkoutData = self::getBoldCheckoutData();
                $checkoutData->jwt_token = $orderData->jwt_token;
                self::persistCheckoutData($checkoutData, $quote);
                return;
            }

            self::clearBoldCheckoutData();
        }

        if (!self::acquireInitLock($quote->getId())) {
            if (self::getPublicOrderId()) {
                return;
            }
        }

        try {
            if (self::getPublicOrderId()) {
                return;
            }

            $checkoutData = Bold_CheckoutPaymentBooster_Service_Order_Init::init($quote, $flowId);
            self::persistCheckoutData($checkoutData, $quote);
        } finally {
            self::releaseInitLock($quote->getId());
        }
    }

    /**
     * Determine whether the checkout session Bold order no longer matches the quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return bool
     */
    public static function shouldRotatePublicOrder(Mage_Sales_Model_Quote $quote)
    {
        $publicOrderId = self::getPublicOrderId();
        if (!$publicOrderId) {
            return false;
        }

        if (Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::findOrderByPublicId($publicOrderId)) {
            return true;
        }

        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $boundQuoteId = $session->getData(self::SESSION_BOUND_QUOTE_ID);
        if ($boundQuoteId && (int)$boundQuoteId !== (int)$quote->getId()) {
            return true;
        }

        $boundEmail = trim((string)$session->getData(self::SESSION_BOUND_CUSTOMER_EMAIL));
        $currentEmail = trim((string)$quote->getCustomerEmail());

        if ($boundEmail !== '' && $currentEmail !== '' && strcasecmp($boundEmail, $currentEmail) !== 0) {
            return true;
        }

        if ($boundEmail === '' && $currentEmail !== '') {
            return true;
        }

        return false;
    }

    /**
     * Fail fast when Bold checkout session is stale relative to the active quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function assertPublicOrderMatchesQuote(Mage_Sales_Model_Quote $quote)
    {
        if (self::shouldRotatePublicOrder($quote)) {
            Mage::throwException(
                Mage::helper('checkout')->__('Your payment session expired. Please refresh the page and try again.')
            );
        }

        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $boundQuoteId = $session->getData(self::SESSION_BOUND_QUOTE_ID);
        if ($boundQuoteId && (int)$boundQuoteId !== (int)$quote->getId()) {
            Mage::throwException(
                Mage::helper('checkout')->__('Your payment session expired. Please refresh the page and try again.')
            );
        }
    }

    /**
     * Clear Bold checkout data in checkout session.
     */
    public static function clearBoldCheckoutData()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->setBoldCheckoutData(null);
        $checkoutSession->unsetData(self::SESSION_BOUND_QUOTE_ID);
        $checkoutSession->unsetData(self::SESSION_BOUND_CUSTOMER_EMAIL);
    }

    /**
     * @param stdClass $checkoutData
     * @param Mage_Sales_Model_Quote $quote
     * @return void
     */
    private static function persistCheckoutData(stdClass $checkoutData, Mage_Sales_Model_Quote $quote)
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->setBoldCheckoutData($checkoutData);
        $checkoutSession->setData(self::SESSION_BOUND_QUOTE_ID, (int)$quote->getId());
        $checkoutSession->setData(
            self::SESSION_BOUND_CUSTOMER_EMAIL,
            trim((string)$quote->getCustomerEmail())
        );
    }

    /**
     * @param int|string|null $quoteId
     * @return bool
     */
    private static function acquireInitLock($quoteId)
    {
        if (!$quoteId) {
            return true;
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $lockName = 'bold_checkout_init_' . md5((string)$quoteId);
        $result = $connection->fetchOne(
            'SELECT GET_LOCK(?, 5)',
            array($lockName)
        );

        return (int)$result === 1;
    }

    /**
     * @param int|string|null $quoteId
     * @return void
     */
    private static function releaseInitLock($quoteId)
    {
        if (!$quoteId) {
            return;
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $lockName = 'bold_checkout_init_' . md5((string)$quoteId);
        $connection->query('SELECT RELEASE_LOCK(?)', array($lockName));
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

    /**
     * Export Bold checkout session values for frontend synchronization.
     *
     * @return array
     */
    public static function exportCheckoutSessionForFrontend()
    {
        $checkoutData = self::getBoldCheckoutData();

        return array(
            'public_order_id' => self::getPublicOrderId(),
            'jwt_token' => self::getJwtToken(),
            'eps_auth_token' => self::getEpsAuthToken(),
            'eps_gateway_id' => ($checkoutData && isset($checkoutData->flow_settings->eps_gateway_id))
                ? $checkoutData->flow_settings->eps_gateway_id
                : null,
        );
    }
}
