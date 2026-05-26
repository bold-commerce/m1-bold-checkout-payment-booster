<?php

/**
 * Blocks duplicate Bold saveOrder requests (Firecheckout totals reload / double-submit).
 *
 * CHANGES vs main: new file. Wired in etc/config.xml on predispatch/postdispatch for:
 * - checkout/onepage/saveOrder
 * - firecheckout/index/saveOrder
 * - firecheckout/onecolumn/saveOrder
 *
 * Delegates to Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard.
 */
class Bold_CheckoutPaymentBooster_Observer_SaveOrderObserver
{
    /**
     * [vs main] Predispatch: allow first placement, block in-progress, or return success for existing order.
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function predispatchSaveOrder(Varien_Event_Observer $observer)
    {
        $paymentMethod = Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::getPaymentMethodFromRequest();
        if (!Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::isBoldPaymentMethod($paymentMethod)) {
            return;
        }

        $evaluation = Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::evaluatePlacementRequest();

        if ($evaluation['action'] === 'allow') {
            return;
        }

        if ($evaluation['action'] === 'success_existing' && !empty($evaluation['order'])) {
            /** @var Mage_Core_Controller_Varien_Action $controller */
            $controller = $observer->getEvent()->getControllerAction();
            Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::respondWithExistingOrderSuccess(
                $controller,
                $evaluation['order']
            );

            return;
        }

        $message = !empty($evaluation['message'])
            ? $evaluation['message']
            : Mage::helper('checkout')->__('Unable to place your order. Please try again.');

        Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::blockPlacement($message);
    }

    /**
     * [vs main] Postdispatch: clear session placement flag and MySQL lock after saveOrder completes or fails.
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function cleanupAfterSaveOrder(Varien_Event_Observer $observer)
    {
        $paymentMethod = Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::getPaymentMethodFromRequest();
        if (!Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::isBoldPaymentMethod($paymentMethod)) {
            return;
        }

        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        if ($session->getLastSuccessQuoteId()) {
            Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::clearPlacementState();

            return;
        }

        if ($session->getData(Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::SESSION_PLACEMENT_FLAG)) {
            Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::clearPlacementState();
        }
    }
}
