<?php

/**
 * Registers Magento as Bold's RSA (remote state authority) callback target.
 *
 * The shared secret generated here is used by Bold to sign inbound payment
 * webhooks; Magento verifies those signatures in Model/Router::authorize().
 */
class Bold_CheckoutPaymentBooster_Service_Rsa_Connect
{
    const URL = 'checkout/shop/{{shopId}}/rsa_config';

    /**
     * Set RSA configuration (legacy entry point).
     *
     * @param int $websiteId
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function setRsaConfig($websiteId)
    {
        self::registerRsaConfig($websiteId, true);
    }

    /**
     * Register RSA configuration with Bold.
     *
     * @param int $websiteId
     * @param bool $force When true, always register even if rotation is not required.
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function registerRsaConfig($websiteId, $force = false)
    {
        // Routine admin saves should not rotate the secret — only first setup,
        // API token change, or an explicit Re-sync should re-register RSA.
        if (!$force && !Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::shouldRotateRsa($websiteId, false)) {
            Mage::log(
                'RSA registration skipped (no rotation trigger)',
                Zend_Log::DEBUG,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
            );
            return;
        }

        self::assertShopIdPresent($websiteId);

        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        // Keep the new secret in memory until Bold confirms POST — if registration
        // fails, Magento must retain the previous secret so retries stay consistent.
        $sharedSecret = self::generateSharedSecret();
        $body = [
            'url' => self::getRestCallbackUrl($websiteId),
            'shared_secret' => $sharedSecret,
        ];

        // POST first. Unconditional DELETE-before-POST left Bold without RSA when
        // POST failed, while Magento still held the old secret (shared-secret drift).
        $result = Bold_CheckoutPaymentBooster_Service_BoldClient::post(self::URL, $websiteId, $body);
        if (self::isRegistrationSuccess($result)) {
            $config->setSharedSecret($sharedSecret, $websiteId);
            return;
        }

        // Some shops require clearing existing RSA before a new secret can be posted.
        if (self::isRetriableRsaConflict($result)) {
            Bold_CheckoutPaymentBooster_Service_BoldClient::delete(self::URL, $websiteId);
            $result = Bold_CheckoutPaymentBooster_Service_BoldClient::post(self::URL, $websiteId, $body);
            if (self::isRegistrationSuccess($result)) {
                $config->setSharedSecret($sharedSecret, $websiteId);
                return;
            }
        }

        $message = self::getRegistrationErrorMessage($result);
        Mage::throwException(
            $message
                ? 'RSA registration failed: ' . $message . ' Inbound payment webhooks will not work until you save again or use Re-sync RSA.'
                : 'RSA registration failed. Inbound payment webhooks will not work until you save again or use Re-sync RSA.'
        );
    }

    /**
     * Build the Magento REST callback URL for RSA registration.
     *
     * @param int $websiteId
     * @return string
     */
    public static function getRestCallbackUrl($websiteId)
    {
        // Use the website being configured, not the admin user's current store scope.
        $defaultStore = Mage::app()->getWebsite($websiteId)->getDefaultStore();

        return $defaultStore->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'rest/V1';
    }

    /**
     * Require a persisted shop_id before RSA calls.
     *
     * @param int $websiteId
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function assertShopIdPresent($websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        // Read persisted shop_id only — ShopInfo::getShopId() would auto-fetch and
        // mask a failed saveShopInfo, producing checkout/shop//rsa_config requests.
        $shopId = $config->getShopId($websiteId);
        if (!$shopId) {
            Mage::throwException(
                'Bold shop ID is missing. Save your API token first so shop info can be retrieved before RSA registration.'
            );
        }
    }

    /**
     * @param stdClass|null $result
     * @return bool
     */
    public static function isRegistrationSuccess($result)
    {
        if (!$result || !is_object($result)) {
            return false;
        }

        if (isset($result->errors) && is_array($result->errors) && count($result->errors) > 0) {
            return false;
        }

        if (isset($result->error)) {
            return false;
        }

        return true;
    }

    /**
     * Bold may reject POST when RSA already exists; DELETE + POST is allowed once.
     *
     * @param stdClass|null $result
     * @return bool
     */
    public static function isRetriableRsaConflict($result)
    {
        $message = strtolower(self::getRegistrationErrorMessage($result));

        if ($message === '') {
            return false;
        }

        $needles = ['rsa', 'already', 'exist', 'conflict', 'configured'];
        foreach ($needles as $needle) {
            if (strpos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param stdClass|null $result
     * @return string
     */
    public static function getRegistrationErrorMessage($result)
    {
        if (!$result || !is_object($result)) {
            return '';
        }

        if (isset($result->errors[0]->message)) {
            return (string)$result->errors[0]->message;
        }

        if (isset($result->error_description)) {
            return (string)$result->error_description;
        }

        if (isset($result->error->message)) {
            return (string)$result->error->message;
        }

        return '';
    }

    /**
     * Generate shared secret.
     *
     * @return string
     */
    private static function generateSharedSecret()
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 8; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
