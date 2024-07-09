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
     * @throws Mage_Core_Exception
     */
    public function setShopId(Varien_Event_Observer $event)
    {
        $websiteId = Mage::app()->getWebsite($event->getWebsite())->getId();
        try {
            Bold_CheckoutPaymentBooster_Service_ShopId::set($websiteId);
        } catch (Exception $exception) {
            Mage::log($exception->getMessage(), Zend_Log::CRIT);
        }
    }

    public function sendPigiStyles(Varien_Event_Observer $event)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $websiteId = Mage::app()->getWebsite($event->getWebsite())->getId();
        if (!$config->isPaymentBoosterEnabled($websiteId)) {
            return;
        }
        try {
            $savedValue = $config->getPaymentCss($websiteId);
            $newRules = $savedValue
                ? preg_replace('/\s+/', ' ', unserialize($savedValue))
                : Bold_CheckoutPaymentBooster_Service_PIGI::getDefaultCss();
            $savedStyles = Bold_CheckoutPaymentBooster_Service_PIGI::getStyles($websiteId);
            $oldRules = isset($savedStyles->css_rules[0]->cssText) ? $savedStyles->css_rules[0]->cssText : '';
            if ($oldRules !== $newRules) {
                Bold_CheckoutPaymentBooster_Service_PIGI::updateStyles(
                    $websiteId,
                    Bold_CheckoutPaymentBooster_Service_PIGI::build([$newRules])
                );
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR);
        }
    }
}
