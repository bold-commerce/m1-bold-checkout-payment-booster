<?php

/**
 * Bold payment method block.
 */
class Bold_CheckoutPaymentBooster_Block_Payment_Form_Bold extends Bold_CheckoutPaymentBooster_Block_Payment_Form_Base
{
    const PATH = '/checkout/storefront/';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('bold/checkout_payment_booster/payment/form/bold_method.phtml');
    }
}
