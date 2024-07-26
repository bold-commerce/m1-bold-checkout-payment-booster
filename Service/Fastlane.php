<?php

/**
 * PayPal Fastlane service.
 */
class Bold_CheckoutPaymentBooster_Service_Fastlane
{
    const PAYPAL_FASTLANE_CLIENT_TOKEN_URL = 'checkout/orders/{{shopId}}/%s/paypal_fastlane/client_token';

    public static function loadGatewayData($publicOrderId, $websiteId)
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->setBotFastlaneGatewayData(null);
        if (!self::isAvailable()) {
            return;
        }
        $apiUrl = sprintf(self::PAYPAL_FASTLANE_CLIENT_TOKEN_URL, $publicOrderId);
        $baseUrl = Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $domain = preg_replace('#^https?://|/$#', '', $baseUrl);
        $response = Bold_CheckoutPaymentBooster_Service_Client::post(
            $apiUrl,
            $websiteId,
            [
                'domains' => [
                    $domain,
                ],
            ]
        );
        if (isset($response->errors)) {
            return;
        }
        $checkoutSession->setBotFastlaneGatewayData($response->data);
    }

    /**
     * Get Fastlane Gateway data.
     *
     * @return array|null
     */
    public static function getGatewayData()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        return $checkoutSession->getBotFastlaneGatewayData();
    }

    /**
     * Check if Fastlane payment method is available.
     *
     * @return bool
     */
    private static function isAvailable()
    {
        $websiteId = (int)Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->isFastlaneEnabled($websiteId) && !Mage::getSingleton('customer/session')->isLoggedIn();
    }
}
