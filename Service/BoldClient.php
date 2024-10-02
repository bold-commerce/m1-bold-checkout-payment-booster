<?php

/**
 * Perform requests to Bold Checkout API.
 */
class Bold_CheckoutPaymentBooster_Service_BoldClient
{
    const BOLD_API_VERSION_DATE = '2022-10-14';

    /**
     * Perform GET HTTP request.
     *
     * @param string $path
     * @param int $websiteId
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function get($path, $websiteId)
    {
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        $headers = self::getHeaders($websiteId);
        $path = self::getUrl($websiteId, $shopId, $path);
        return json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'GET',
                $path,
                $websiteId,
                $headers
            )
        );
    }

    /**
     * Perform POST HTTP request.
     *
     * @param string $path
     * @param int $websiteId
     * @param array|null $body
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function post($path, $websiteId, array $body = null)
    {
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        $headers = self::getHeaders($websiteId);
        $path = self::getUrl($websiteId, $shopId, $path);
        return json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'POST',
                $path,
                $websiteId,
                $headers,
                $body ? json_encode($body) : ''
            )
        );
    }

    /**
     * Perform PUT HTTP request.
     *
     * @param string $path
     * @param int $websiteId
     * @param array|null $body
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function put($path, $websiteId, array $body = null)
    {
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        $headers = self::getHeaders($websiteId);
        $path = self::getUrl($websiteId, $shopId, $path);
        return json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'PUT',
                $path,
                $websiteId,
                $headers,
                $body ? json_encode($body) : ''
            )
        );
    }

    /**
     * Perform PATCH HTTP request.
     *
     * @param string $path
     * @param int $websiteId
     * @param array|null $body
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function patch($path, $websiteId, array $body = null)
    {
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        $headers = self::getHeaders($websiteId);
        $path = self::getUrl($websiteId, $shopId, $path);
        return json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'PATCH',
                $path,
                $websiteId,
                $headers,
                $body ? json_encode($body) : ''
            )
        );
    }

    /**
     * Perform DELETE HTTP request.
     *
     * @param string $path
     * @param int $websiteId
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    public static function delete($path, $websiteId)
    {
        $shopId = Bold_CheckoutPaymentBooster_Service_ShopInfo::getShopId($websiteId);
        $headers = self::getHeaders($websiteId);
        $path = self::getUrl($websiteId, $shopId, $path);
        return json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'DELETE',
                $path,
                $websiteId,
                $headers
            )
        );
    }

    /**
     * Build request headers.
     *
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
     * Build URL for API request.
     *
     * @param int $websiteId
     * @param string $shopId
     * @param string $path
     * @return string
     */
    private static function getUrl($websiteId, $shopId, $path)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        return $config->getApiUrl($websiteId)
            . '/'
            . ltrim(str_replace('{{shopId}}', $shopId, $path), '/');
    }
}
