<?php

/**
 * Observer for `controller_action_predispatch_checkout_onepage_saveShipping` event
 *
 * @see Mage_Core_Controller_Varien_Action::preDispatch
 * @see Mage_Checkout_OnepageController::saveShippingAction
 */
class Bold_CheckoutPaymentBooster_Observer_BeforeCheckoutSaveShippingObserver
{
    /**
     * Mark cart as updated for Express Pay orders
     *
     * Resolves a "403 Forbidden" error thrown when calling the `saveShipping` endpoint via AJAX outside the checkout.
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function execute(Varien_Event_Observer $observer)
    {
        /** @var Mage_Core_Controller_Request_Http $request */
        $request = $observer->getEvent()->getControllerAction()->getRequest();
        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();

        if ($request->getParam('source') !== 'expresspay' || $publicOrderId === null) {
            return;
        }

        Mage::getSingleton('checkout/session')->setCartWasUpdated(false);
    }
}
