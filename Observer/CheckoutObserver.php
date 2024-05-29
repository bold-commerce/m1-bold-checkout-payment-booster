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
     * @throws Throwable
     */
    public function beforeCheckout(Varien_Event_Observer $event)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('checkout/cart')->getQuote();
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->setBoldCheckoutData(null);

        try {
            if (!Bold_CheckoutPaymentBooster_Service_Order_Init::isAllowed($quote)) {
                return;
            }
            $flowId = Bold_CheckoutPaymentBooster_Service_FlowId::get($quote);
            $checkoutData = Bold_CheckoutPaymentBooster_Service_Order_Init::init($quote, $flowId);
            $checkoutSession->setBoldCheckoutData($checkoutData);
            $this->setOrderData($quote->getId(), $checkoutData);
        } catch (Exception $exception) {
            Mage::log($exception->getMessage(), Zend_Log::CRIT);
        }
    }

    /**
     * Fill Bold order with additional data for virtual quote.
     *
     * @param Varien_Event_Observer $event
     * @return void
     */
    public function afterSaveBilling(Varien_Event_Observer $event)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('checkout/cart')->getQuote();
        if ($quote->isVirtual()) {
            $this->hydrateOrder($quote);
        }
    }

    /**
     * Fill Bold order with additional data.
     *
     * @param Varien_Event_Observer $event
     * @return void
     */
    public function afterSaveShippingMethod(Varien_Event_Observer $event)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('checkout/cart')->getQuote();
        $this->hydrateOrder($quote);
    }

    /**
     * Set Bold order data.
     *
     * @param int $quoteId
     * @param stdClass $checkoutData
     * @return void
     * @throws Throwable
     */
    private function setOrderData(int $quoteId, stdClass $checkoutData)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $orderData */
        $orderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $orderData->load($quoteId, Bold_CheckoutPaymentBooster_Model_Resource_Order::QUOTE_ID);

        if ($orderData->getEntityId()) {
            return;
        }

        $data = [
            Bold_CheckoutPaymentBooster_Model_Resource_Order::QUOTE_ID => $quoteId,
            Bold_CheckoutPaymentBooster_Model_Resource_Order::ORDER_ID => null,
            Bold_CheckoutPaymentBooster_Model_Resource_Order::PUBLIC_ID => $checkoutData->public_order_id,
        ];

        Bold_CheckoutPaymentBooster_Service_Order_Data::save($data);
    }

    /**
     * Hydrate Bold order.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return void
     */
    private function hydrateOrder(Mage_Sales_Model_Quote $quote): void
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $boldCheckoutData = $checkoutSession->getBoldCheckoutData();

        try {
            Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote, $boldCheckoutData->public_order_id);
        } catch (Exception $exception) {
            Mage::log($exception->getMessage(), Zend_Log::CRIT);
        }
    }
}
