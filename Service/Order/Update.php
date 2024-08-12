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
     * @return stdClass
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
        $updateResponse = Bold_CheckoutPaymentBooster_Service_Client::put(
            $url,
            $order->getStore()->getWebsiteId(),
            $body
        );
        if (isset($updateResponse->error) || !isset($updateResponse->data->public_order_id)) {
            $message = json_encode($updateResponse->error);
            Mage::throwException('Cannot update order state, order id: ' . $order->getId() . ', error: ' . $message);
        }
        return $updateResponse->data;
    }
}
