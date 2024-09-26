<?php

/**
 * Perform requests to Bold Checkout API.
 */
class Bold_CheckoutPaymentBooster_Service_Client
{
    const BOLD_API_VERSION_DATE = '2022-10-14';

    /**
     * Perform GET HTTP request.
     *
     * @param string $url
     * @param int $websiteId
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function get($url, $websiteId)
    {
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        $headers = self::getHeaders($websiteId);
        $url = self::getUrl($websiteId, $shopId, $url);
        return json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'GET',
                $url,
                $websiteId,
                $headers
            )
        );
    }

    /**
     * Perform POST HTTP request.
     *
     * @param string $url
     * @param int $websiteId
     * @param array|null $body
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function post($url, $websiteId, array $body = null)
    {
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        $headers = self::getHeaders($websiteId);
        $url = self::getUrl($websiteId, $shopId, $url);
        return json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'POST',
                $url,
                $websiteId,
                $headers,
                $body ? json_encode($body) : ''
            )
        );
    }

    /**
     * Perform PUT HTTP request.
     *
     * @param string $url
     * @param int $websiteId
     * @param array|null $body
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function put($url, $websiteId, array $body = null)
    {
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        $headers = self::getHeaders($websiteId);
        $url = self::getUrl($websiteId, $shopId, $url);
        return json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'PUT',
                $url,
                $websiteId,
                $headers,
                $body ? json_encode($body) : ''
            )
        );
    }

    /**
     * Perform PATCH HTTP request.
     *
     * @param string $url
     * @param int $websiteId
     * @param array|null $body
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function patch($url, $websiteId, array $body = null)
    {
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        $headers = self::getHeaders($websiteId);
        $url = self::getUrl($websiteId, $shopId, $url);
        return json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'PATCH',
                $url,
                $websiteId,
                $headers,
                $body ? json_encode($body) : ''
            )
        );
    }

    /**
     * Perform DELETE HTTP request.
     *
     * @param string $url
     * @param int $websiteId
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function delete($url, $websiteId)
    {
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        $headers = self::getHeaders($websiteId);
        $url = self::getUrl($websiteId, $shopId, $url);
        return json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'DELETE',
                $url,
                $websiteId,
                $headers
            )
        );
    }

    /**
     * @param int $websiteId
     * @return string[]
     */
    private static function getHeaders($websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        return [
            'Authorization: Bearer ' . $config->getApiToken($websiteId),
            'Content-Type: application/json',
            'Bold-API-Version-Date:' . self::BOLD_API_VERSION_DATE,
        ];
    }

    /**
     * @param int $websiteId
     * @param string $shopId
     * @param string $url
     * @return string
     */
    private static function getUrl($websiteId, $shopId, $url)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        return $config->getApiUrl($websiteId)
            . '/'
            . ltrim(str_replace('{{shopId}}', $shopId, $url), '/');
    }
}
