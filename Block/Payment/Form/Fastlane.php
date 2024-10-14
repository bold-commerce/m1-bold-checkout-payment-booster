<?php

/**
 * Bold payment method block.
 */
class Bold_CheckoutPaymentBooster_Block_Payment_Form_Fastlane extends
    Bold_CheckoutPaymentBooster_Block_Payment_Form_Base
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();
        if ($this->isFastlaneAvailable()) {
            $this->setTemplate('bold/checkout_payment_booster/payment/form/bold_fastlane_method.phtml');
        }
    }

    /**
     * Retrieve Fastlane styles.
     *
     * @return string
     */
    public function getFastlaneStyles()
    {
        $fastlaneStyles = Bold_CheckoutPaymentBooster_Service_Bold::getFastlaneStyles();
        $fastlaneStyles = $fastlaneStyles ? (array)$fastlaneStyles : [];
        return json_encode($fastlaneStyles);
    }
}
