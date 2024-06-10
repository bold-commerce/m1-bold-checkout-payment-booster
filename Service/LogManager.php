<?php

/**
 * Log manager service.
 */
class Bold_CheckoutPaymentBooster_Service_LogManager
{
    const LOG_FILE_NAME = 'bold_checkout_payment_booster.log';

    /**
     * Write the message to the log file.
     *
     * @param string $message
     * @param string|null $websiteId
     * @param int $level
     * @return void
     */
    public static function log($message, $websiteId = null, $level = Zend_Log::ERR)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        if ($websiteId === null) {
            $websiteId = Mage::app()->getStore()->getWebsiteId();
        }

        if ($config->isLogEnabled($websiteId)) {
            Mage::log(
                $message,
                $level,
                self::LOG_FILE_NAME,
                true
            );
        }
    }
}
