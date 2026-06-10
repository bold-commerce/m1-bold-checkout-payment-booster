<?php

/**
 * Persist API token changes and track fingerprint updates.
 *
 * The fingerprint lets SavePipeline detect token rotation and re-register RSA
 * without rotating the shared secret on every unrelated config save.
 */
class Bold_CheckoutPaymentBooster_Model_Adminhtml_System_Config_Backend_ApiToken
    extends Mage_Adminhtml_Model_System_Config_Backend_Encrypted
{
    /**
     * @return $this
     */
    protected function _afterSave()
    {
        // Registry flag is read by SavePipeline in the same request, after config save.
        if ($this->isValueChanged()) {
            Mage::register(
                'bold_checkout_api_token_changed_' . $this->getScopeId(),
                true,
                true
            );
        }

        $token = Mage::helper('core')->decrypt((string)$this->getValue());
        if ($token) {
            /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
            $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
            $config->setApiTokenFingerprint(hash('sha256', $token), (int)$this->getScopeId());
        }

        return parent::_afterSave();
    }
}
