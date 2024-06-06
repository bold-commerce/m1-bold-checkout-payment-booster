<?php

/**
 * Bold configuration service.
 */
class Bold_CheckoutPaymentBooster_Model_Config
{
    const RESOURCE = 'bold_checkout_payment_booster/config';
    const LOG_FILE_NAME = 'bold_checkout_payment_booster.log';

    // Main settings
    const PATH_IS_PAYMENT_BOOSTER_ENABLED = 'checkout/bold_checkout_payment_booster/is_payment_booster_enabled';
    const PATH_IS_FASTLANE_ENABLED = 'checkout/bold_checkout_payment_booster/is_fastlane_enabled';
    const PATH_API_TOKEN = 'checkout/bold_checkout_payment_booster/api_token';
    const PATH_SHOP_ID = 'checkout/bold_checkout_payment_booster/shop_id';

    // Advanced settings
    const PATH_API_URL = 'checkout/bold_checkout_payment_booster_advanced/api_url';
    const PATH_WEIGHT_CONVERSION_RATE = 'checkout/bold_checkout_payment_booster_advanced/weight_conversion_rate';
    const PATH_IS_LOG_ENABLED = 'checkout/bold_checkout_payment_booster_advanced/is_log_enabled';

    /**
     * Check if the Payment Booster is enabled.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isPaymentBoosterEnabled($websiteId)
    {
        return (bool)Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_IS_PAYMENT_BOOSTER_ENABLED)
            && Mage::helper('core')->isModuleOutputEnabled('Bold_CheckoutPaymentBooster');
    }

    /**
     * Check if the PayPal Fastlane is enabled.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isFastlaneEnabled($websiteId)
    {
        return (bool)Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_IS_FASTLANE_ENABLED)
            && Mage::helper('core')->isModuleOutputEnabled('Bold_CheckoutPaymentBooster');
    }

    /**
     * Get API token (decrypted).
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getApiToken($websiteId)
    {
        $encryptedToken = Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_API_TOKEN);

        return Mage::helper('core')->decrypt($encryptedToken);
    }

    /**
     * Retrieve Bold shop identifier.
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getShopId($websiteId)
    {
        return Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_SHOP_ID);
    }

    /**
     * Save Bold shop identifier.
     *
     * @param string|null $shopIdentifier
     * @param int $websiteId
     * @return void
     */
    public function setShopId($shopIdentifier, $websiteId)
    {
        Mage::getConfig()->saveConfig(self::PATH_SHOP_ID, $shopIdentifier, 'websites', $websiteId);
        Mage::getConfig()->cleanCache();
    }

    /**
     * Retrieve Bold API URL.
     *
     * @param int $websiteId
     * @return string
     */
    public function getApiUrl($websiteId)
    {
        return rtrim(Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_API_URL), '/');
    }

    /**
     * Retrieve weight unit conversion rate to grams.
     *
     * @param int $websiteId
     * @return float|int
     */
    public function getWeightConversionRate($websiteId)
    {
        return (float)Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_WEIGHT_CONVERSION_RATE) ?: 1000;
    }

    /**
     * Check if requests logging is enabled
     *
     * @param int $websiteId
     * @return bool
     */
    public function isLogEnabled($websiteId)
    {
        return (bool)Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_IS_LOG_ENABLED);
    }
}
