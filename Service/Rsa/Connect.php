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

    /** Bold error code when RSA has never been registered (first-time setup). */
    const CODE_RSA_NOT_CONFIGURED = '02-89';

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
        $previousSharedSecret = $config->getSharedSecret($websiteId);
        $callbackUrl = self::getRestCallbackUrl($websiteId);
        // Keep the new secret in memory until Bold confirms PATCH/POST and GET verification —
        // if registration fails, Magento must retain the previous secret so retries stay consistent.
        $sharedSecret = self::generateSharedSecret();
        $body = [
            'url' => $callbackUrl,
            'shared_secret' => $sharedSecret,
        ];

        // PATCH updates RSA in place (Adobe Commerce pattern). Avoids DELETE-before-POST,
        // which left Bold without RSA when POST failed (shared-secret drift).
        $result = Bold_CheckoutPaymentBooster_Service_BoldClient::patch(self::URL, $websiteId, $body);
        if (self::isRsaNotConfigured($result)) {
            $result = Bold_CheckoutPaymentBooster_Service_BoldClient::post(self::URL, $websiteId, $body);
        }
        if (!self::isRegistrationSuccess($result)) {
            $message = self::getRegistrationErrorMessage($result);
            Mage::throwException(
                $message
                    ? 'RSA registration failed: ' . $message . ' Inbound payment webhooks will not work until you save again or use Re-sync RSA.'
                    : 'RSA registration failed. Inbound payment webhooks will not work until you save again or use Re-sync RSA.'
            );
        }

        $remoteConfig = self::fetchRsaConfig($websiteId);
        if (!self::rsaConfigMatches($body, $remoteConfig)) {
            if ($previousSharedSecret) {
                self::attemptRestorePreviousRsaConfig($websiteId, $previousSharedSecret, $callbackUrl);
            }

            Mage::throwException(self::getVerificationFailureMessage((bool)$previousSharedSecret));
        }

        $config->setSharedSecret($sharedSecret, $websiteId);
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
     * Fetch RSA config from Bold (GET checkout/shop/{shopId}/rsa_config).
     *
     * @param int $websiteId
     * @return array|null Keys: url, shared_secret
     */
    public static function fetchRsaConfig($websiteId)
    {
        $result = Bold_CheckoutPaymentBooster_Service_BoldClient::get(self::URL, $websiteId);

        return self::extractRsaConfigFromResponse($result);
    }

    /**
     * @param stdClass|null $result
     * @return array|null Keys: url, shared_secret
     */
    public static function extractRsaConfigFromResponse($result)
    {
        if (!$result || !is_object($result)) {
            return null;
        }

        if (isset($result->errors) && is_array($result->errors) && count($result->errors) > 0) {
            return null;
        }

        if (isset($result->error)) {
            return null;
        }

        $data = isset($result->data) ? $result->data : $result;
        if (!is_object($data) || !isset($data->url, $data->shared_secret)) {
            return null;
        }

        return [
            'url' => (string)$data->url,
            'shared_secret' => (string)$data->shared_secret,
        ];
    }

    /**
     * @param array|null $expected Keys: url, shared_secret
     * @param array|null $remote Keys: url, shared_secret
     * @return bool
     */
    public static function rsaConfigMatches(array $expected, $remote)
    {
        if (!is_array($remote)
            || !isset($expected['url'], $expected['shared_secret'], $remote['url'], $remote['shared_secret'])
        ) {
            return false;
        }

        return (string)$expected['shared_secret'] === (string)$remote['shared_secret']
            && self::normalizeRsaUrl($expected['url']) === self::normalizeRsaUrl($remote['url']);
    }

    /**
     * @param string $url
     * @return string
     */
    public static function normalizeRsaUrl($url)
    {
        return rtrim((string)$url, '/');
    }

    /**
     * @param bool $hadPreviousSecret
     * @return string
     */
    public static function getVerificationFailureMessage($hadPreviousSecret)
    {
        if ($hadPreviousSecret) {
            return 'RSA registration verification failed: Bold did not confirm the new shared secret. '
                . 'Your previous RSA configuration was restored on Bold. '
                . 'Inbound payment webhooks were not changed. Try Save again or use Re-sync RSA.';
        }

        return 'RSA registration verification failed: Bold did not confirm the new shared secret. '
            . 'Inbound payment webhooks will not work until you save again or use Re-sync RSA.';
    }

    /**
     * @param int $websiteId
     * @param string $previousSharedSecret
     * @param string $callbackUrl
     * @return void
     */
    private static function attemptRestorePreviousRsaConfig($websiteId, $previousSharedSecret, $callbackUrl)
    {
        $body = [
            'url' => $callbackUrl,
            'shared_secret' => $previousSharedSecret,
        ];
        $result = Bold_CheckoutPaymentBooster_Service_BoldClient::patch(self::URL, $websiteId, $body);
        if (self::isRegistrationSuccess($result)) {
            Mage::log(
                'RSA rolled back to previous shared secret after verification mismatch',
                Zend_Log::WARN,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
            );
            return;
        }

        Mage::log(
            'RSA rollback failed after verification mismatch: ' . self::getRegistrationErrorMessage($result),
            Zend_Log::ERR,
            Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
        );
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
     * Bold returns 02-89 when RSA has not been registered yet; POST is required once.
     *
     * @param stdClass|null $result
     * @return bool
     */
    public static function isRsaNotConfigured($result)
    {
        if (!$result || !is_object($result) || !isset($result->errors[0])) {
            return false;
        }

        $error = $result->errors[0];
        if (is_object($error) && isset($error->code)) {
            return (string)$error->code === self::CODE_RSA_NOT_CONFIGURED;
        }

        if (is_array($error) && isset($error['code'])) {
            return (string)$error['code'] === self::CODE_RSA_NOT_CONFIGURED;
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
