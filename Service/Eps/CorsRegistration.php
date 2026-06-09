<?php

/**
 * Register Magento storefront domains with Bold EPS CORS allow list.
 *
 * Extracted from ConfigObserver so SavePipeline can run CORS in a fixed order.
 */
class Bold_CheckoutPaymentBooster_Service_Eps_CorsRegistration
{
    /**
     * @param int $websiteId
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function registerWebsiteDomain($websiteId)
    {
        $defaultStore = Mage::app()->getWebsite($websiteId)->getDefaultStore();
        $magentoUrl = $defaultStore->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $domainList = Bold_CheckoutPaymentBooster_Service_Eps_Cors::getAllowList((int)$websiteId);
        foreach ($domainList as $domain) {
            if ($domain->domain === rtrim($magentoUrl, '/')) {
                return;
            }
        }

        Bold_CheckoutPaymentBooster_Service_Eps_Cors::addDomainToCorsAllowList(
            (int)$websiteId,
            (string)$magentoUrl
        );
    }
}
