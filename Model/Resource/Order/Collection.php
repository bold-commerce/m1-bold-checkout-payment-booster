<?php

/**
 * Bold order mapping collection.
 */
class Bold_CheckoutPaymentBooster_Model_Resource_Order_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
    }
}
