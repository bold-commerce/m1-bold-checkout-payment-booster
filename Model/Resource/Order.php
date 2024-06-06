<?php

/**
 * Bold order data resource model.
 */
class Bold_CheckoutPaymentBooster_Model_Resource_Order extends Mage_Core_Model_Mysql4_Abstract
{
    const ENTITY_ID = 'entity_id';
    const ORDER_ID = 'order_id';
    const PUBLIC_ID = 'public_id';

    /**
     * Initialize resource model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE, self::ENTITY_ID);
    }
}
