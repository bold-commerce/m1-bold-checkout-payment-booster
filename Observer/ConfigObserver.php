<?php

/**
 * Bold configuration observer.
 */
class Bold_CheckoutPaymentBooster_Observer_ConfigObserver
{
    /** Group IDs that belong to this module (defined in etc/system.xml). */
    const BOLD_CONFIG_GROUPS = array(
        'bold_checkout_payment_booster_onboarding',
        'bold_checkout_payment_booster',
        'bold_checkout_payment_booster_advanced',
    );

    /**
     * Run Bold config save pipeline for checkout section changes.
     *
     * Single entry point replaces saveShopInfo, CORS, RSA, and processFlows observers
     * so failures stop the pipeline before flows run without valid RSA.
     *
     * Fires on admin_system_config_changed_section_checkout (whole section), so we
     * guard against unrelated Checkout group saves (e.g. native Magento checkout
     * settings) by checking that at least one Bold group was included in the POST.
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @see etc/config.xml adminhtml/events: admin_system_config_changed_section_checkout
     */
    public function onCheckoutConfigChanged(Varien_Event_Observer $event)
    {
        if (!$this->isBoldGroupSubmitted()) {
            return;
        }

        $websiteId = Mage::app()->getWebsite($event->getWebsite())->getId();
        try {
            Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::run($websiteId);
        } catch (Exception $exception) {
            $this->addErrorMessage($exception->getMessage());
        }
    }

    /**
     * Return true when the current POST contains at least one Bold config group.
     *
     * @return bool
     */
    private function isBoldGroupSubmitted()
    {
        $submittedGroups = Mage::app()->getRequest()->getPost('groups', array());
        if (!is_array($submittedGroups)) {
            return false;
        }

        foreach (self::BOLD_CONFIG_GROUPS as $group) {
            if (array_key_exists($group, $submittedGroups)) {
                return true;
            }
        }

        return false;
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
