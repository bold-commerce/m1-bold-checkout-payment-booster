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
}
