<?php

/**
 * Bold payment authorization service.
 */
class Bold_CheckoutPaymentBooster_Service_Payment_Auth
{
    const AUTHORIZE_PAYMENT_URI = '/checkout/orders/{{shopId}}/%s/payments/auth/full';

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param array|null $data
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function full(Mage_Sales_Model_Quote $quote, ?array $data = null)
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $boldCheckoutData = $checkoutSession->getBoldCheckoutData();
        $publicOrderId = $boldCheckoutData->public_order_id;
        $errorMessage = Mage::helper('core')->__('Payment Authorization Failure.');

        if (!$publicOrderId) {
            Mage::throwException($errorMessage);
        }

        $apiUri = sprintf(self::AUTHORIZE_PAYMENT_URI, $publicOrderId);
        $response = json_decode(
            Bold_CheckoutPaymentBooster_Client::call(
                'POST',
                $apiUri,
                $quote->getStore()->getWebsiteId(),
                $data ? json_encode($data) : null
            )
        );

        $errors = $response->errors ?? [];
        if ($errors) {
            $logMessage = $errorMessage . PHP_EOL;
            foreach ($errors as $error) {
                $logMessage .= sprintf(
                    'Type: %s. Message: %s' . PHP_EOL,
                    $error->type,
                    $error->message
                );
            }
            Mage::log(
                $logMessage,
                Zend_Log::ERR,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
            );
            Mage::throwException($errorMessage);
        }

        if (!isset($response->data->transactions)) {
            Mage::throwException($errorMessage);
        }

        return $response->data;
    }
}
