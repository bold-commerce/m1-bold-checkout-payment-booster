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
     * @param string $flowId
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function init(Mage_Sales_Model_Quote $quote, $flowId)
    {
        /*$orderData = self::lookupForExistingOrder($quote);
        if ($orderData) {
            return $orderData->data;
        }  there is no flow setting data in the response at the moment. */
        $body = [
            'flow_id' => $flowId,
            'order_type' => 'simple_order',
            'cart_id' => $quote->getId(),
        ];
        $orderData = Bold_CheckoutPaymentBooster_Service_Client::post(
            self::SIMPLE_ORDER_URI,
            $quote->getStore()->getWebsiteId(),
            $body
        );
        if (isset($orderData->error) || !isset($orderData->data->public_order_id)) {
            $message = isset($orderData->error->message) ? $orderData->error->message : 'Unknown error';
            Mage::throwException('Cannot initialize order, quote id: ' . $quote->getId() . ', error: ' . $message);
        }

        /** @var Bold_CheckoutPaymentBooster_Model_Quote $resetData */
        $resetData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Quote::RESOURCE);
        $resetData->resetQuoteId($quote->getId());

        /** @var Bold_CheckoutPaymentBooster_Model_Quote $quoteData */
        $quoteData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Quote::RESOURCE);
        $quoteData->setQuoteId($quote->getId());
        $quoteData->setPublicId($orderData->data->public_order_id);
        $quoteData->save();

        return $orderData->data;
    }

    /**
     * Check if order initialization is allowed.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return bool
     */
    public static function isAllowed(Mage_Sales_Model_Quote $quote)
    {
        $websiteId = $quote->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        if (!$config->isPaymentBoosterEnabled($websiteId)) {
            return false;
        }

        return true;
    }

    /**
     * Lookup for existing order.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return stdClass|null
     */
    private static function lookupForExistingOrder(Mage_Sales_Model_Quote $quote)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Quote $quote */
        $quoteBoldData = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Quote::RESOURCE);
        $quoteBoldData = $quoteBoldData->load($quote->getId(), Bold_CheckoutPaymentBooster_Model_Quote::QUOTE_ID);
        if (!$quoteBoldData->getId()) {
            return null;
        }
        $orderData = Bold_CheckoutPaymentBooster_Service_Client::post(
            self::SIMPLE_ORDER_URI . '/' . $quoteBoldData->getPublicId() . '/resume',
            $quote->getStore()->getWebsiteId()
        );
        if (isset($orderData->error)) {
            return null;
        }
        return $orderData;
    }
}
