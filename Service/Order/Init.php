<?php

/**
 * Bold order initialization service.
 */
class Bold_CheckoutPaymentBooster_Service_Order_Init
{
    const SIMPLE_ORDER_URI = '/checkout_sidekick/{{shopId}}/order';

    /**
     * Initialize simple order.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function init(Mage_Sales_Model_Quote $quote)
    {
        $body = [
            'flow_id' => Bold_CheckoutPaymentBooster_Service_Flow::DEFAULT_FLOW_ID,
            'order_type' => 'simple_order',
            'cart_id' => $quote->getId() ?: '',
        ];
        $orderData = Bold_CheckoutPaymentBooster_Service_BoldClient::post(
            self::SIMPLE_ORDER_URI,
            $quote->getStore()->getWebsiteId(),
            $body
        );
        if (isset($orderData->error) || !isset($orderData->data->public_order_id)) {
            $message = isset($orderData->error->message) ? $orderData->error->message : 'Unknown error';
            Mage::throwException('Cannot initialize order, quote id: ' . $quote->getId() . ', error: ' . $message);
        }
        $orderData->data->flow_settings->fastlane_styles = Bold_CheckoutPaymentBooster_Service_Flow::getFastlaneStyles(
            $quote->getStore()->getWebsiteId()
        );
        return $orderData->data;
    }
}
