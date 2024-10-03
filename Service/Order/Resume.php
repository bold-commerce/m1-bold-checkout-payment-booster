<?php

/**
 * Refresh the order to get a new JWT.
 */
class Bold_CheckoutPaymentBooster_Service_Order_Resume
{
    const RESUME_SIMPLE_ORDER_URI = '/checkout_sidekick/{{shopId}}/order/{{publicOrderId}}/resume';

    /**
     * Resume an order using Sidekick
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $publicOrderId
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public static function resumeOrder(Mage_Sales_Model_Quote $quote, $publicOrderId)
    {
        $url = str_replace(
            '{{publicOrderId}}',
            $publicOrderId,
            self::RESUME_SIMPLE_ORDER_URI
        );

        $response = Bold_CheckoutPaymentBooster_Service_BoldClient::post(
            $url,
            $quote->getStore()->getWebsiteId()
        );

        if (isset($response->errors) || isset($response->error)) {
            $message = isset($updateResponse->error) ? json_encode($updateResponse->error) : 'n/a';
            Mage::throwException('Cannot refresh order state, quote id: ' . $quote->getId() . ', error: ' . $message);
        }

        return $response->data;
    }
}
