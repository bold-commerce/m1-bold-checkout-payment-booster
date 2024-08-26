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
            Bold_CheckoutPaymentBooster_Service_Bold::initBoldCheckoutData($quote);
            $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
            if (!$publicOrderId) {
                return;
            }
            Bold_CheckoutPaymentBooster_Service_Fastlane::loadGatewayData(
                $publicOrderId,
                (int)$quote->getStore()->getWebsiteId()
            );
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
        // hydrate bold order before auth payment
        Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
        $transactionData = Bold_CheckoutPaymentBooster_Service_Payment_Auth::full($publicOrderId, $websiteId);
        $order->getPayment()->setTransactionId($transactionData->transactions[0]->transaction_id);
        $order->getPayment()->setIsTransactionClosed(0);
        $order->getPayment()->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
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
            Bold_CheckoutPaymentBooster_Service_Fastlane::clearGatewayData();
            return;
        }
        try {
            /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
            $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
            $extOrderData->setOrderId($order->getEntityId());
            $extOrderData->setPublicId(Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId());
            $extOrderData->save();
            Bold_CheckoutPaymentBooster_Service_Order_Update::updateOrderState($order);
            Bold_CheckoutPaymentBooster_Service_Bold::clearBoldCheckoutData();
            Bold_CheckoutPaymentBooster_Service_Fastlane::clearGatewayData();
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::CRIT);
        }
    }
}
