<?php

/**
 * Bold order data service.
 */
class Bold_CheckoutPaymentBooster_Service_Order_Data
{
    /**
     * Save bold order data.
     *
     * @param array $data
     * @return void
     * @throws Throwable
     */
    public static function save(array $data)
    {
        try {
            /** @var Bold_CheckoutPaymentBooster_Model_Order $orderData */
            $orderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
            foreach ($data as $key => $value) {
                $orderData->setData($key, $value);
            }
            $orderData->save();
        } catch (Throwable $exception) {
            Mage::log($exception->getMessage(), Zend_Log::CRIT);
        }
    }
}
