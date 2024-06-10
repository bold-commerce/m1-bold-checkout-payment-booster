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
     */
    public static function save(array $data)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Order $orderData */
        $orderData = Mage::getModel(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
        foreach ($data as $key => $value) {
            $orderData->setData($key, $value);
        }
        $orderData->save();
    }
}
