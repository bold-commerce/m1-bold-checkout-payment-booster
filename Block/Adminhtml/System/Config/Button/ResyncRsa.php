<?php

/**
 * Legacy admin button label for RSA re-sync (same action as Rotate Shared Key).
 */
class Bold_CheckoutPaymentBooster_Block_Adminhtml_System_Config_Button_ResyncRsa
    extends Bold_CheckoutPaymentBooster_Block_Adminhtml_System_Config_Button_AbstractRsaAction
{
    public function getButtonId()
    {
        return 'bold_resync_rsa_button';
    }

    public function getButtonLabel()
    {
        return Mage::helper('core')->__('Re-sync RSA with Bold');
    }

    public function getConfirmMessage()
    {
        return Mage::helper('core')->__(
            'Re-sync RSA with Bold? This rotates the shared secret used for inbound payment webhooks.'
        );
    }

    public function getControllerAction()
    {
        return 'resync';
    }
}
