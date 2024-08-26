<?php

/**
 * Bold payment service.
 */
class Bold_CheckoutPaymentBooster_Api_Payment_Gateway
{
    const CAPTURE_FULL_URL = '/checkout/orders/{{shopId}}/%s/payments/capture/full';
    const CAPTURE_PARTIALLY_URL = '/checkout/orders/{{shopId}}/%s/payments/capture';
    const REFUND_FULL_URL = '/checkout/orders/{{shopId}}/%s/refunds/full';
    const REFUND_PARTIALLY_URL = '/checkout/orders/{{shopId}}/%s/refunds';
    const CANCEL_URL = '/checkout/orders/{{shopId}}/%s/cancel';
    const CANCEL = 'cancel';
    const VOID = 'void';

    /**
     * Capture order payment.
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return void
     * @throws Exception
     */
    public static function capture(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();
        Bold_CheckoutPaymentBooster_Service_Order_Data::saveIsPlatformCapture($order);
        if ((float)$order->getGrandTotal() === (float)$amount) {
            $payment->setTransactionId(self::captureFull($order))
                ->setShouldCloseParentTransaction(true);
            return;
        }
        $payment->setTransactionId(self::capturePartial($order,
            (float)$amount));
        if ((float)$payment->getBaseAmountAuthorized() === $payment->getBaseAmountPaid() + $amount) {
            $payment->setShouldCloseParentTransaction(true);
        }
    }

