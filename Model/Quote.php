<?php

/**
 * Bold quote data model.
 *
 * @method Bold_CheckoutPaymentBooster_Model_Resource_Quote _getResource()
 * @method int getEntityId()
 * @method $this setEntityId(int $value)
 * @method int getQuoteId()
 * @method $this setQuoteId(int $value)
 * @method string getPublicId()
 * @method $this setPublicId(string $value)
 * @method array getFlowSettings()
 * @method $this setFlowSettings(array $value)
 */
class Bold_CheckoutPaymentBooster_Model_Quote extends Mage_Core_Model_Abstract
{
    const RESOURCE = 'bold_checkout_payment_booster/quote';
    const QUOTE_ID = 'quote_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Bold_CheckoutPaymentBooster_Model_Quote::RESOURCE);
    }
}
