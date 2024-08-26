<?php

class Bold_CheckoutPaymentBooster_Service_Rsa_Connect
{
    const URL = 'checkout/shop/{{shopId}}/rsa_config';

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
        Bold_CheckoutPaymentBooster_Service_Client::delete(self::URL, $websiteId);
        $sharedSecret = $config->getSharedSecret($websiteId);
        $body = [
            'url' => Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'rest/V1',
            'shared_secret' => $sharedSecret,
        ];
        $result = Bold_CheckoutPaymentBooster_Service_Client::post(self::URL, $websiteId, $body);
        $message = isset($result->errors[0]->message) ? $result->errors[0]->message : null;
        if (!$message) {
            return;
        }
        Mage::throwException($result->errors[0]->message);
    }
}