    /**
     * Cancel payment transaction.
     *
     * @param Varien_Object $payment
     * @return void
     * @throws Exception
     */
    public static function cancel(Varien_Object $payment)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        Bold_CheckoutPaymentBooster_Service_Order_Data::saveIsPlatformCancel($order);
        Bold_CheckoutPaymentBooster_Api_Payment_Gateway::cancelVoid(
            $order,
            Bold_CheckoutPaymentBooster_Api_Payment_Gateway::CANCEL
        );
    }

    /**
     * Void payment transaction.
     *
     * @param Varien_Object $payment
     * @return void
     * @throws Exception
     */
    public static function void(Varien_Object $payment)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        Bold_CheckoutPaymentBooster_Service_Order_Data::resetIsPlatformCancel($order);
        self::cancelVoid(
            $order,
            Bold_CheckoutPaymentBooster_Api_Payment_Gateway::VOID
        );
    }

    /**
     * Refund payment via bold.
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return void
     * @throws Exception
     */
    public static function refund(Varien_Object $payment, $amount)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        Bold_CheckoutPaymentBooster_Service_Order_Data::saveIsPlatformRefund($order);
        $orderGrandTotal = Mage::app()->getStore()->roundPrice($order->getGrandTotal());
        $amount = Mage::app()->getStore()->roundPrice($amount);
        if ($orderGrandTotal <= $amount) {
            $transactionId = self::refundFull($order);
            $payment->setTransactionId($transactionId)
                ->setIsTransactionClosed(1)
                ->setShouldCloseParentTransaction(true);
            return;
        }
        $transactionId = self::refundPartial($order, (float)$amount);
        $payment->setTransactionId($transactionId)->setIsTransactionClosed(1);
        if ((float)$payment->getBaseAmountPaid() === $payment->getBaseAmountRefunded() + $amount) {
            $payment->setShouldCloseParentTransaction(true);
        }
    }

    /**
     * Capture a payment for the full order amount.
     *
     * @param Mage_Sales_Model_Order $order
     * @return string
     * @throws Mage_Payment_Exception
     * @throws Mage_Core_Exception
     */
    private static function captureFull(Mage_Sales_Model_Order $order)
    {
        self::keepTransactionAdditionalData($order);
        $orderPublicId = self::getOrderPublicId($order);
        $body = [
            'reauth' => true,
            'idempotent_key' => Mage::helper('core')->getRandomString(10),
        ];
        $url = sprintf(self::CAPTURE_FULL_URL, $orderPublicId);
        $websiteId = $order->getStore()->getWebsiteId();
        return self::sendCaptureRequest($url, $order->getIncrementId(), $websiteId, $body);
    }

    /**
     * Capture a payment by an arbitrary amount.
     *
     * @param Mage_Sales_Model_Order $order
     * @param float $amount
     * @return string
     * @throws Mage_Payment_Exception
     * @throws Mage_Core_Exception
     */
    private static function capturePartial(Mage_Sales_Model_Order $order, $amount)
    {
        self::keepTransactionAdditionalData($order);
        $orderPublicId = self::getOrderPublicId($order);
        $body = [
            'reauth' => true,
            'amount' => $amount * 100,
            'idempotent_key' => Mage::helper('core')->getRandomString(10),
        ];
        $url = sprintf(self::CAPTURE_PARTIALLY_URL, $orderPublicId);
        $websiteId = $order->getStore()->getWebsiteId();
        return self::sendCaptureRequest($url, $order->getIncrementId(), $websiteId, $body);
    }

    /**
     * Cancel order via bold.
     *
     * @param Mage_Sales_Model_Order $order
     * @param string $operation
     * @return void
     * @throws Mage_Payment_Exception
     * @throws Mage_Core_Exception
     */
    private static function cancelVoid(Mage_Sales_Model_Order $order, $operation)
    {
        self::keepTransactionAdditionalData($order);
        $orderPublicId = self::getOrderPublicId($order);
        $url = sprintf(self::CANCEL_URL, $orderPublicId);
        $body = [
            'reason' => $operation === self::CANCEL ? 'Order has been canceled.' : 'Order payment has been voided.',
        ];
        $websiteId = $order->getStore()->getWebsiteId();
        $result = Bold_CheckoutPaymentBooster_Service_Client::post(
            $url,
            $websiteId,
            $body
        );
        $errors = isset($result->errors) ? $result->errors : [];
        $logMessage = sprintf('Order id: %s. Errors: ' . PHP_EOL, $order->getIncrementId());
        $errorMessage = '';
        foreach ($errors as $error) {
            $logMessage .= sprintf('Type: %s. Message: %s' . PHP_EOL, $error->type, $error->message);
            $errorMessage = $error->message;
        }
        if ($errors) {
            Mage::log($logMessage, Zend_Log::ERR, 'bold_cancel.log');
            Mage::throwException($errorMessage);
        }
        if (!isset($result->data->application_state)) {
            Mage::throwException(
                $operation === self::CANCEL
                    ? 'Cannot cancel order. Please try again later.'
                    : 'Cannot void the payment. Please try again later.'
            );
        }
    }

    /**
     * Refund a payment for the full order amount.
     *
     * @param Mage_Sales_Model_Order $order
     * @return string
     * @throws Mage_Payment_Exception
     * @throws Mage_Core_Exception
     */
    private static function refundFull(Mage_Sales_Model_Order $order)
    {
        self::keepTransactionAdditionalData($order);
        $orderPublicId = self::getOrderPublicId($order);
        $body = [
            'email_notification' => false,
            'reason' => 'Magento credit memo created.',
        ];
        $url = sprintf(self::REFUND_FULL_URL, $orderPublicId);
        $websiteId = $order->getStore()->getWebsiteId();
        return self::sendRefundRequest($url, $order->getIncrementId(), $websiteId, $body);
    }

    /**
     * Refund a payment by an arbitrary amount.
     *
     * @param Mage_Sales_Model_Order $order
     * @param float $amount
     * @return string
     * @throws Mage_Payment_Exception
     * @throws Mage_Core_Exception
     */
    private static function refundPartial(Mage_Sales_Model_Order $order, $amount)
    {
        self::keepTransactionAdditionalData($order);
        $orderPublicId = self::getOrderPublicId($order);
        $body = [
            'email_notification' => false,
            'reason' => 'Magento credit memo created.',
            'amount' => $amount * 100,
        ];
        $url = sprintf(self::REFUND_PARTIALLY_URL, $orderPublicId);
        $websiteId = $order->getStore()->getWebsiteId();
        return self::sendRefundRequest($url, $order->getIncrementId(), $websiteId, $body);
    }

    /**
     * Perform capture api call.
     *
     * @param string $url
     * @param string $orderId
     * @param int $websiteId
     * @param array $body
     * @return string
     * @throws Mage_Core_Exception
     */
    private static function sendCaptureRequest($url, $orderId, $websiteId, array $body)
    {
        $result = Bold_CheckoutPaymentBooster_Service_Client::post(
            $url,
            $websiteId,
            $body
        );
        $errors = isset($result->errors) ? $result->errors : [];
        $logMessage = sprintf('Order id: %s. Errors: ' . PHP_EOL, $orderId);
        $errorMessage = Mage::helper('core')->__('Cannot capture order.');
        foreach ($errors as $error) {
            $errorMessage = $error->message;
            $logMessage .= sprintf(
                'Type: %s. Message: %s' . PHP_EOL,
                $error->type,
                $error->message
            );
        }
        if ($errors) {
            Mage::log($logMessage, Zend_Log::ERR, 'bold_capture.log');
            Mage::throwException($errorMessage);
        }
        if (!isset($result->data->capture->transactions)) {
            Mage::throwException($errorMessage);
        }
        $transaction = current($result->data->capture->transactions);

        return $transaction->transaction_id;
    }

    /**
     * Perform capture api call.
     *
     * @param string $url
     * @param string $orderId
     * @param int $websiteId
     * @param array $body
     * @return string
     * @throws Mage_Core_Exception
     */
    private static function sendRefundRequest($url, $orderId, $websiteId, array $body)
    {
        $result = Bold_CheckoutPaymentBooster_Service_Client::post($url, $websiteId, $body);
        $errors = isset($result->errors) ? $result->errors : [];
        $logMessage = sprintf('Order id: %s. Errors: ' . PHP_EOL, $orderId);
        $errorMessage = Mage::helper('core')->__('Cannot refund order.');
        foreach ($errors as $error) {
            $errorMessage = $error->message;
            $logMessage .= sprintf(
                'Code: %s. Type: %s. Message: %s' . PHP_EOL,
                $error->code,
                $error->type,
                $error->message
            );
        }
        if ($errors) {
            Mage::log($logMessage, Zend_Log::ERR, 'bold_refund.log');
            Mage::throwException($errorMessage);
        }
        if (!isset($result->data->refund->transaction_details)) {
            Mage::throwException($errorMessage);
        }
        $transactionDetails = current($result->data->refund->transaction_details);

        return $transactionDetails->transaction_number;
    }

    /**
     * Retrieve public order id.
     *
     * @param Mage_Sales_Model_Order $order
     * @return string
     * @throws Mage_Payment_Exception
     */
    private static function getOrderPublicId(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $orderData */
        $orderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE)->load(
            $order->getEntityId(),
            Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID
        );
        if (!$orderData->getPublicId()) {
            throw new Mage_Payment_Exception(
                sprintf('Cannot process order "%s" without "public order id".', $order->getIncrementId())
            );
        }

        return $orderData->getPublicId();
    }

    /**
     * Keep transaction additional information for future transactions.
     *
     * @param Mage_Sales_Model_Order $order
     * @return void
     */
    private static function keepTransactionAdditionalData(Mage_Sales_Model_Order $order)
    {
        $lastTransaction = $order->getPayment()->getAuthorizationTransaction();
        if (!$lastTransaction && $order->getPayment()->getLastTransId()) {
            $lastTransaction = $order->getPayment()->getTransaction($order->getPayment()->getLastTransId());
        }
        if (!$lastTransaction) {
            return;
        }
        $transactionAdditionalInfo = $lastTransaction->getAdditionalInformation() ?: [];
        foreach ($transactionAdditionalInfo as $key => $value) {
            $order->getPayment()->setTransactionAdditionalInfo($key, $value);
        }
    }
}
