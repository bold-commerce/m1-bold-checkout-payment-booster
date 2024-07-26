<?php

/**
 * Bold checkout observer.
 */
class Bold_CheckoutPaymentBooster_Observer_CheckoutObserver
{
    /**
     * Init Bold order.
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @throws Exception
     */
    public function beforeCheckout(Varien_Event_Observer $event)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('checkout/cart')->getQuote();
        try {
            if (!Bold_CheckoutPaymentBooster_Service_Order_Init::isAllowed($quote)) {
                return;
            }
            Bold_CheckoutPaymentBooster_Service_Bold::loadBoldCheckoutData($quote);
            $checkoutData = Bold_CheckoutPaymentBooster_Service_Bold::getBoldCheckoutData();
            $publicOrderId = $checkoutData ? $checkoutData->public_order_id : null;
            if (!$publicOrderId) {
                return;
            }
            Bold_CheckoutPaymentBooster_Service_Fastlane::loadGatewayData(
                $publicOrderId,
                (int)$quote->getStore()->getWebsiteId()
            );
        } catch (Exception $exception) {
            Mage::log($exception->getMessage(), Zend_Log::CRIT);
        }
    }

    /**
     * Authorize payment before Magento order is placed.
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @throws Mage_Core_Exception
     */
    public function beforeSaveOrder(Varien_Event_Observer $event)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $event->getEvent()->getOrder();
        $boldCheckoutData = Bold_CheckoutPaymentBooster_Service_Bold::getBoldCheckoutData();
        $publicOrderId = $boldCheckoutData->public_order_id;
        $paymentMethod = $order->getPayment()->getMethod();
        $methodsToProcess = [
            Bold_CheckoutPaymentBooster_Model_Payment_Fastlane::CODE,
            Bold_CheckoutPaymentBooster_Model_Payment_Bold::CODE,
        ];
        if (!in_array($paymentMethod, $methodsToProcess)) {
            return;
        }
        $quote = $order->getQuote();
        $websiteId = $quote->getStore()->getWebsiteId();
        // hydrate bold order before auth payment
        Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
        // TODO: check if order total and transactions are correct
        $paymentAuthData = Bold_CheckoutPaymentBooster_Service_Payment_Auth::full($publicOrderId, $websiteId);
    }
}
