<?php

/**
 * Bold quote data resource model.
 */
class Bold_CheckoutPaymentBooster_Model_Resource_Quote extends Mage_Core_Model_Mysql4_Abstract
{
    const ENTITY_ID = 'entity_id';
    const QUOTE_ID = 'quote_id';
    const PUBLIC_ID = 'public_id';

    /**
     * Initialize resource model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Bold_CheckoutPaymentBooster_Model_Quote::RESOURCE, self::ENTITY_ID);
    }
}
