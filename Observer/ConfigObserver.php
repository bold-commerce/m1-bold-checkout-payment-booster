<?php

/**
 * Bold configuration observer.
 */
class Bold_CheckoutPaymentBooster_Observer_ConfigObserver
{
    /**
     * Run Bold config save pipeline for checkout section changes.
     *
     * Single entry point replaces saveShopInfo, CORS, RSA, and processFlows observers
     * so failures stop the pipeline before flows run without valid RSA.
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @see etc/config.xml adminhtml/events: admin_system_config_changed_section_checkout
     */
    public function onCheckoutConfigChanged(Varien_Event_Observer $event)
    {
        $websiteId = Mage::app()->getWebsite($event->getWebsite())->getId();
        try {
            Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::run($websiteId);
        } catch (Exception $exception) {
            $this->addErrorMessage($exception->getMessage());
        }
    }

    /**
     * Add unique error message to the session and log.
     *
     * @param string $messageToAdd
     * @return void
     */
    private function addErrorMessage($messageToAdd)
    {
        foreach (Mage::getSingleton('core/session')->getMessages()->getErrors() as $message) {
            if ($message->getCode() === $messageToAdd) {
                return;
            }
        }
        Mage::getSingleton('core/session')->addError($messageToAdd);
        Mage::log(
            $messageToAdd,
            Zend_Log::ERR,
            Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
        );
    }
}
