<?php

/**
 * Bold payment method model.
 */
class Bold_CheckoutPaymentBooster_Model_Payment_Bold extends Mage_Payment_Model_Method_Abstract
{
    const CODE = 'bold';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'bold_checkout_payment_booster/payment_form_bold';

    /**
     * @inheritDoc
     */
    public function isAvailable($quote = null)
    {
        if ($quote && $quote->getIsMultiShipping()) {
            return false;
        }
        return Mage::getSingleton('checkout/session')->getBoldCheckoutData() !== null;
    }
}
