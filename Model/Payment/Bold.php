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
        return Bold_CheckoutPaymentBooster_Service_Bold::isAvailable();
    }

    /**
     * Build title considering payment info to match Bold Checkout payment description.
     *
     * @return string
     */
    public function getTitle()
    {
        $title = null;
        $infoInstance = $this->getInfoInstance();
        if ($infoInstance && $infoInstance->getCcLast4()) {
            $ccLast4 = $infoInstance->decrypt($infoInstance->getCcLast4());
            $title .= strlen($ccLast4) === 4
                ? $infoInstance->getCcType() . ': ending in ' . $ccLast4
                : $infoInstance->getCcType() . ': ' . $ccLast4;
        }
        return $title ?: parent::getTitle();
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
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $this->saveIsDelayedCapture($order);
        if ((float)$order->getGrandTotal() === (float)$amount) {
            $payment->setTransactionId(Bold_CheckoutPaymentBooster_Api_Payment_Gateway::captureFull($order))
                ->setShouldCloseParentTransaction(true);
            return $this;
        }
        $payment->setTransactionId(Bold_CheckoutPaymentBooster_Api_Payment_Gateway::capturePartial($order,
            (float)$amount));
        if ((float)$payment->getBaseAmountAuthorized() === $payment->getBaseAmountPaid() + $amount) {
            $payment->setShouldCloseParentTransaction(true);
        }

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
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $this->saveIsDelayedCapture($order);
        Bold_CheckoutPaymentBooster_Api_Payment_Gateway::cancel(
            $order,
            Bold_CheckoutPaymentBooster_Api_Payment_Gateway::CANCEL
        );
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
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $this->saveIsDelayedCapture($order);
        Bold_CheckoutPaymentBooster_Api_Payment_Gateway::cancel(
            $order,
            Bold_CheckoutPaymentBooster_Api_Payment_Gateway::VOID
        );
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
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $orderGrandTotal = Mage::app()->getStore()->roundPrice($order->getGrandTotal());
        $amount = Mage::app()->getStore()->roundPrice($amount);
        if ($orderGrandTotal <= $amount) {
            $transactionId = Bold_CheckoutPaymentBooster_Api_Payment_Gateway::refundFull($order);
            $payment->setTransactionId($transactionId)
                ->setIsTransactionClosed(1)
                ->setShouldCloseParentTransaction(true);
            return $this;
        }
        $transactionId = Bold_CheckoutPaymentBooster_Api_Payment_Gateway::refundPartial($order, (float)$amount);
        $payment->setTransactionId($transactionId)->setIsTransactionClosed(1);
        if ((float)$payment->getBaseAmountPaid() === $payment->getBaseAmountRefunded() + $amount) {
            $payment->setShouldCloseParentTransaction(true);
        }

        return $this;
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return void
     * @throws Exception
     */
    private function saveIsDelayedCapture(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        $extOrderData->setIsDelayedCapture(1);
        $extOrderData->save();
    }
}
