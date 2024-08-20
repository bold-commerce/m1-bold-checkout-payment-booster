<?php

/**
 * Update order item fulfillment status on bold side observer.
 */
class Bold_CheckoutPaymentBooster_Observer_OrderObserver
{
    /**
     * Fulfill order items on bold side after order has been invoiced|shipped.
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @throws Mage_Payment_Exception|Mage_Core_Exception
     */
    public function fulfillOrderItems(Varien_Event_Observer $event)
    {
        $order = $event->getDataObject();
        $websiteId = $order->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $boldConfig */
        $boldConfig = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        if (!$boldConfig->isPaymentBoosterEnabled($websiteId)) {
            return;
        }
        Bold_CheckoutPaymentBooster_Api__Order_Items::fulfilItems($order);
    }
}
