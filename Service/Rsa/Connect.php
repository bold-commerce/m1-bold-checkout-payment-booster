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

    /** Bold endpoint that confirms rsa_config was applied before Magento persists locally. */
    const CHECK_SHARED_URL = 'checkout/shop/{{shopId}}/rsa_config/checkShared';

    /** Bold error code when RSA has never been registered (first-time setup). */
    const CODE_RSA_NOT_CONFIGURED = '02-89';

    /** Delay between checkShared retries while Bold propagates the new secret. */
    const CHECK_SHARED_RETRY_DELAY_US = 1000000;

    /** Max checkShared attempts (initial + retries ≈ 3s propagation window). */
    const CHECK_SHARED_MAX_ATTEMPTS = 4;

    const REGISTRY_ROTATION_LOCK_PREFIX = 'bold_checkout_rsa_rotating_';

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
        // API token change, or an explicit Rotate Shared Key should re-register RSA.
        if (!$force && !Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::shouldRotateRsa($websiteId, false)) {
            Mage::log(
                'RSA registration skipped (no rotation trigger)',
                Zend_Log::DEBUG,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
            );
            return;
        }

        if (!self::acquireRotationLock($websiteId)) {
            Mage::throwException(
                'RSA rotation is already in progress for this website. Please wait a moment and try again.'
            );
        }

        try {
            self::executeRsaRegistration($websiteId);
        } finally {
            self::releaseRotationLock($websiteId);
        }
    }

    /**
     * @param int $websiteId
     * @return void
     * @throws Mage_Core_Exception
     */
    private static function executeRsaRegistration($websiteId)
    {
        self::assertShopIdPresent($websiteId);

        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $previousSharedSecret = $config->getSharedSecret($websiteId);
        $callbackUrl = self::getRestCallbackUrl($websiteId);
        // Keep the new secret in memory until Bold confirms PATCH/POST and checkShared
        // succeeds — Magento must not persist locally until both sides agree.
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
                    ? 'RSA registration failed: ' . $message . ' Inbound payment webhooks will not work until you save again or use Rotate Shared Key.'
                    : 'RSA registration failed. Inbound payment webhooks will not work until you save again or use Rotate Shared Key.'
            );
        }

        if ($config->isCheckSharedEnabled($websiteId)
            && !self::verifySharedSecretWithBoldCheckShared($websiteId, $sharedSecret, $callbackUrl)
        ) {
            if ($previousSharedSecret) {
                self::attemptRestorePreviousRsaConfig($websiteId, $previousSharedSecret, $callbackUrl);
            }

            Mage::throwException(self::getVerificationFailureMessage((bool)$previousSharedSecret));
        }

        $config->setSharedSecret($sharedSecret, $websiteId);
    }

    /**
     * Ask Bold checkout to confirm rsa_config matches the proposed url + shared secret.
     *
     * @param int $websiteId
     * @param string $sharedSecret
     * @param string $callbackUrl
     * @return bool
     */
    public static function verifySharedSecretWithBoldCheckShared($websiteId, $sharedSecret, $callbackUrl)
    {
        $body = [
            'url' => $callbackUrl,
            'shared_secret' => $sharedSecret,
        ];

        $lastResult = null;
        for ($attempt = 1; $attempt <= self::CHECK_SHARED_MAX_ATTEMPTS; $attempt++) {
            if ($attempt > 1) {
                usleep(self::CHECK_SHARED_RETRY_DELAY_US);
            }

            $lastResult = Bold_CheckoutPaymentBooster_Service_BoldClient::post(
                self::CHECK_SHARED_URL,
                $websiteId,
                $body
            );

            if (self::isCheckSharedSuccess($lastResult)) {
                Mage::log(
                    'RSA shared secret verified via Bold checkShared for website '
                    . $websiteId
                    . ($attempt > 1 ? ' (attempt ' . $attempt . ')' : '')
                    . '.',
                    Zend_Log::INFO,
                    Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
                );

                return true;
            }
        }

        self::logCheckSharedFailure($websiteId, $lastResult);

        return false;
    }

    /**
     * @param stdClass|null $result
     * @return bool
     */
    public static function isCheckSharedSuccess($result)
    {
        if (!$result || !is_object($result)) {
            return false;
        }

        if (!isset($result->data)) {
            return false;
        }

        $data = $result->data;
        if ($data === true || $data === 1 || $data === '1') {
            return true;
        }

        if ($data === false || $data === 0 || $data === '0') {
            return false;
        }

        return (int)$data === 1;
    }

    /**
     * Serialize RSA rotations per website across concurrent admin requests.
     *
     * @param int $websiteId
     * @return bool
     */
    public static function acquireRotationLock($websiteId)
    {
        $registryKey = self::REGISTRY_ROTATION_LOCK_PREFIX . (int)$websiteId;
        if (Mage::registry($registryKey)) {
            return false;
        }

        Mage::register($registryKey, true);

        try {
            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
            $lockName = 'bold_checkout_rsa_rotate_' . (int)$websiteId;
            $result = $connection->fetchOne(
                'SELECT GET_LOCK(?, 10)',
                array($lockName)
            );
            if ((int)$result !== 1) {
                Mage::unregister($registryKey);
                return false;
            }
        } catch (Exception $exception) {
            Mage::unregister($registryKey);
            throw $exception;
        }

        return true;
    }

    /**
     * @param int $websiteId
     * @return void
     */
    public static function releaseRotationLock($websiteId)
    {
        $registryKey = self::REGISTRY_ROTATION_LOCK_PREFIX . (int)$websiteId;

        try {
            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
            $lockName = 'bold_checkout_rsa_rotate_' . (int)$websiteId;
            $connection->query('SELECT RELEASE_LOCK(?)', array($lockName));
        } catch (Exception $exception) {
            Mage::logException($exception);
        }

        if (Mage::registry($registryKey)) {
            Mage::unregister($registryKey);
        }
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
        $baseUrl = $defaultStore->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true);
        if (!$baseUrl || $baseUrl === 'http://' || $baseUrl === 'https://') {
            $baseUrl = $defaultStore->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, false);
        }

        return rtrim($baseUrl, '/') . '/rest/V1';
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
     * @param bool $hadPreviousSecret
     * @return string
     */
    public static function getVerificationFailureMessage($hadPreviousSecret)
    {
        if ($hadPreviousSecret) {
            return 'RSA registration verification failed: Bold could not confirm the new shared secret. '
                . 'Your previous RSA configuration was restored on Bold. '
                . 'Inbound payment webhooks were not changed. '
                . 'Check var/log/bold_checkout_payment_booster.log and try Rotate Shared Key again.';
        }

        return 'RSA registration verification failed: Bold could not confirm the new shared secret. '
            . 'Inbound payment webhooks will not work until you save again or use Rotate Shared Key. '
            . 'Check var/log/bold_checkout_payment_booster.log.';
    }

    /**
     * @param int $websiteId
     * @param stdClass|null $result
     * @return void
     */
    private static function logCheckSharedFailure($websiteId, $result)
    {
        $detail = '';
        if ($result && is_object($result) && isset($result->data)) {
            $detail = ' checkShared=' . json_encode($result->data);
        } elseif ($result && is_object($result)) {
            $detail = ' response=' . json_encode($result);
        }

        Mage::log(
            'RSA checkShared verification failed for website ' . $websiteId . '.' . $detail,
            Zend_Log::WARN,
            Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
        );
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
                'RSA rolled back to previous shared secret after checkShared verification failure',
                Zend_Log::WARN,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
            );
            return;
        }

        Mage::log(
            'RSA rollback failed after checkShared verification failure: ' . self::getRegistrationErrorMessage($result),
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
