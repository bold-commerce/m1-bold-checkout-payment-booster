<?php

/**
 * Shop identifier retrieve service.
 */
class Bold_CheckoutPaymentBooster_Service_ShopIdentifier
{
    const SHOP_INFO_URI = '/shops/v1/info';

    /**
     * Get Bold shop ID.
     *
     * @param int $websiteId
     * @return string
     * @throws Mage_Core_Exception
     */
    public static function get($websiteId)
    {
        $websiteId = $websiteId ?: (int)Mage::app()->getDefaultStoreView()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $shopIdentifier = $config->getShopId($websiteId);
        if ($shopIdentifier) {
            return $shopIdentifier;
        }
        self::update($websiteId);

        return $config->getShopId($websiteId);
    }

    /**
     * Update Bold shop ID.
     *
     * @param int $websiteId
     * @throws Mage_Core_Exception
     */
    public static function update($websiteId)
    {
        $websiteId = $websiteId ?: (int)Mage::app()->getDefaultStoreView()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $apiToken = $config->getApiToken($websiteId);
        $headers = [
            'Authorization: Bearer ' . $apiToken,
            'Content-Type: application/json',
            'User-Agent:' . Bold_CheckoutPaymentBooster_Service_UserAgent::get(),
            'Bold-API-Version-Date:' . Bold_CheckoutPaymentBooster_Client::BOLD_API_VERSION_DATE,
        ];
        $url = $config->getApiUrl($websiteId) . self::SHOP_INFO_URI;
        $shopInfo = json_decode(
            Bold_CheckoutPaymentBooster_HttpClient::call(
                'GET',
                $url,
                $websiteId,
                null,
                $headers
            )
        );
        if (isset($shopInfo->errors)) {
            $error = current($shopInfo->errors);
            Mage::throwException($error->message);
        }
        $shopIdentifier = $shopInfo->shop_identifier;
        $config->setShopId($shopIdentifier, $websiteId);
    }
}
