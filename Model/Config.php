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
    const PATH_SHARED_SECRET = 'checkout/bold_checkout_payment_booster/shared_secret';
    const PATH_SHOP_ID = 'checkout/bold_checkout_payment_booster/shop_id';
    const PATH_SHOP_DOMAIN = 'checkout/bold_checkout_payment_booster/shop_domain';

    // Advanced settings
    const PATH_API_URL = 'checkout/bold_checkout_payment_booster_advanced/api_url';
    const PATH_EPS_URL = 'checkout/bold_checkout_payment_booster_advanced/eps_url';
    const PATH_EPS_STATIC_URL = 'checkout/bold_checkout_payment_booster_advanced/eps_static_url';
    const PATH_WEIGHT_CONVERSION_RATE = 'checkout/bold_checkout_payment_booster_advanced/weight_conversion_rate';
    const PATH_FASTLANE_ADDRESS_CONTAINER_STYLES = 'checkout/bold_checkout_payment_booster_advanced/fastlane_address_container_styles';
    const PATH_FASTLANE_EMAIL_CONTAINER_STYLES = 'checkout/bold_checkout_payment_booster_advanced/fastlane_email_container_styles';
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
     * Retrieve EPS URL.
     *
     * @param int $websiteId
     * @return string
     */
    public function getEpsUrl($websiteId)
    {
        return rtrim(Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_EPS_URL), '/');
    }

    /**
     * Retrieve EPS static URL.
     *
     * @param int $websiteId
     * @return string
     */
    public function getEpsStaticUrl($websiteId)
    {
        return rtrim(Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_EPS_STATIC_URL), '/');
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
     * Retrieve Fastlane address container styles.
     *
     * @param int $websiteId
     * @return string
     */
    public function getFastlaneAddressContainerStyles($websiteId)
    {
        return Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_FASTLANE_ADDRESS_CONTAINER_STYLES) ?: '';
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

    /**
     * Retrieve shared secret (decrypted).
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getSharedSecret($websiteId)
    {
        $encryptedSharedSecret = Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_SHARED_SECRET);
        return Mage::helper('core')->decrypt($encryptedSharedSecret);
    }

    /**
     * Save generated shared secret.
     *
     * @param string|null $sharedSecret
     * @param int $websiteId
     * @return void
     */
    public function setSharedSecret($sharedSecret, $websiteId)
    {
        $sharedSecret = Mage::helper('core')->encrypt($sharedSecret);
        Mage::getConfig()->saveConfig(self::PATH_SHARED_SECRET, $sharedSecret, 'websites', $websiteId);
        Mage::getConfig()->cleanCache();
    }

    /**
     * Save Bold shop domain.
     *
     * @param string $shopDomain
     * @param int $websiteId
     * @return void
     */
    public function setShopDomain($shopDomain, $websiteId)
    {
        Mage::getConfig()->saveConfig(self::PATH_SHOP_DOMAIN, $shopDomain, 'websites', $websiteId);
        Mage::getConfig()->cleanCache();
    }

    /**
     * Retrieve saved Bold shop domain.
     *
     * @param $websiteId
     * @return string
     * @throws Mage_Core_Exception
     */
    public function getShopDomain($websiteId)
    {
        return (string)Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_SHOP_DOMAIN);
    }

    public function getFastlaneWatermarkContainerStyles($websiteId)
    {
        return Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_FASTLANE_EMAIL_CONTAINER_STYLES) ?: '';
    }

    /**
     * Retrieve the fastlane styles URL
     *
     * @param $websiteId
     * @return string
     * @throws Mage_Core_Exception
     */
    public function getFastlaneStylesUrl($websiteId)
    {
        $shopDomain = $this->getShopDomain($websiteId);
        return rtrim(Mage::app()->getWebsite($websiteId)->getConfig(self::PATH_EPS_STATIC_URL), '/')
            . '/'
            . $shopDomain
            . '/custom-style.css';
    }
}
