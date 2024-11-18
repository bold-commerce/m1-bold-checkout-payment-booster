<?php

/**
 * Observer for `controller_action_predispatch` event
 *
 * @see Mage_Core_Controller_Varien_Action::preDispatch
 */
class Bold_CheckoutPaymentBooster_Observer_PredispatchObserver
{
    /**
     * Initialize Bold order
     *
     * @param Varien_Event_Observer $observer
     * @return void
     * @throws Exception
     */
    public function initializeBoldOrder(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('checkout/cart')->getQuote();

        if (count($quote->getAllVisibleItems()) === 0) {
            return;
        }

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
