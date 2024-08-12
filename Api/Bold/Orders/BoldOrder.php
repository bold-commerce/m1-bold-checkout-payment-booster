<?php

/**
 * Initialize order on Bold side service.
 */
class Bold_Checkout_Api_Bold_Orders_BoldOrder
{
    const ORDER_INIT_URL = '/checkout/orders/{{shopId}}/init';
    const FLOW_ID = 'Bold-Magento2';

    /**
     * Initialize order on bold side.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return stdClass
     * @throws Exception
     */
    public static function init(Mage_Sales_Model_Quote $quote, $flowId = self::FLOW_ID)
    {
        $body = [
            'flow_id' => $flowId,
            'api_session_id' => (string)$quote->getId(),
            'cart_items' => Bold_Checkout_Service_Extractor_Quote_Item::extractLineItems($quote),
            'actions' => Bold_Checkout_Service_QuoteActionManager::generateActionsData($quote),
            'order_meta_data' => [
                'cart_parameters' => [
                    'quote_id' => $quote->getId(),
                    'masked_quote_id' => null,
                    'store_id' => $quote->getStoreId(),
                    'website_id' => (int)$quote->getStore()->getWebsiteId(),
                ],
                'note_attributes' => [
                    'quote_id' => $quote->getId(),
                ],
            ],
        ];
        if ($quote->getCustomer()->getId()) {
            $body['customer'] = Bold_Checkout_Service_Extractor_Customer::extractForOrder($quote);
        }
        $orderData = json_decode(
            Bold_Checkout_Client::call(
                'POST',
                self::ORDER_INIT_URL,
                $quote->getStore()->getWebsiteId(),
                json_encode($body)
            )
        );
        if (!isset($orderData->data->public_order_id)) {
            Mage::throwException('Cannot initialize order, quote id ' . $quote->getId());
        }
        if ($quote->getCustomer()->getId() && !isset($orderData->data->application_state->customer->public_id)) {
            Mage::throwException('Cannot authenticate customer, customer id ' . $quote->getCustomerId());
        }
        return $orderData;
    }
}
