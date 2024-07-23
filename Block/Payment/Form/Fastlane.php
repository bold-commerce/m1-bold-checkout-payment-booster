<?php

/**
 * Bold payment method block.
 */
class Bold_CheckoutPaymentBooster_Block_Payment_Form_Fastlane extends Mage_Payment_Block_Form
{
    const PAYPAL_FASTLANE_CLIENT_TOKEN_URL = 'checkout/orders/{{shopId}}/%s/paypal_fastlane/client_token';
    const PATH = '/checkout/storefront/';

    /**
     * @var Mage_Sales_Model_Quote|null
     */
    private $quote = null;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->quote = Mage::getSingleton('checkout/session')->getQuote();
        $this->setTemplate('bold/checkout_payment_booster/payment/form/bold_fastlane_method.phtml');
    }

    /**
     * Check if fastlane payment method is available.
     *
     * @return int
     */
    public function isAvailable()
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Payment_Fastlane $fastlane */
        $fastlane = Mage::getModel('bold_checkout_payment_booster/payment_fastlane');
        $isAvailable = !Mage::getSingleton('customer/session')->isLoggedIn() && $fastlane->isAvailable($this->quote);
        return (int)$isAvailable;
    }

    /**
     * Get address container style.
     *
     * @return string
     */
    public function getAddressContainerStyle()
    {
        $websiteId = $this->quote->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        return $config->getFastlaneAddressContainerStyles($websiteId);
    }

    /**
     * Get payment gateway data.
     *
     * @return string
     * @throws Mage_Core_Exception
     */
    public function getGatewayData()
    {
        $websiteId = $this->quote->getStore()->getWebsiteId();
        $session = Mage::getSingleton('checkout/session');
        $publicOrderId = isset($session->getBoldCheckoutData()->public_order_id)
            ? $session->getBoldCheckoutData()->public_order_id
            : null;
        if (!$publicOrderId) {
            return json_encode([]);
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
            Mage::throwException('Something went wrong while fetching the Fastlane gateway data.');
        }
        return json_encode($response->data);
    }

    /**
     * Retrieve Fastlane styles.
     *
     * @return string
     */
    public function getFastlaneStyles()
    {
        $boldCheckoutData = Mage::getSingleton('checkout/session')->getBoldCheckoutData();
        $styles = (object)[];
        if (!$boldCheckoutData) {
            return json_encode($styles);
        }

        // TODO: Need to implement styles retrieving from Checkout admin
        // (for now there is no ability to get this information if order was created using checkout_sidekick)

        return json_encode($styles);
    }

    /**
     * Retrieve Bold Storefront API URL.
     *
     * @return string|null
     */
    public function getBoldApiUrl()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $boldCheckoutData = $checkoutSession->getBoldCheckoutData();
        if (!$boldCheckoutData) {
            return null;
        }
        $websiteId = $checkoutSession->getQuote()->getStore()->getWebsiteId();
        try {
            $shopId = Bold_CheckoutPaymentBooster_Service_ShopId::get($websiteId);
        } catch (Mage_Core_Exception $e) {
            return null;
        }
        $orderId = $boldCheckoutData->public_order_id;
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $apiUrl = $config->getApiUrl($websiteId);
        return $apiUrl . self::PATH . $shopId . '/' . $orderId . '/';
    }

    /**
     * Retrieve JWT token.
     *
     * @return string|null
     */
    public function getJwtToken()
    {
        $boldCheckoutData = Mage::getSingleton('checkout/session')->getBoldCheckoutData();
        if (!$boldCheckoutData) {
            return null;
        }
        return $boldCheckoutData->jwt_token;
    }
}
