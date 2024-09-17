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
            Bold_CheckoutPaymentBooster_Service_ShopInfo::saveShopInfo($websiteId);
        } catch (Exception $exception) {
            $this->addErrorMessage($exception->getMessage());
        }
    }

    /**
     * Set RSA configuration.
     *
     * @param Varien_Event_Observer $event
     * @return void
     */
    public function setRsaConfig(Varien_Event_Observer $event)
    {
        $websiteId = Mage::app()->getWebsite($event->getWebsite())->getId();
        try {
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::setRsaConfig($websiteId);
        } catch (Exception $exception) {
            $this->addErrorMessage($exception->getMessage());
        }
    }

    /**
     * Send PIGI styles to the Bold.
     *
     * @param Varien_Event_Observer $event
     * @return void
     */
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
            if ($oldRules === $newRules) {
                return;
            }
            Bold_CheckoutPaymentBooster_Service_PIGI::updateStyles(
                $websiteId,
                Bold_CheckoutPaymentBooster_Service_PIGI::buildStylesPayload([$newRules])
            );
        } catch (Exception $e) {
            $this->addErrorMessage($e->getMessage());
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
