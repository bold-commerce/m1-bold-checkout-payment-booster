<?php

/**
 * Countries information rest service.
 */
class Bold_Checkout_Api_Platform_Directory_Countries
{
    /**
     * Get specified in request country information endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function getCountryInfo(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        preg_match('/countries\/(.*)/', $request->getRequestUri(), $cartIdMatches);
        $countryId = isset($cartIdMatches[1]) ? $cartIdMatches[1] : null;
        /** @var Mage_Directory_Model_Country $country */
        $country = Mage::getModel('directory/country')->loadByCode($countryId);
        if (!$country->getId()) {
            $error = new stdClass();
            $error->message = 'There is no country with id: ' . $countryId;
            $error->code = 422;
            $error->type = 'server.validation_error';
            return Bold_Checkout_Rest::buildResponse($response, json_encode(
                    [
                        'errors' => [$error],
                    ]
                )
            );
        }
        $store = Mage::app()->getStore();
        $storeLocale = Mage::getStoreConfig(
            'general/locale/code',
            $store
        );
        /** @var Mage_Directory_Helper_Data $directoryHelper */
        $directoryHelper = Mage::helper('directory');
        $regions = Mage::helper('core')->jsonDecode($directoryHelper->getRegionJsonByStore());
        $countryInfo = self::setCountryInfo($country, $regions, $storeLocale);
        return Bold_Checkout_Rest::buildResponse($response, json_encode($countryInfo));
    }

    /**
     * Get allowed countries information.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     * @throws Mage_Core_Model_Store_Exception
     */
    public static function getCountriesInfo(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        $countriesInfo = [];
        $store = Mage::app()->getStore();
        $storeLocale = Mage::getStoreConfig(
            'general/locale/code',
            $store
        );
        /** @var Mage_Directory_Helper_Data $directoryHelper */
        $directoryHelper = Mage::helper('directory');
        $countries = $directoryHelper->getCountryCollection();
        $regions = Mage::helper('core')->jsonDecode($directoryHelper->getRegionJsonByStore());
        foreach ($countries as $data) {
            $countryInfo = self::setCountryInfo($data, $regions, $storeLocale);
            $countriesInfo[] = $countryInfo;
        }
        return Bold_Checkout_Rest::buildResponse($response, json_encode($countriesInfo));
    }

    /**
     * Build country information response object.
     *
     * @param Mage_Directory_Model_Country $country
     * @param array $regions
     * @param string $storeLocale
     * @return \stdClass
     */
    private static function setCountryInfo($country, array $regions, $storeLocale)
    {
        $countryId = $country->getCountryId();
        $countryInfo = new \stdClass();
        $countryInfo->id = $countryId;
        $countryInfo->two_letter_abbreviation = $country->getData('iso2_code');
        $countryInfo->three_letter_abbreviation = $country->getData('iso3_code');
        /** @var Mage_Core_Model_Locale $locale */
        $locale = Mage::getSingleton('core/locale');
        $locale->setLocale($storeLocale);
        $countryInfo->full_name_locale = $locale->getCountryTranslation($country->getId());
        $locale = Mage::getModel('core/locale');
        $locale->setLocale('en_US');
        $countryInfo->full_name_english = $locale->getCountryTranslation($country->getId());
        if (array_key_exists($countryId, $regions)) {
            $regionsInfo = [];
            foreach ($regions[$countryId] as $id => $regionData) {
                $regionInfo = new \stdClass();
                $regionInfo->id = (string)$id;
                $regionInfo->code = $regionData['code'];
                $regionInfo->name = $regionData['name'];
                $regionsInfo[] = $regionInfo;
            }
            $countryInfo->available_regions = $regionsInfo;
        }
        return $countryInfo;
    }
}
