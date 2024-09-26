<?php

/**
 * Bold order data service.
 */
class Bold_CheckoutPaymentBooster_Service_Order_Data
{
    public static function getOrderBoldData(Mage_Sales_Model_Order $order)
    {
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        return $extOrderData;
    }
    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @param bool $value
     * @return void
     * @throws Throwable
     */
    public static function setIsCaptureInProgress(Mage_Sales_Model_Order $order, $value)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        $extOrderData->setIsCancelInProgress((int)$value);
        $extOrderData->save();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return int
     * @throws Exception
     */
    public static function getIsCaptureInProgress(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        return (bool)$extOrderData->getIsCaptureInProgress();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @param bool $value
     * @return void
     * @throws Throwable
     */
    public static function setIsRefundInProgress(Mage_Sales_Model_Order $order, $value)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        $extOrderData->setIsRefundInProgress((int)$value);
        $extOrderData->save();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     * @throws Exception
     */
    public static function isRefundInProgress(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        return (bool)$extOrderData->getIsRefundInProgress();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @param bool $value
     * @return void
     * @throws Throwable
     */
    public static function setIsCancelInProgress(Mage_Sales_Model_Order $order, $value)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        $extOrderData->setIsCancelInProgress((int)$value);
        $extOrderData->save();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     * @throws Exception
     */
    public static function getIsCancelInProgress(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        return (bool)$extOrderData->getIsCancelInProgress();
    }
}
