<?php

/**
 * Bold flows service.
 */
class Bold_CheckoutPaymentBooster_Service_Flow
{
    const BOLD = 'Bold three page';
    const FASTLANE = 'paypal_fastlane_3_page';

    private static $flowList = [];

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
        self::$flowList = Bold_CheckoutPaymentBooster_Service_Client::get(
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
        return Bold_CheckoutPaymentBooster_Service_Client::delete(
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
        return Bold_CheckoutPaymentBooster_Service_Client::get(
            '/checkout/shop/{{shopId}}/flows/available',
            $websiteId
        )->data->flows;
    }

    /**
     * Get Bold flow ID for given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     */
    public static function getId(Mage_Sales_Model_Quote $quote)
    {
        $websiteId = $quote->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $flows = self::getList($websiteId);
        $isFastlaneEnabled = $config->isFastlaneEnabled($websiteId);
        if (!$isFastlaneEnabled) {
            return self::getDefaultFlowId($flows);
        }
        foreach ($flows as $flow) {
            if ($flow->flow_id === self::FASTLANE) {
                return self::FASTLANE;
            }
        }
        return self::getDefaultFlowId($flows);
    }

    /**
     * Get default Bold flow ID.
     *
     * @param array $flows
     * @return string|null
     */
    private static function getDefaultFlowId(array $flows)
    {
        $fastlaneFlowId = null;
        foreach ($flows as $flowKey => $flow) {
            if ($flow->flow_id === self::FASTLANE) {
                $fastlaneFlowId = $flow->flow_id;
                unset($flows[$flowKey]);
            }
        }
        // return fastlane flow only if it is the only flow available.
        return $flows[0]->flow_id ?? $fastlaneFlowId;
    }
}
