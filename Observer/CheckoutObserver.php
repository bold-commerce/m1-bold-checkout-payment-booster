<?php

/**
 * Bold checkout observer.
 */
class Bold_CheckoutPaymentBooster_Observer_CheckoutObserver
{
    /**
     * Authorize payment before Magento order is placed.
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @throws Mage_Core_Exception
     */
    public function beforeSaveOrder(Varien_Event_Observer $event)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $event->getEvent()->getOrder();
        $paymentMethod = $order->getPayment()->getMethod();
        $methodsToProcess = array(
            Bold_CheckoutPaymentBooster_Model_Payment_Fastlane::CODE,
            Bold_CheckoutPaymentBooster_Model_Payment_Bold::CODE,
        );
        if (!in_array($paymentMethod, $methodsToProcess, true)) {
            return;
        }

        $quote = $this->resolveQuoteForOrder($order);
        Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::assertQuoteCanSubmit($quote);

        $epsOrderId = Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::getEpsOrderIdFromRequest();
        if ($epsOrderId) {
            $order->getPayment()->setAdditionalInformation(
                Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::PAYMENT_ADDITIONAL_EPS_ORDER_ID,
                $epsOrderId
            );
        }

        $websiteId = $quote->getStore()->getWebsiteId();
        try {
            Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
            $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
            $transactionData = Bold_CheckoutPaymentBooster_Service_Payment_Auth::full($publicOrderId, $websiteId);
            $this->saveTransaction($order, $transactionData);
        } catch (Mage_Core_Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::CRIT);
            Mage::throwException(Mage::helper('core')->__('Payment Authorization Failure.'));
        }
    }

    /**
     * Save Bold order data to database after order has been placed on Magento side.
     *
     * @param Varien_Event_Observer $event
     * @return void
     */
    public function afterSaveOrder(Varien_Event_Observer $event)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $event->getEvent()->getOrder();
        $methodsToProcess = array(
            Bold_CheckoutPaymentBooster_Model_Payment_Fastlane::CODE,
            Bold_CheckoutPaymentBooster_Model_Payment_Bold::CODE,
        );
        if (!in_array($order->getPayment()->getMethod(), $methodsToProcess, true)) {
            Bold_CheckoutPaymentBooster_Service_Bold::clearBoldCheckoutData();

            return;
        }

        try {
            /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
            $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
            $extOrderData->setOrderId($order->getEntityId());
            $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
            $extOrderData->setPublicId($publicOrderId);

            try {
                $extOrderData->save();
            } catch (Exception $e) {
                if ($publicOrderId && self::isDuplicatePublicIdException($e)) {
                    Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::logDuplicateOrderAttempt(
                        $publicOrderId,
                        'after_save_order mapping race magento_order=' . $order->getIncrementId()
                    );
                } else {
                    throw $e;
                }
            }

            Bold_CheckoutPaymentBooster_Service_Order_Update::updateOrderState($order);
            Bold_CheckoutPaymentBooster_Service_Bold::clearBoldCheckoutData();
            Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::clearPlacementState();
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::CRIT);
        }
    }

    /**
     * Order model often has no loaded quote during checkout_type_onepage_save_order.
     *
     * @param Mage_Sales_Model_Order $order
     * @return Mage_Sales_Model_Quote
     * @throws Mage_Core_Exception
     */
    private function resolveQuoteForOrder(Mage_Sales_Model_Order $order)
    {
        $quote = $order->getQuote();
        if ($quote && $quote->getId()) {
            return $quote;
        }

        if ($order->getQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            if ($quote->getId()) {
                return $quote;
            }
        }

        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $quote = $session->getQuote();
        if ($quote && $quote->getId()) {
            return $quote;
        }

        Mage::throwException(Mage::helper('checkout')->__('Your shopping cart could not be found.'));
    }

    /**
     * Add Bold transaction data to order payment.
     *
     * @param Mage_Sales_Model_Order $order
     * @param stdClass $transactionData
     * @return void
     * @throws Mage_Core_Exception
     */
    private function saveTransaction(Mage_Sales_Model_Order $order, stdClass $transactionData)
    {
        $transactionId = isset($transactionData->transactions[0]->transaction_id)
            ? $transactionData->transactions[0]->transaction_id
            : null;
        if (!$transactionId) {
            return;
        }
        $order->getPayment()->setTransactionId($transactionId);
        $order->getPayment()->setIsTransactionClosed(0);
        $order->getPayment()->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
        $cardDetails = isset($transactionData->transactions[0]->tender_details)
            ? $transactionData->transactions[0]->tender_details
            : null;
        if ($cardDetails) {
            $order->getPayment()->setAdditionalInformation('card_details', serialize((array) $cardDetails));
        }
    }

    /**
     * @param Exception $exception
     * @return bool
     */
    private static function isDuplicatePublicIdException(Exception $exception)
    {
        $message = $exception->getMessage();

        return stripos($message, 'Duplicate entry') !== false
            && stripos($message, 'UNQ_BOLD_CHECKOUT_PAYMENT_BOOSTER_ORDER_PUBLIC_ID') !== false;
    }
}
