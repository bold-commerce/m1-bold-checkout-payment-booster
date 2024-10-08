<?php

/**
 * Bold order data model.
 *
 * @method Bold_CheckoutPaymentBooster_Model_Resource_Order _getResource()
 * @method Bold_CheckoutPaymentBooster_Model_Resource_Order getResource()
 * @method int getEntityId()
 * @method $this setEntityId(int $value)
 * @method int getOrderId()
 * @method $this setOrderId(int $value)
 * @method string getPublicId()
 * @method $this setPublicId(string $value)
 * @method bool getIsCaptureInProgress()
 * @method $this setIsCaptureInProgress(bool $value)
 * @method bool getIsRefundInProgress()
 * @method $this setIsRefundInProgress(bool $value)
 * @method bool getIsCancelInProgress()
 * @method $this setIsCancelInProgress(bool $value)
 */
class Bold_CheckoutPaymentBooster_Model_Order extends Mage_Core_Model_Abstract
{
    const RESOURCE = 'bold_checkout_payment_booster/order';
    const ORDER_ID = 'order_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
    }
}
