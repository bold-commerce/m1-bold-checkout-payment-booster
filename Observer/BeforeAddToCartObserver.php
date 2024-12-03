<?php

/**
 * Observer for `controller_action_predispatch_checkout_cart_add` event
 *
 * @see Mage_Core_Controller_Varien_Action::preDispatch
 * @see Mage_Checkout_CartController::addAction
 */
class Bold_CheckoutPaymentBooster_Observer_BeforeAddToCartObserver
{
    /**
     * Clear the cart before checking out with Express Pay from the product detail page
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function resetQuote(Varien_Event_Observer $observer)
    {
        /** @var Mage_Core_Controller_Varien_Action $controllerAction */
        $controllerAction = $observer->getEvent()->getControllerAction();
        $request = $controllerAction->getRequest();
        $isExpressPayOrder = $request->getParam('source') === 'expresspay';
        $shouldEmptyCart = Mage::getSingleton('checkoutpaymentbooster/config')
            ->shouldEmptyCartForExpressPayOnProductPage();
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $checkoutSession->getQuote();

        if (!$isExpressPayOrder || !$shouldEmptyCart || count($quote->getAllVisibleItems()) === 0) {
            return;
        }

        $checkoutSession->setCheckoutState(true);

        /** @var Mage_Checkout_Model_Cart $cart */
        $cart = Mage::getSingleton('checkout/cart');

        $cart->truncate()
            ->save();

        $checkoutSession->clear();
    }
}
