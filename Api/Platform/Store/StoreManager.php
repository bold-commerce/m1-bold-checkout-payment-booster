<?php

/**
 * Stores information rest service.
 */
class Bold_Checkout_Api_Platform_Store_StoreManager
{
    private static $configPaths = [
        'locale' => 'general/locale/code',
        'base_currency_code' => 'currency/options/base',
        'default_display_currency' => 'currency/options/default',
        'timezone' => 'general/locale/timezone',
        'weight_unit' => Bold_Checkout_Model_Config::PATH_WEIGHT_UNIT,
    ];

    /**
     * Get specified in request stores information endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function getStoreConfigs(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        $payload = json_decode($request->getRawBody());
        $storeConfigs = [];
        $storeCollection = Mage::getModel('core/store')->getCollection();
        if (isset($payload->store_codes)) {
            $storeCollection->addFieldToFilter('code', ['in' => $payload->store_codes]);
        }
        foreach ($storeCollection->load() as $item) {
            $storeConfigs[] = self::getStoreConfig($item);
        }
        return Bold_Checkout_Rest::buildResponse($response, json_encode($storeConfigs));
    }

    /**
     * Get store information endpoint.
     *
     * @param Mage_Core_Model_Store $store
     * @return stdClass
     * @throws Mage_Core_Exception
     */
    private static function getStoreConfig(Mage_Core_Model_Store $store)
    {
        $storeConfig = new stdClass();
        $storeConfig->id = $store->getId();
        $storeConfig->code = $store->getCode();
        $storeConfig->website_id = $store->getWebsiteId();
        foreach (self::$configPaths as $property => $configPath) {
            $configValue = Mage::getStoreConfig($configPath, $store);
            $storeConfig->$property = $configValue;
        }
        $storeConfig->base_url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, false);
        $storeConfig->secure_base_url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, true);
        $storeConfig->base_link_url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, false);
        $storeConfig->secure_base_link_url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true);
        $storeConfig->base_static_url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, false);
        $storeConfig->secure_base_static_url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true);
        $storeConfig->base_media_url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA, false);
        $storeConfig->secure_base_media_url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA, true);
        return $storeConfig;
    }
}
