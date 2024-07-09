<?php

/**
 * Bold flows service.
 */
class Bold_CheckoutPaymentBooster_Service_Flow
{
    /**
     * Get Bold flows.
     *
     * @param int $websiteId
     * @return array
     */
    public static function getList($websiteId)
    {
        return Bold_CheckoutPaymentBooster_Service_Client::get(
            '/checkout/shop/{{shopId}}/flows',
            $websiteId
        )->data->flows;
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

        if ($config->isFastlaneEnabled($websiteId)
            && !$quote->getCustomer()->getId()
        ) {
            return 'Bold three page';  //todo: check if api should be used instead.
        }

        return 'Bold three page'; //todo: check if api should be used instead.
    }
}
