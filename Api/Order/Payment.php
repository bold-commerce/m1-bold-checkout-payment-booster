<?php

/**
 * POST /rest/v1/orders/{{shopId}}/{{orderId}}/payment handler.
 *
 * @see /app/code/community/Bold/CheckoutPaymentBooster/etc/config.xml
 */
class Bold_CheckoutPaymentBooster_Api_Order_Payment
{
    /**
     * Update order. Create invoice, refund or cancel order if needed.
     *
     * @param string $shopIdentifier
     * @param string $publicOrderId
     * @param array $data
     * @return array
     * @throws Mage_Core_Exception
     */
    public static function update(
        $shopIdentifier,
        $publicOrderId,
        array $data
    ) {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $orderId = isset($data['platform_order_id']) ? $data['platform_order_id'] : null;
        $order = Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            Mage::throwException('Order not found');
        }
        $shopId = $config->getShopId((int)$order->getStore()->getWebsiteId());
        if ($shopId !== $shopIdentifier) {
            Mage::throwException('Shop identifier does not match');
        }
        $publicId = Bold_CheckoutPaymentBooster_Service_Order_Data::getOrderBoldData($order)->getPublicId();
        if ($publicOrderId !== $publicId) {
            Mage::throwException('Public order id does not match');
        }
        if (self::isRefund($order, $data)) {
            return self::refund($order);
        }
        if (self::isInvoice($order, $data)) {
            return self::invoice($order);
        }
        if (self::isCancel($order, $data)) {
            return self::cancel($order);
        }
        return self::getResponseBody($order);
    }

    /**
     * Build response array.
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    private static function getResponseBody(Mage_Sales_Model_Order $order)
    {
        return [
            'platform_id' => $order->getId(),
            'platform_friendly_id' => $order->getIncrementId(),
            'platform_customer_id' => $order->getCustomerId() ?: null,
        ];
    }

    /**
     * Create invoice for order.
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    private static function invoice(Mage_Sales_Model_Order $order)
    {
        $payment = $order->getPayment();
        $invoice = $order->prepareInvoice();
        $invoice->setRequestedCaptureCase('offline');
        $invoice->register();
        $invoice->setEmailSent(true);
        $invoice->setTransactionId($payment->getLastTransId());
        $invoice->getOrder()->setCustomerNoteNotify(true);
        $invoice->getOrder()->setIsInProcess(true);
        $order->addRelatedObject($invoice);
        $order->save();
        Bold_CheckoutPaymentBooster_Service_Order_Data::resetIsPlatformCapture($order);
        return self::getResponseBody($order);
    }

    /**
     * Refund order.
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    private static function refund(Mage_Sales_Model_Order $order)
    {
        $creditmemo = Mage::getModel('sales/service_order', $order)->prepareCreditmemo();
        $creditmemo->setPaymentRefundDisallowed(true);
        $creditmemo->register();
        $creditmemo->save();
        Bold_CheckoutPaymentBooster_Service_Order_Data::resetIsPlatformRefund($order);
        return self::getResponseBody($order);
    }

    /**
     * Check if order should be refunded.
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $data
     * @return bool
     */
    private static function isRefund(Mage_Sales_Model_Order $order, array $data)
    {
        if ($data['financial_status'] === 'refunded') {
            return !Bold_CheckoutPaymentBooster_Service_Order_Data::getIsPlatformRefund($order);
        }
        return false;
    }

    /**
     * Check if order should be invoiced.
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $data
     * @return bool
     */
    private static function isInvoice(Mage_Sales_Model_Order $order, array $data)
    {
        if ($data['financial_status'] === 'paid') {
            return !Bold_CheckoutPaymentBooster_Service_Order_Data::getIsPlatformCapture($order);
        }
        return false;
    }

    /**
     * Check if order should be cancelled.
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $data
     * @return bool
     */
    private static function isCancel(Mage_Sales_Model_Order $order, array $data)
    {
        if ($data['financial_status'] === 'cancelled') {
            return !Bold_CheckoutPaymentBooster_Service_Order_Data::getIsPlatformCancel($order);
        }
        return false;
    }

    /**
     * Cancel order.
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    private static function cancel(Mage_Sales_Model_Order $order)
    {
        $order->cancel();
        $order->save();
        Bold_CheckoutPaymentBooster_Service_Order_Data::resetIsPlatformCancel($order);
        return self::getResponseBody($order);
    }
}
