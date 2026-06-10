<?php

/**
 * Admin button to rotate the RSA shared key with Bold and save it locally after Magento auth verification.
 */
class Bold_CheckoutPaymentBooster_Block_Adminhtml_System_Config_Button_RotateSharedKey
    extends Bold_CheckoutPaymentBooster_Block_Adminhtml_System_Config_Button_AbstractRsaAction
{
    public function getButtonId()
    {
        return 'bold_rotate_shared_key_button';
    }

    public function getButtonLabel()
    {
        return Mage::helper('core')->__('Rotate Shared Key');
    }

    public function getConfirmMessage()
    {
        return Mage::helper('core')->__(
            'Rotate the shared key with Bold? This generates a new secret, registers it with Bold, verifies the change, and saves it locally.'
        );
    }

    public function getControllerAction()
    {
        return 'rotate';
    }
}
