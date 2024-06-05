<?php

/**
 * Bold order data model.
 *
 * @method Bold_CheckoutPaymentBooster_Model_Resource_Order _getResource()
 * @method Bold_CheckoutPaymentBooster_Model_Resource_Order getResource()
 * @method int getEntityId()
 * @method $this setEntityId(int $value)
 * @method int getQuoteId()
 * @method $this setQuoteId(int $value)
 * @method int getOrderId()
 * @method $this setOrderId(int $value)
 * @method string getPublicId()
 * @method $this setPublicId(string $value)
 */
class Bold_CheckoutPaymentBooster_Model_Order extends Mage_Core_Model_Abstract
{
    const RESOURCE = 'bold_checkout_payment_booster/order';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
    }
}
