<?php

/**
 * Bold payment authorization service.
 */
class Bold_CheckoutPaymentBooster_Service_Payment_Auth
{
    const AUTHORIZE_PAYMENT_URI = '/checkout/orders/{{shopId}}/%s/payments/auth/full';

    /**
     * Authorize payment.
     *
     * @param string $publicOrderId
     * @param int $websiteId
     * @param array $data // can contain "idempotent_key" (optional)
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function full($publicOrderId, $websiteId, $data = [])
    {
        $apiUri = sprintf(self::AUTHORIZE_PAYMENT_URI, $publicOrderId);
        $response = Bold_CheckoutPaymentBooster_Service_BoldClient::post(
            $apiUri,
            $websiteId,
            $data
        );

        $errorMessage = Mage::helper('core')->__('Payment Authorization Failure.');
        $errors = isset($response->errors) ? $response->errors : [];
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
