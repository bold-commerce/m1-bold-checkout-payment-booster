<?php

/**
 * Platform version rest service.
 */
class Bold_Checkout_Api_Platform_Version
{
    /**
     * Get platform version.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function getVersion(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        $version = (string)Mage::getConfig()->getModuleConfig('Bold_Checkout')->version;
        return Bold_Checkout_Rest::buildResponse($response, json_encode($version));
    }
}
