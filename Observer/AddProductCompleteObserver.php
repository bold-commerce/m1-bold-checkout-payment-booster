<?php

/**
 * Observer for `checkout_cart_add_product_complete` event
 *
 * @see Mage_Checkout_CartController::addAction
 */
class Bold_CheckoutPaymentBooster_Observer_AddProductCompleteObserver
{
    public function execute(Varien_Event_Observer $observer)
    {
        /** @var Mage_Core_Controller_Request_Http $request */
        $request = $observer->getEvent()->getRequest();
        $source = $request->getParam('source');

        if ($source !== 'expresspay') {
            return;
        }

        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');

        $checkoutSession->setNoCartRedirect(true);
        $checkoutSession->setCartWasUpdated(false);
    }
}
