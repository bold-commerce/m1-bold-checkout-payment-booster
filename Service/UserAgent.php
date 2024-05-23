<?php

/**
 * User Agent header builder.
 */
class Bold_CheckoutPaymentBooster_Service_UserAgent
{
    const HEADER_PREFIX = 'Bold-Platform-Connector-M1:';

    /**
     * Retrieve user-agent header value.
     *
     * @return string
     * @throws Mage_Core_Exception
     */
    public static function get()
    {
        $moduleConfig = Mage::app()
            ->getConfig()
            ->getModuleConfig('Bold_CheckoutPaymentBooster')
            ->asArray();

        if (!isset($moduleConfig['version'])) {
            Mage::throwException(
                Mage::helper('core')->__('Bold_CheckoutPaymentBooster module is not installed')
            );
        }

        return self::HEADER_PREFIX . $moduleConfig['version'];
    }
}
