<?php

/**
 * Register shared secret rest service.
 */
class Bold_Checkout_Api_Platform_RegisterSharedSecret
{
    /**
     * Register shared secret endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function register(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        try {
            $shopId = Bold_Checkout_Service_GetShopIdFromRequest::getShopId($request);
            $websiteId = Bold_Checkout_Service_GetWebsiteIdByShopId::getWebsiteId($shopId);
            $website = Mage::app()->getWebsite($websiteId);
            if ($website->getId() === null) {
                $error = new \stdClass();
                $error->message = Mage::helper('core')->__('Incorrect "%s" Shop Id is provided.', $shopId);
                $error->code = 422;
                $error->type = 'server.validation_error';
                $result = new \stdClass();
                $result->errors = [$error];
                return Bold_Checkout_Rest::buildResponse($response, json_encode($result));
            }
        } catch (\Exception $e) {
            $error = new \stdClass();
            $error->message = $e->getMessage();
            $error->code = 500;
            $error->type = 'server.internal_error';
            $result = new \stdClass();
            $result->errors = [$error];
            return Bold_Checkout_Rest::buildResponse($response, json_encode($result));
        }
        /** @var Bold_Checkout_Model_Config $config */
        $config = Mage::getModel(Bold_Checkout_Model_Config::RESOURCE);
        $payload = json_decode($request->getRawBody());
        $config->saveSharedSecret($websiteId, $payload->shared_secret);
        $result = new \stdClass();
        $result->shop_id = $shopId;
        $result->website_code = $website->getCode();
        $result->website_id = (int)$website->getId();
        $result->module_version = (string)Mage::getConfig()->getModuleConfig('Bold_Checkout')->version;
        $result->errors = [];
        return Bold_Checkout_Rest::buildResponse($response, json_encode($result));
    }
}
