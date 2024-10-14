<?php

/**
 * Bold order hydrate service.
 */
class Bold_CheckoutPaymentBooster_Service_Order_Hydrate
{
    const HYDRATE_ORDER_URI = '/checkout_sidekick/{{shopId}}/order/%s';

    /**
     * Hydrate Bold order.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function hydrate(Mage_Sales_Model_Quote $quote)
    {
        $quote->collectTotals();
        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
        if (!$publicOrderId) {
            Mage::throwException('There is no public order ID in the checkout session.');
        }
        $body = Bold_CheckoutPaymentBooster_Service_Order_Hydrate_ExtractData::extractQuoteData($quote);
        $apiUri = sprintf(self::HYDRATE_ORDER_URI, $publicOrderId);
        $response = Bold_CheckoutPaymentBooster_Service_BoldClient::put(
            $apiUri,
            $quote->getStore()->getWebsiteId(),
            $body
        );
        if (isset($response->errors) || isset($response->error)) {
            Mage::throwException(
                'Cannot hydrate order, Quote ID: ' . $quote->getId() . ', Public Order ID: ' . $publicOrderId
            );
        }
    }
}
