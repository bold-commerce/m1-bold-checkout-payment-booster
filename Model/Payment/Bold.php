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
     * @var boolean
     */
    protected $_canAuthorize = true;

    /**
     * @var boolean
     */
    protected $_canCapture = true;

    /**
     * @var boolean
     */
    protected $_canCapturePartial = true;

    /**
     * @var boolean
     */
    protected $_canRefund = true;

    /**
     * @var boolean
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var boolean
     */
    protected $_canVoid = true;

    /**
     * @var boolean
     */
    protected $_canUseInternal = false;

    /**
     * @var boolean
     */
    protected $_canUseCheckout = true;

    /**
     * @var boolean
     */
    protected $_canUseForMultishipping = false;

    /**
     * @var boolean
     */
    protected $_canFetchTransactionInfo = true;

    /**
     * @var string
     */
    protected $_formBlockType = 'bold_checkout_payment_booster/payment_form_bold';

    /**
     * @inheritDoc
     */
    public function isAvailable($quote = null)
    {
        return Bold_CheckoutPaymentBooster_Service_Bold::getBoldCheckoutData();
    }

    /**
     * Build title considering payment info to match Bold Checkout payment description.
     *
     * @return string
     */
    public function getTitle()
    {
        $infoInstance = $this->getInfoInstance();
        if ($infoInstance && $infoInstance->getAdditionalInformation('card_details')) {
            $cardDetails = unserialize($infoInstance->getAdditionalInformation('card_details'));
            if (isset($cardDetails['brand']) && isset($cardDetails['last_four'])) {
                return ucfirst($cardDetails['brand']) . ': ending in ' . $cardDetails['last_four'];
            }
            if (isset($cardDetails['account']) && isset($cardDetails['email'])) {
                return 'PayPal: ' . $cardDetails['email'];
            }
        }
        return parent::getTitle();
    }

    /**
     * Capture order payment.
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return Bold_CheckoutPaymentBooster_Model_Payment_Bold
     * @throws Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        Bold_CheckoutPaymentBooster_Api_Payment_Gateway::capture($payment, $amount);
        return $this;
    }

    /**
     * Cancel payment transaction.
     *
     * @param Varien_Object $payment
     * @return Bold_CheckoutPaymentBooster_Model_Payment_Bold
     * @throws Exception
     */
    public function cancel(Varien_Object $payment)
    {
        Bold_CheckoutPaymentBooster_Api_Payment_Gateway::cancel($payment);
        return $this;
    }

    /**
     * Void payment transaction.
     *
     * @param Varien_Object $payment
     * @return Bold_CheckoutPaymentBooster_Model_Payment_Bold
     * @throws Exception
     */
    public function void(Varien_Object $payment)
    {
        Bold_CheckoutPaymentBooster_Api_Payment_Gateway::void($payment);
        return $this;
    }

    /**
     * Refund payment via bold.
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return Bold_CheckoutPaymentBooster_Model_Payment_Bold
     * @throws Exception
     */
    public function refund(Varien_Object $payment, $amount)
    {
        Bold_CheckoutPaymentBooster_Api_Payment_Gateway::refund($payment, $amount);
        return $this;
    }
}
