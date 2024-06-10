<?php

/**
 * Bold configuration observer.
 */
class Bold_CheckoutPaymentBooster_Observer_ConfigObserver
{
    /**
     * Set Bold shop ID.
     *
     * @param Varien_Event_Observer $event
     * @return void
     */
    public function setShopId(Varien_Event_Observer $event)
    {
        $websiteId = Mage::app()->getWebsite($event->getWebsite())->getId();
        try {
            Bold_CheckoutPaymentBooster_Service_ShopId::set($websiteId);
        } catch (Exception $exception) {
            Bold_CheckoutPaymentBooster_Service_LogManager::log(
                'ERROR: Bold shop identifier set failed. ' . $exception->getMessage(),
                $websiteId
            );
        }
    }
}
