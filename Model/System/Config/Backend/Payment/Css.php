<?php

/**
 * PIGI payment css backend model
 */
class Bold_CheckoutPaymentBooster_Model_System_Config_Backend_Payment_Css extends Mage_Core_Model_Config_Data
{
    /**
     * Unserialize value on load with default fallback
     */
    protected function _afterLoad()
    {
        try {
            $value = $this->getValue()
                ? unserialize($this->getValue())
                : Bold_CheckoutPaymentBooster_Service_PIGI::getDefaultCss();
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::CRIT);
            $value = '';
        }
        $this->setValue($value);
        return $this;
    }

    /**
     * Serialize value before saving
     *
     * @return $this
     */
    protected function _beforeSave()
    {
        $serialized = serialize($this->getValue());
        $this->setValue($serialized);
        return $this;
    }

    /**
     * Get & decrypt old value from configuration
     *
     * @return string
     */
    public function getOldValue()
    {
        return unserialize(parent::getOldValue());
    }
}
