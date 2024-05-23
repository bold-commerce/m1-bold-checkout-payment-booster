<?php

/**
 * Perform requests to Bold Checkout API.
 */
class Bold_CheckoutPaymentBooster_Client
{
    const BOLD_API_VERSION_DATE = "2022-10-14";

    /**
     * Perform HTTP request.
     *
     * @param string $method
     * @param string $url
     * @param int $websiteId
     * @param string|null $data
     * @return string
     * @throws Mage_Core_Exception
     */
    public static function call($method, $url, $websiteId, $data = null)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopIdentifier::get($websiteId);

        $headers = [
            'Authorization: Bearer ' . $config->getApiToken($websiteId),
            'Content-Type: application/json',
            'User-Agent:' . Bold_CheckoutPaymentBooster_Service_UserAgent::get(),
            'Bold-API-Version-Date:' . self::BOLD_API_VERSION_DATE,
        ];

        $url = $config->getApiUrl($websiteId)
            . '/'
            . ltrim(str_replace('{{shopId}}', $shopId, $url), '/');

        return Bold_CheckoutPaymentBooster_HttpClient::call($method, $url, $websiteId, $data, $headers);
    }
}
