<?php

/**
 * Shop identifier retrieve service.
 */
class Bold_CheckoutPaymentBooster_Service_ShopInfo
{
    const SHOP_INFO_URI = '/shops/v1/info';

    /**
     * Get Bold shop ID.
     *
     * @param int $websiteId
     * @return string
     * @throws Mage_Core_Exception
     */
    public static function getShopId($websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $shopIdentifier = $config->getShopId($websiteId);
        if ($shopIdentifier) {
            return $shopIdentifier;
        }
        self::saveShopInfo($websiteId);

        return $config->getShopId($websiteId);
    }

    /**
     * Set Bold shop ID.
     *
     * @param int $websiteId
     * @throws Mage_Core_Exception
     */
    public static function saveShopInfo($websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $config->setShopId(null, $websiteId);
        $apiToken = $config->getApiToken($websiteId);
        $headers = [
            'Authorization: Bearer ' . $apiToken,
            'Content-Type: application/json',
            'Bold-API-Version-Date:' . Bold_CheckoutPaymentBooster_Service_BoldClient::BOLD_API_VERSION_DATE,
        ];
        $url = $config->getApiUrl($websiteId) . self::SHOP_INFO_URI;

        $shopInfo = json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                'GET',
                $url,
                $websiteId,
                $headers
            )
        );
        if (!$shopInfo) {
            Mage::throwException('Failed to get shop info. Please contact Bold support.');
        }
        if (isset($shopInfo->errors)) {
            $error = current($shopInfo->errors);
            Mage::throwException($error->message);
        }
        if (isset($shopInfo->error)) {
            Mage::throwException($shopInfo->error_description);
        }

        $shopIdentifier = $shopInfo->shop_identifier;
        $config->setShopId($shopIdentifier, $websiteId);
        $config->setShopDomain($shopInfo->shop_domain, $websiteId);
    }
}
