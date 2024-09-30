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
        Bold_CheckoutPaymentBooster_Service_BoldClient::delete(self::URL, $websiteId);
        $sharedSecret = self::generateSharedSecret();
        $body = [
            'url' => Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'rest/V1',
            'shared_secret' => $sharedSecret,
        ];
        $result = Bold_CheckoutPaymentBooster_Service_BoldClient::post(self::URL, $websiteId, $body);
        $message = isset($result->errors[0]->message) ? $result->errors[0]->message : null;
        if (!$message) {
            $config->setSharedSecret($sharedSecret, $websiteId);
            return;
        }
        Mage::throwException($result->errors[0]->message);
    }

    /**
     * Generate shared secret.
     *
     * @return string
     */
    private static function generateSharedSecret()
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 8; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
