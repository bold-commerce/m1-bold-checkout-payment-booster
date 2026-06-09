<?php

/**
 * Orchestrate Bold config save side effects in a safe order.
 *
 * Replaces four independent observers so RSA registration cannot run after a
 * partial failure left shop_id empty, and flows are not provisioned when RSA fails.
 */
class Bold_CheckoutPaymentBooster_Service_Config_SavePipeline
{
    /**
     * @param int $websiteId
     * @param array $options
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function run($websiteId, array $options = array())
    {
        $forceRsa = !empty($options['force_rsa']);

        // Order matters: shop_id must exist before RSA; RSA must succeed before flows.
        Bold_CheckoutPaymentBooster_Service_ShopInfo::saveShopInfo($websiteId);
        self::validateShopDomain($websiteId); // notice only — does not abort
        Bold_CheckoutPaymentBooster_Service_Eps_CorsRegistration::registerWebsiteDomain($websiteId);

        if (self::shouldRotateRsa($websiteId, $forceRsa)) {
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::registerRsaConfig($websiteId, true);
        } else {
            Mage::log(
                'RSA registration skipped (no rotation trigger)',
                Zend_Log::DEBUG,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
            );
        }

        Bold_CheckoutPaymentBooster_Service_Flow::processPaymentBoosterFlow($websiteId);
        Bold_CheckoutPaymentBooster_Service_Flow::processPaymentBoosterPdpFlow($websiteId);
        Bold_CheckoutPaymentBooster_Service_Flow::processPaymentBoosterCartFlow($websiteId);
    }

    /**
     * @param int $websiteId
     * @param bool $force
     * @return bool
     */
    public static function shouldRotateRsa($websiteId, $force = false)
    {
        if ($force) {
            return true;
        }

        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        // First-time setup: no local secret yet.
        if (!$config->getSharedSecret($websiteId)) {
            return true;
        }

        // Set by ApiToken backend when the admin saves a new token this request.
        if (Mage::registry('bold_checkout_api_token_changed_' . $websiteId)) {
            return true;
        }

        // Fallback if registry was cleared but fingerprint still differs.
        $storedFingerprint = $config->getApiTokenFingerprint($websiteId);
        $currentFingerprint = $config->buildApiTokenFingerprint($websiteId);
        if ($currentFingerprint && $storedFingerprint !== $currentFingerprint) {
            return true;
        }

        return false;
    }

    /**
     * Warn when Bold shop domain and Magento base URL hosts do not align.
     *
     * @param int $websiteId
     * @return void
     */
    public static function validateShopDomain($websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $boldDomain = self::normalizeHost($config->getShopDomain($websiteId));
        $defaultStore = Mage::app()->getWebsite($websiteId)->getDefaultStore();
        $magentoHost = self::normalizeHost($defaultStore->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB));

        if ($boldDomain === '' || $magentoHost === '') {
            return;
        }

        // www vs apex mismatch (e.g. lethalperformance.com vs www.lethalperformance.com)
        // does not block save but causes webhook/callback confusion if left unaddressed.
        if (self::hostsMatch($boldDomain, $magentoHost)) {
            return;
        }

        Mage::getSingleton('adminhtml/session')->addNotice(
            Mage::helper('core')->__(
                'Bold shop domain (%s) does not match the Magento base URL host (%s). Ensure www/apex redirects and Bold Account Center use the same canonical domain.',
                $boldDomain,
                $magentoHost
            )
        );
    }

    /**
     * @param string $value
     * @return string
     */
    public static function normalizeHost($value)
    {
        $value = strtolower(trim((string)$value));
        if ($value === '') {
            return '';
        }

        $parts = parse_url(strpos($value, '://') === false ? 'https://' . $value : $value);
        if (!isset($parts['host'])) {
            return rtrim($value, '/');
        }

        return strtolower($parts['host']);
    }

    /**
     * @param string $left
     * @param string $right
     * @return bool
     */
    public static function hostsMatch($left, $right)
    {
        if ($left === $right) {
            return true;
        }

        $stripWww = function ($host) {
            return preg_replace('/^www\./', '', $host);
        };

        return $stripWww($left) === $stripWww($right);
    }
}
