<?php

/**
 * Perform requests to Bold Checkout API.
 */
class Bold_CheckoutPaymentBooster_Service_EpsClient
{
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
        $headers = self::getHeaders($websiteId);
        $path = self::getUrl($websiteId, $path);
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
        $headers = self::getHeaders($websiteId);
        $path = self::getUrl($websiteId, $path);
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
        $headers = self::getHeaders($websiteId);
        $path = self::getUrl($websiteId, $path);
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
        $headers = self::getHeaders($websiteId);
        $path = self::getUrl($websiteId, $path);
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
        $headers = self::getHeaders($websiteId);
        $path = self::getUrl($websiteId, $path);
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
     * @param int $websiteId
     * @return string[]
     */
    private static function getHeaders($websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        return [
            'Authorization: Bearer ' . $config->getEpsToken($websiteId),
            'Content-Type: application/json',
        ];
    }

    /**
     * Build URL for API request.
     *
     * @param int $websiteId
     * @param string $path
     * @return string
     * @throws Mage_Core_Exception
     */
    private static function getUrl($websiteId, $path)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $shopDomain = $config->getShopDomain($websiteId);
        return $config->getEpsUrl($websiteId)
            . '/'
            . ltrim(str_replace('{{shopDomain}}', $shopDomain, $path), '/');
    }
}
