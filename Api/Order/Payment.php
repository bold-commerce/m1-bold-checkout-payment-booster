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

        // Extract and save payment information from Bold data
        self::savePaymentInformation($order, $data);

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
        Bold_CheckoutPaymentBooster_Service_Order_Data::setIsCaptureInProgress($order, true);
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
        Bold_CheckoutPaymentBooster_Service_Order_Data::setIsCaptureInProgress($order, false);

        $comment = self::buildTransactionComment('Payment captured', $order);
        $order->addStatusHistoryComment($comment, false);
        $order->save();

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
        Bold_CheckoutPaymentBooster_Service_Order_Data::setIsRefundInProgress($order, true);
        $creditmemo = Mage::getModel('sales/service_order', $order)->prepareCreditmemo();
        $creditmemo->setPaymentRefundDisallowed(true);
        $creditmemo->register();
        $creditmemo->save();
        $order->save();

        Bold_CheckoutPaymentBooster_Service_Order_Data::setIsRefundInProgress($order, false);

        $comment = self::buildTransactionComment('Payment refunded', $order);
        $order->addStatusHistoryComment($comment, false);
        $order->save();

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
            return !Bold_CheckoutPaymentBooster_Service_Order_Data::isRefundInProgress($order);
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
            return !Bold_CheckoutPaymentBooster_Service_Order_Data::getIsCaptureInProgress($order);
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
            return !Bold_CheckoutPaymentBooster_Service_Order_Data::getIsCancelInProgress($order);
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
        Bold_CheckoutPaymentBooster_Service_Order_Data::setIsCancelInProgress($order, true);
        $order->cancel();
        $order->save();
        Bold_CheckoutPaymentBooster_Service_Order_Data::setIsCancelInProgress($order, false);
        return self::getResponseBody($order);
    }

    /**
     * Extract and save payment transaction information from Bold webhook data.
     * Stores provider, provider_id, processed_at, and financial_status in payment additional_information.
     * Maintains all transactions in descending order by processed_at date.
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $data
     * @return void
     */
    private static function savePaymentInformation(Mage_Sales_Model_Order $order, array $data)
    {
        if (!isset($data['payments']) || !is_array($data['payments'])) {
            return;
        }

        $payments = $data['payments'];
        $payment = $order->getPayment();

        // Get existing transactions from additional_information
        $existingTransactions = $payment->getAdditionalInformation('bold_transactions');
        if (!is_array($existingTransactions)) {
            $existingTransactions = array();
        }

        // Extract financial_status from root level
        $financialStatus = isset($data['financial_status']) ? $data['financial_status'] : null;

        // Process each payment
        foreach ($payments as $paymentData) {
            if (!is_array($paymentData)) {
                continue;
            }

            // Extract the required fields
            $provider = isset($paymentData['provider']) ? $paymentData['provider'] : null;
            $processedAt = null;
            $providerId = null;

            // Check if transaction data exists
            if (isset($paymentData['transaction']) && is_array($paymentData['transaction'])) {
                $transaction = $paymentData['transaction'];
                $processedAt = isset($transaction['processed_at']) ? $transaction['processed_at'] : null;
                $providerId = isset($transaction['provider_id']) ? $transaction['provider_id'] : null;
            }

            // Only add if we have at least provider_id
            if ($providerId) {
                $transactionRecord = array(
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'processed_at' => $processedAt,
                    'financial_status' => $financialStatus
                );

                // Check if this transaction already exists (by provider_id)
                $exists = false;
                foreach ($existingTransactions as $existingTx) {
                    if (isset($existingTx['provider_id']) && $existingTx['provider_id'] === $providerId) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $existingTransactions[] = $transactionRecord;

                    $config = Mage::getModel('bold_checkout_payment_booster/config');
                    if ($config->isLogEnabled($order->getStore()->getWebsiteId())) {
                        Mage::log(
                            sprintf(
                                'Added Bold transaction for order %s: Provider=%s, Provider ID=%s, Processed At=%s, Financial Status=%s',
                                $order->getIncrementId(),
                                $provider ?: 'N/A',
                                $providerId,
                                $processedAt ?: 'N/A',
                                $financialStatus ?: 'N/A'
                            ),
                            Zend_Log::INFO,
                            Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
                            true
                        );
                    }
                } else {
                    $config = Mage::getModel('bold_checkout_payment_booster/config');
                    if ($config->isLogEnabled($order->getStore()->getWebsiteId())) {
                        Mage::log(
                            sprintf(
                                'Transaction %s already exists for order %s, skipping',
                                $providerId,
                                $order->getIncrementId()
                            ),
                            Zend_Log::DEBUG,
                            Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
                            true
                        );
                    }
                }
            } else {
                $config = Mage::getModel('bold_checkout_payment_booster/config');
                if ($config->isLogEnabled($order->getStore()->getWebsiteId())) {
                    Mage::log(
                        sprintf(
                            'No provider_id found in payment data for order %s',
                            $order->getIncrementId()
                        ),
                        Zend_Log::DEBUG,
                        Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
                        true
                    );
                }
            }
        }

        usort($existingTransactions, function($a, $b) {
            $timeA = isset($a['processed_at']) && $a['processed_at'] ? strtotime($a['processed_at']) : 0;
            $timeB = isset($b['processed_at']) && $b['processed_at'] ? strtotime($b['processed_at']) : 0;
            return $timeB - $timeA; // Descending order
        });

        // Get all current additional_information to preserve it
        $allAdditionalInfo = $payment->getAdditionalInformation();
        if (!is_array($allAdditionalInfo)) {
            $allAdditionalInfo = array();
        }

        $allAdditionalInfo['bold_transactions'] = $existingTransactions;

        foreach ($allAdditionalInfo as $key => $value) {
            $payment->setAdditionalInformation($key, $value);
        }

        $payment->save();

        $config = Mage::getModel('bold_checkout_payment_booster/config');


        if ($config->isLogEnabled($order->getStore()->getWebsiteId())) {
            Mage::log(
                sprintf(
                    'Saved %d Bold transaction(s) for order %s',
                    count($existingTransactions),
                    $order->getIncrementId()
                ),
                Zend_Log::DEBUG,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
                true
            );
        }
    }

    /**
     * Build a comment string with transaction details from Bold
     *
     * @param string $action
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    private static function buildTransactionComment($action, Mage_Sales_Model_Order $order)
    {
        $payment = $order->getPayment();
        $transactions = $payment->getAdditionalInformation('bold_transactions');

        $comment = $action . ' via Bold Payment Booster.';

        if (is_array($transactions) && !empty($transactions)) {
            // Get the most recent transaction (first one, since sorted by processed_at desc)
            $latestTransaction = $transactions[0];

            if (isset($latestTransaction['provider_id']) && $latestTransaction['provider_id']) {
                $comment .= ' Transaction ID: ' . $latestTransaction['provider_id'];
            }

            if (isset($latestTransaction['provider']) && $latestTransaction['provider']) {
                $comment .= ' (Provider: ' . $latestTransaction['provider'] . ')';
            }

            if (isset($latestTransaction['processed_at']) && $latestTransaction['processed_at']) {
                $comment .= ' Processed at: ' . $latestTransaction['processed_at'];
            }
        }

        return $comment;
    }
}