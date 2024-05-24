<?php

/**
 * Bold configuration service.
 */
class Bold_CheckoutPaymentBooster_Model_Config
{
    const RESOURCE = 'bold_checkout_payment_booster/config';
    const PATH_IS_PAYMENT_BOOSTER_ENABLED = 'checkout/bold_checkout_payment_booster/is_payment_booster_enabled';
    const PATH_IS_FASTLANE_ENABLED = 'checkout/bold_checkout_payment_booster/is_fastlane_enabled';
    const PATH_API_TOKEN = 'checkout/bold_checkout_payment_booster/api_token';
    const PATH_SHOP_ID = 'checkout/bold_checkout_payment_booster/shop_id';

    const PATH_API_URL = 'checkout/bold_checkout_payment_booster_advanced/api_url';
    const PATH_IS_LOG_ENABLED = 'checkout/bold_checkout_payment_booster_advanced/is_log_enabled';

    const LOG_FILE_NAME = 'bold_checkout_payment_booster.log';

    /**
     * Check if the Payment Booster is enabled.
     *
     * @param int $websiteId
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function isPaymentBoosterEnabled(int $websiteId)
    {
        return (bool)Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_IS_PAYMENT_BOOSTER_ENABLED)
            && Mage::helper('core')->isModuleOutputEnabled('Bold_CheckoutPaymentBooster');
    }

    /**
     * Check if the PayPal Fastlane is enabled.
     *
     * @param int $websiteId
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function isFastlaneEnabled(int $websiteId)
    {
        return (bool)Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_IS_FASTLANE_ENABLED)
            && Mage::helper('core')->isModuleOutputEnabled('Bold_CheckoutPaymentBooster');
    }

    /**
     * Get API token (decrypted).
     *
     * @param int $websiteId
     * @return string|null
     * @throws Mage_Core_Exception
     */
    public function getApiToken(int $websiteId)
    {
        $encryptedToken = Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_API_TOKEN);

        return Mage::helper('core')->decrypt($encryptedToken);
    }

    /**
     * Retrieve Bold shop identifier.
     *
     * @param int $websiteId
     * @return string|null
     * @throws Mage_Core_Exception
     */
    public function getShopId(int $websiteId)
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
    public function setShopId(?string $shopIdentifier, int $websiteId)
    {
        Mage::getConfig()->saveConfig(self::PATH_SHOP_ID, $shopIdentifier, 'websites', $websiteId);
        Mage::getConfig()->cleanCache();
    }

    /**
     * Retrieve Bold API URL.
     *
     * @param int $websiteId
     * @return string
     * @throws Mage_Core_Exception
     */
    public function getApiUrl(int $websiteId)
    {
        return rtrim(Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_API_URL), '/');
    }

    /**
     * Check if requests logging is enabled
     *
     * @param int $websiteId
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function isLogEnabled(int $websiteId)
    {
        return (bool)Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_IS_LOG_ENABLED);
    }
}
