<?php

/**
 * Observer for `controller_action_predispatch_checkout_cart_index` event
 */
class Bold_CheckoutPaymentBooster_Observer_CartObserver
{
    /**
     * Initialize Bold order
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @throws Exception
     */
    public function execute(Varien_Event_Observer $event)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('checkout/cart')->getQuote();

        try {
            Bold_CheckoutPaymentBooster_Service_Bold::initBoldCheckoutData($quote);

            $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();

            if ($publicOrderId === null) {
                return;
            }
        } catch (Exception $exception) {
            Mage::log($exception->getMessage(), Zend_Log::CRIT);
        }

        Mage::getSingleton('checkout/session')->setCartWasUpdated(false);
    }
}
