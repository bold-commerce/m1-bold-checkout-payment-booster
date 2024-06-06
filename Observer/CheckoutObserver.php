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
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->setBoldCheckoutData(null);

        try {
            if (!Bold_CheckoutPaymentBooster_Service_Order_Init::isAllowed($quote)) {
                return;
            }
            $flowId = Bold_CheckoutPaymentBooster_Service_Flow::getId($quote);
            $checkoutData = Bold_CheckoutPaymentBooster_Service_Order_Init::init($quote, $flowId);
            $checkoutSession->setBoldCheckoutData($checkoutData);
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
        $checkoutSession = Mage::getSingleton('checkout/session');
        $boldCheckoutData = $checkoutSession->getBoldCheckoutData();
        $publicOrderId = $boldCheckoutData->public_order_id;
        $paymentMethod = $order->getPayment()->getMethod();

        if (!$publicOrderId
            || $paymentMethod !== Bold_CheckoutPaymentBooster_Model_Method_Bold::CODE
        ) {
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
