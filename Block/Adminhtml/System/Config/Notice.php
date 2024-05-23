<?php

/**
 * Show notification on global scope.
 */
class Bold_CheckoutPaymentBooster_Block_Adminhtml_System_Config_Notice extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * @inheritDoc
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        return '<span class="notice-text">'
            . Mage::helper('adminhtml')->__('Please switch to "Website" scope to configure Bold Checkout Integration.')
            . '</span>';
    }
}
