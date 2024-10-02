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
     * @see etc/config.xml adminhtml/events: admin_system_config_changed_section_checkout
     */
    public function saveShopInfo(Varien_Event_Observer $event)
    {
        $websiteId = Mage::app()->getWebsite($event->getWebsite())->getId();
        try {
            Bold_CheckoutPaymentBooster_Service_ShopInfo::saveShopInfo($websiteId);
        } catch (Exception $exception) {
            $this->addErrorMessage($exception->getMessage());
        }
    }

    /**
     * Create|update or disable Payment Booster and Fastlane flows considering configuration.
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @see etc/config.xml adminhtml/events: admin_system_config_changed_section_checkout
     */
    public function processFlows(Varien_Event_Observer $event)
    {
        $websiteId = Mage::app()->getWebsite($event->getWebsite())->getId();
        try {
            Bold_CheckoutPaymentBooster_Service_Flow::processPaymentBoosterFlow($websiteId);
            Bold_CheckoutPaymentBooster_Service_Flow::processFastlaneFlow($websiteId);
        } catch (Exception $exception) {
            $this->addErrorMessage($exception->getMessage());
        }
    }

    /**
     * Add Magento domain for specific website to the CORS allow list.
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @see etc/config.xml adminhtml/events: admin_system_config_changed_section_checkout
     */
    public function addDomainToCorsAllowList(Varien_Event_Observer $event)
    {
        try {
            $websiteId = Mage::app()->getWebsite($event->getWebsite())->getId();
            $defaultStore = Mage::app()->getWebsite($websiteId)->getDefaultStore();
            $magentoUrl = $defaultStore->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            $domainList = Bold_CheckoutPaymentBooster_Service_Eps_Cors::getAllowList((int)$websiteId);
            foreach ($domainList as $domain) {
                if ($domain->domain === rtrim($magentoUrl, '/')) {
                    return;
                }
            }
            Bold_CheckoutPaymentBooster_Service_Eps_Cors::addDomainToCorsAllowList(
                (int)$websiteId,
                (string)$magentoUrl
            );
        } catch (Mage_Core_Exception $e) {
            $this->addErrorMessage($e->getMessage());
        }
    }

    /**
     * Set RSA configuration.
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @see etc/config.xml adminhtml/events: admin_system_config_changed_section_checkout
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
