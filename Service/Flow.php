<?php

/**
 * Bold flows service.
 */
class Bold_CheckoutPaymentBooster_Service_Flow
{
    const DEFAULT_FLOW_ID = 'bold-booster-m1';
    const PDP_FLOW_ID = 'bold-booster-pdp-m1';
    const STAGING_CONFIGURATION_GROUP = '/consumers/checkout-staging/configuration_group/{{shopDomain}}';
    const CONFIGURATION_GROUP = '/consumers/checkout/configuration_group/{{shopDomain}}';

    private static $flowList = [];

    private static $epsConfig = null;

    /**
     * Get Bold flows.
     *
     * @param int $websiteId
     * @return array
     */
    public static function getList($websiteId)
    {
        if (self::$flowList) {
            return self::$flowList;
        }
        self::$flowList = Bold_CheckoutPaymentBooster_Service_BoldClient::get(
            '/checkout/shop/{{shopId}}/flows',
            $websiteId
        )->data->flows;
        return self::$flowList;
    }

    /**
     * Disable given Bold flow.
     *
     * @param int $websiteId
     * @param string $flowId
     * @return stdClass
     */
    public static function disableFlow($websiteId, $flowId)
    {
        return Bold_CheckoutPaymentBooster_Service_BoldClient::delete(
            '/checkout/shop/{{shopId}}/flows/' . $flowId,
            $websiteId
        );
    }

    /**
     * Get available Bold flows.
     *
     * @param int $websiteId
     * @return array
     */
    public static function getAvailable($websiteId)
    {
        return Bold_CheckoutPaymentBooster_Service_BoldClient::get(
            '/checkout/shop/{{shopId}}/flows/available',
            $websiteId
        )->data->flows;
    }

    /**
     * Create custom Bold flow.
     *
     * @param int $websiteId
     * @param array $flowData
     * @return stdClass
     */
    public static function createFlow($websiteId, $flowData)
    {
        return Bold_CheckoutPaymentBooster_Service_BoldClient::post(
            '/checkout/shop/{{shopId}}/flows',
            $websiteId,
            $flowData
        );
    }

    /**
     * Update custom Bold flow.
     *
     * @param int $websiteId
     * @param string $flowId
     * @param array $flowData
     * @return stdClass
     */
    public static function updateFlow($websiteId, $flowId, array $flowData)
    {
        return Bold_CheckoutPaymentBooster_Service_BoldClient::patch(
            '/checkout/shop/{{shopId}}/flows/' . $flowId,
            $websiteId,
            $flowData
        );
    }

    /**
     * Retrieve Bold flow settings by flow ID.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $flowId
     * @return null
     */
    public static function getFlowSettingsByFlowId(Mage_Sales_Model_Quote $quote, $flowId)
    {
        $websiteId = $quote->getStore()->getWebsiteId();
        $flowList = self::getList($websiteId);
        foreach ($flowList as $flow) {
            if ($flow->flow_id === $flowId) {
                return $flow->flow_settings;
            }
        }
        return null;
    }

    /**
     * Create|update Payment Booster flow for given website.
     *
     * @param int $websiteId
     * @return void
     */
    public static function processPaymentBoosterFlow($websiteId)
    {
        $list = self::getList($websiteId);
        foreach ($list as $flow) {
            if ($flow->flow_id === self::DEFAULT_FLOW_ID) {
                return;
            }
        }
        self::createFlow(
            $websiteId,
            [
                'flow_id' => self::DEFAULT_FLOW_ID,
                'flow_name' => 'Bold Booster for PayPal',
                'flow_type' => 'custom',
            ]
        );
    }

    /**
     * Create|update Payment Booster PDP flow for given website.
     *
     * @param int $websiteId
     * @return void
     */
    public static function processPaymentBoosterPdpFlow($websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $isExpressPayEnabledOnProductPage = $config->isExpressPayEnabledOnProductPage($websiteId);

        if (!$isExpressPayEnabledOnProductPage) {
            return;
        }

        $flows = self::getList($websiteId);

        foreach ($flows as $flow) {
            if ($flow->flow_id === self::PDP_FLOW_ID) {
                return;
            }
        }

        self::createFlow(
            $websiteId,
            [
                'flow_id' => self::PDP_FLOW_ID,
                'flow_name' => 'Bold Booster for PayPal on Product Detail Page',
                'flow_type' => 'custom',
            ]
        );
    }

    /**
     * Create|update Fastlane flow for given website.
     *
     * @param int $websiteId
     * @return void
     */
    public static function processFastlaneFlow($websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $isFastlaneEnabled = $config->isFastlaneEnabled($websiteId);
        if (!$isFastlaneEnabled) {
            return;
        }
        $list = self::getList($websiteId);
        foreach ($list as $flow) {
            if ($flow->flow_id === self::FASTLANE_FLOW_ID) {
                return;
            }
        }
        self::createFlow(
            $websiteId,
            [
                'flow_id' => self::FASTLANE_FLOW_ID,
                'flow_name' => 'Bold Booster for PayPal Fastlane',
                'flow_type' => 'custom',
            ]
        );
    }

    /**
     * Get fastlane styles.
     *
     * @param int $websiteId
     * @return string
     */
    public static function getFastlaneStyles($websiteId)
    {
        if (self::$epsConfig) {
            return self::extractStylesFromEpsConfig();
        }
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        self::$epsConfig = Bold_CheckoutPaymentBooster_Service_Client_Http::call(
            'GET',
            $config->getFastlaneStylesUrl($websiteId),
            $websiteId,
            []
        );

        return self::extractStylesFromEpsConfig();
    }

    /**
     * Extract styles from EPS configuration.
     *
     * @return string
     */
    private static function extractStylesFromEpsConfig()
    {
        if (isset(self::$epsConfig)) {
            try {
                $styles = json_decode(self::$epsConfig);
                return isset($styles->fastlane->styles) ? $styles->fastlane->styles : null;
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }
}
