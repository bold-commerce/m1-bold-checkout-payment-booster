<?php

/**
 * Bold payment method model.
 */
class Bold_CheckoutPaymentBooster_Model_Method_Bold extends Mage_Payment_Model_Method_Abstract
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
    protected $_formBlockType = 'bold_checkout_payment_booster/form_payment';

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

    /**
     * Build title considering payment info to match Bold Checkout payment description.
     *
     * @return string
     */
    public function getTitle()
    {
        $infoInstance = $this->getInfoInstance();
        if ($infoInstance && $infoInstance->getCcLast4()) {
            $ccLast4 = $infoInstance->decrypt($infoInstance->getCcLast4());
            return strlen($ccLast4) === 4
                ? $infoInstance->getCcType() . ': ending in ' . $ccLast4
                : $infoInstance->getCcType() . ': ' . $ccLast4;
        }

        return parent::getTitle();
    }

    /**
     * Capture order payment.
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return Bold_CheckoutPaymentBooster_Model_Method_Bold
     * @throws Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $websiteId = $order->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        if (!$config->isPaymentBoosterEnabled($websiteId)) {
            Mage::throwException(
                'Cannot create Invoice.'
                . ' Please make sure Bold Checkout module output is enabled and Bold Checkout Integration is on.'
            );
        }

        // TODO: implement capture order payment.

        return $this;
    }

    /**
     * Cancel payment transaction.
     *
     * @param Varien_Object $payment
     * @return Bold_CheckoutPaymentBooster_Model_Method_Bold
     * @throws Exception
     */
    public function cancel(Varien_Object $payment)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $websiteId = $order->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        if (!$config->isPaymentBoosterEnabled($websiteId)) {
            Mage::throwException(
                'Cannot cancel the order.'
                . ' Please make sure Bold Checkout module output is enabled and Bold Checkout Integration is on.'
            );
        }

        // TODO: implement cancel payment transaction.

        return $this;
    }

    /**
     * Void payment transaction.
     *
     * @param Varien_Object $payment
     * @return Bold_CheckoutPaymentBooster_Model_Method_Bold
     * @throws Exception
     */
    public function void(Varien_Object $payment)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $websiteId = $order->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        if (!$config->isPaymentBoosterEnabled($websiteId)) {
            Mage::throwException(
                'Cannot void the order payment.'
                . ' Please make sure Bold Checkout module output is enabled and Bold Checkout Integration is on.'
            );
        }

        // TODO: implement void payment transaction.

        return $this;
    }

    /**
     * Refund payment.
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return Bold_CheckoutPaymentBooster_Model_Method_Bold
     * @throws Exception
     */
    public function refund(Varien_Object $payment, $amount)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $websiteId = $order->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        if (!$config->isPaymentBoosterEnabled($websiteId)) {
            Mage::throwException(
                'Cannot create Credit Memo.'
                . ' Please make sure Bold Checkout module output is enabled and Bold Checkout Integration is on.'
            );
        }

        // TODO: implement refund payment.

        return $this;
    }
}
