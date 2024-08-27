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
     * @return void
     * @throws Throwable
     */
    public static function saveIsPlatformCapture(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        $extOrderData->setIsPlatformCapture(1);
        $extOrderData->save();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return void
     * @throws Throwable
     */
    public static function resetIsPlatformCapture(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        $extOrderData->setIsPlatformCapture(0);
        $extOrderData->save();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return int
     * @throws Exception
     */
    public static function getIsPlatformCapture(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        return (int)$extOrderData->getIsPlatformCapture();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return void
     * @throws Throwable
     */
    public static function saveIsPlatformRefund(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        $extOrderData->setIsPlatformRefund(1);
        $extOrderData->save();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return void
     * @throws Throwable
     */
    public static function resetIsPlatformRefund(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        $extOrderData->setIsPlatformRefund(0);
        $extOrderData->save();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return int
     * @throws Exception
     */
    public static function getIsPlatformRefund(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        return (int)$extOrderData->getIsPlatformRefund();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return void
     * @throws Throwable
     */
    public static function saveIsPlatformCancel(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        $extOrderData->setIsPlatformCancel(1);
        $extOrderData->save();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return void
     * @throws Throwable
     */
    public static function resetIsPlatformCancel(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        $extOrderData->setIsPlatformCancel(0);
        $extOrderData->save();
    }

    /**
     * Save order uses delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return int
     * @throws Exception
     */
    public static function getIsPlatformCancel(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $extOrderData */
        $extOrderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        $extOrderData->load($order->getEntityId(), Bold_CheckoutPaymentBooster_Model_Order::ORDER_ID);
        return (int)$extOrderData->getIsPlatformCancel();
    }
}
