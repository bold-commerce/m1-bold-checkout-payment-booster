<?php

class Bold_CheckoutPaymentBooster_Service_Rsa_Connect
{
    const URL = 'checkout/shop/{{shopId}}/rsa_config';
    const ALREADY_CREATED_CODE = '02-89';

    /**
     * Set RSA configuration.
     *
     * @param int $websiteId
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function setRsaConfig($websiteId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $sharedSecret = $config->getSharedSecret($websiteId);
        $body = [
            'url' => Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'rest/V1',
            'shared_secret' => $sharedSecret,
        ];
        $result = Bold_CheckoutPaymentBooster_Service_Client::post(self::URL, $websiteId, $body);
        $code = isset($result->errors[0]->code) ? $result->errors[0]->code : null;
        if (!$code) {
            return;
        }
        if ($code !== self::ALREADY_CREATED_CODE) { // RSA configuration already exists and needs to be updated.
            Mage::throwException($result->errors[0]->message);
        }
        $result = Bold_CheckoutPaymentBooster_Service_Client::patch(self::URL, $websiteId, $body);
        $errorMessage = isset($result->errors[0]->message) ? $result->errors[0]->message : null;
        if ($errorMessage) {
            Mage::throwException($errorMessage);
        }
    }
}
