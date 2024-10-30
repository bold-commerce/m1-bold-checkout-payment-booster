<?php

class Bold_CheckoutPaymentBooster_Service_Eps_Cors
{
    const CORS_PATH = 'checkout/shop/{{shopId}}/cors';

    /**
     * Get CORS allow list for specific website.
     *
     * @param int $websiteId
     * @return array
     * @throws Mage_Core_Exception
     */
    public static function getAllowList($websiteId)
    {
        $response = Bold_CheckoutPaymentBooster_Service_BoldClient::get(self::CORS_PATH, $websiteId);
        if (isset($response->error)) {
            Mage::throwException($response->error->message);
        }
        return isset($response->data) ? (array)$response->data : [];
    }

    /**
     * Add Magento domain for specific website to CORS allow list.
     *
     * @param int $websiteId
     * @param string $domain
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function addDomainToCorsAllowList($websiteId, $domain)
    {
        $response = Bold_CheckoutPaymentBooster_Service_BoldClient::post(
            self::CORS_PATH,
            $websiteId,
            ['domain' => rtrim($domain, '/')]
        );
        if (isset($response->error)) {
            Mage::throwException($response->error->message);
        }
    }
}
