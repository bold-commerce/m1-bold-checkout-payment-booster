<?php

/**
 * Bold checkout observer.
 */
class Bold_CheckoutPaymentBooster_Observer_CheckoutObserver
{
    /**
     * Init Bold order.
     *
     * @param Varien_Event_Observer $event
     * @return void
     * @throws Exception
     */
    public function beforeCheckout(Varien_Event_Observer $event)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('checkout/cart')->getQuote();
        try {
            /** @var Bold_CheckoutPaymentBooster_Model_Quote $existingBoldQuote */
            $existingBoldQuote = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Quote::RESOURCE);
            $existingBoldQuote->load($quote->getId(), 'quote_id');

            // On an existing order resume, instead of creating a new order
            if ($existingBoldQuote->getId()) {
                $publicOrderId = $existingBoldQuote->getPublicId();
                $response = Bold_CheckoutPaymentBooster_Service_Bold::resumeQuote($quote, $publicOrderId);

                /** @var Mage_Checkout_Model_Session $checkoutSession */
                $checkoutSession = Mage::getSingleton('checkout/session');
                $checkoutData = $checkoutSession->getBoldCheckoutData();
                $checkoutData->jwt_token = $response->jwt_token;
                $checkoutSession->setBoldCheckoutData($checkoutData);
            } else {
                $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::initBoldCheckoutData($quote);

                // Store a reference to the public order ID and quote ID to check later if this order exists on Bold
                /** @var Bold_CheckoutPaymentBooster_Model_Quote $quoteData */
                $quoteData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Quote::RESOURCE);
                $quoteData->setQuoteId($quote->getId());
                $quoteData->setPublicId($publicOrderId);
                $quoteData->save();
            }

            if (!$publicOrderId) {
                return;
            }
        } catch (Exception $exception) {
            Mage::log($exception->getMessage(), Zend_Log::CRIT);
        }
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
        $quote = $order->getQuote();
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
            /** @var Bold_CheckoutPaymentBooster_Model_Quote $extQuoteData */
            $extQuoteData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Quote::RESOURCE);
            $extQuoteData->load($order->getQuoteId(), 'quote_id');

            /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
            $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
            $extOrderData->setOrderId($order->getEntityId());
            $extOrderData->setPublicId($extQuoteData->getPublicId());
            $extOrderData->save();
            Bold_CheckoutPaymentBooster_Service_Order_Update::updateOrderState($order);
            Bold_CheckoutPaymentBooster_Service_Bold::clearBoldCheckoutData();
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::CRIT);
        }
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
        $order->getPayment()->setTransactionId($transactionData->transactions[0]->transaction_id);
        $order->getPayment()->setIsTransactionClosed(0);
        $order->getPayment()->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
        $cardDetails = isset($transactionData->transactions[0]->tender_details)
            ? $transactionData->transactions[0]->tender_details
            : null;
        if ($cardDetails) {
            $order->getPayment()->setAdditionalInformation('card_details', serialize((array)$cardDetails));
        }
    }
}
