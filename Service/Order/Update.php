<?php

/**
 * Update Bold order service.
 */
class Bold_CheckoutPaymentBooster_Service_Order_Update
{
    const UPDATE_SIMPLE_ORDER_URI = '/checkout_sidekick/{{shopId}}/order/{{publicOrderId}}/state';
    const STATE_COMPLETE = 'order_complete';

    /**
     * Update Bold order to complete state.
     *
     * @param Mage_Sales_Model_Order $order
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function updateOrderState(Mage_Sales_Model_Order $order)
    {
        $body = [
            'state' => self::STATE_COMPLETE,
            'platform_order_id' => (string)$order->getId(),
            'platform_friendly_id' => (string)$order->getIncrementId(),
        ];

        $url = str_replace(
            '{{publicOrderId}}',
            Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId(),
            self::UPDATE_SIMPLE_ORDER_URI
        );

        $response = Bold_CheckoutPaymentBooster_Service_BoldClient::put(
            $url,
            $order->getStore()->getWebsiteId(),
            $body
        );

        if (isset($response->errors) || isset($response->error)) {
            $message = isset($updateResponse->error) ? json_encode($updateResponse->error) : 'n/a';
            Mage::throwException('Cannot update order state, order id: ' . $order->getId() . ', error: ' . $message);
        }
    }
}
