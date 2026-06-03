<?php

/**
 * Bold checkout observer.
 */
class Bold_CheckoutPaymentBooster_Observer_CheckoutObserver
{
    /**
     * Seamless duplicate handling before saveOrder runs (only when placement already started).
     *
     * @param Varien_Event_Observer $event
     * @return void
     */
    public function predispatchSeamlessSaveOrder(Varien_Event_Observer $event)
    {
        $paymentMethod = Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::getPaymentMethodFromRequest();
        if (!Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::isBoldPaymentMethod($paymentMethod)) {
            return;
        }

        /** @var Mage_Core_Controller_Varien_Action $controller */
        $controller = $event->getEvent()->getControllerAction();
        Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::handleSaveOrderPredispatch($controller);
    }

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
        $methodsToProcess = [
            Bold_CheckoutPaymentBooster_Model_Payment_Fastlane::CODE,
            Bold_CheckoutPaymentBooster_Model_Payment_Bold::CODE,
        ];
        if (!in_array($paymentMethod, $methodsToProcess)) {
            return;
        }

        Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::assertCanPlaceOrder();

        $quote = $order->getQuote();
        $websiteId = $quote->getStore()->getWebsiteId();
        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();

        try {
            if ($publicOrderId
                && Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::hasPaymentAuthForPublicOrderId(
                    $publicOrderId
                )
            ) {
                return;
            }

            Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
            $transactionData = Bold_CheckoutPaymentBooster_Service_Payment_Auth::full($publicOrderId, $websiteId);
            $this->saveTransaction($order, $transactionData);

            if ($publicOrderId) {
                Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::markPaymentAuthForPublicOrderId(
                    $publicOrderId
                );
            }
        } catch (Mage_Core_Exception $e) {
            Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::clearPlacementState();
            Mage::log($e->getMessage(), Zend_Log::CRIT);
            Mage::throwException(Mage::helper('core')->__('Payment Authorization Failure.'));
        }
    }

    /**
     * Save Bold order data to database after order has been placed on Magento side.
     *
     * After Magento order has been placed, we have order id and can save Bold order data(public id) to database.
     *
     * @param Varien_Event_Observer $event
     * @return void
     */
    public function afterSaveOrder(Varien_Event_Observer $event)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $event->getEvent()->getOrder();
        $methodsToProcess = [
            Bold_CheckoutPaymentBooster_Model_Payment_Fastlane::CODE,
            Bold_CheckoutPaymentBooster_Model_Payment_Bold::CODE,
        ];
        if (!in_array($order->getPayment()->getMethod(), $methodsToProcess)) {
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
                        'DUPLICATE_ORDER_BLOCKED_MAPPING_SAVE',
                        array(
                            'attempted_magento_order_id' => $order->getId(),
                            'attempted_magento_increment_id' => $order->getIncrementId(),
                        )
                    );
                } else {
                    throw $e;
                }
            }
            Bold_CheckoutPaymentBooster_Service_Order_Update::updateOrderState($order);
            Bold_CheckoutPaymentBooster_Service_Bold::clearBoldCheckoutData();
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::CRIT);
        } finally {
            Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::clearPlacementState();
        }
    }

    /**
     * @param Varien_Event_Observer $event
     * @return void
     */
    public function clearPlacementStateOnFailure(Varien_Event_Observer $event)
    {
        $order = $event->getEvent()->getOrder();
        if ($order && $order->getPayment()
            && !Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::isBoldPaymentMethod(
                $order->getPayment()->getMethod()
            )
        ) {
            return;
        }

        Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::clearPlacementState();
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
            $order->getPayment()->setAdditionalInformation('card_details', serialize((array)$cardDetails));
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
