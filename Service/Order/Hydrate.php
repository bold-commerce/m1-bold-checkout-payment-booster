<?php

/**
 * Bold order hydrate service.
 */
class Bold_CheckoutPaymentBooster_Service_Order_Hydrate
{
    const HYDRATE_ORDER_URI = '/checkout_sidekick/{{shopId}}/order/%s';

    /**
     * Hydrate Bold order.
     */
    public static function hydrate(Mage_Sales_Model_Quote $quote, stdClass $boldCheckoutData)
    {
        $publicOrderId = $boldCheckoutData->public_order_id;
        $apiUri = sprintf(self::HYDRATE_ORDER_URI, $publicOrderId);

        $body = [
            "customer" => self::getCustomer($quote),
            "billing_address" => self::convertBillingAddress($quote->getBillingAddress()),
            "cart_items" => self::getCartItems($quote),
            "taxes" => self::getTaxes($quote),
            "discounts" => self::getDiscounts($quote),
            "fees" => self::getFees($quote),
            "shipping_line" => self::getShippingLine($quote),
            "totals" => self::getTotals($quote)
        ];

        $response = json_decode(
            Bold_CheckoutPaymentBooster_Client::call(
                'PUT',
                $apiUri,
                $quote->getStore()->getWebsiteId(),
                json_encode($body)
            )
        );

        if (isset($response->errors)) {
            Mage::throwException(
                'Cannot hydrate order, Quote ID: ' . $quote->getId() . ', Public Order ID: ' . $publicOrderId
            );
        }
    }

    /**
     * Retrieve customer data.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     */
    private static function getCustomer(Mage_Sales_Model_Quote $quote)
    {
        return [
            'platform_id' => $quote->getCustomerId() ?? null,
            'first_name' => $quote->getCustomerFirstname(),
            'last_name' => $quote->getCustomerLastname(),
            "email_address" => $quote->getCustomerEmail(),
        ];
    }

    /**
     * Convert billing address to appropriate format.
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return array
     */
    private static function convertBillingAddress(Mage_Sales_Model_Quote_Address $address)
    {
        $countryIsoCode = Mage::getModel('directory/country')
            ->load($address->getCountryId())
            ->getIso2Code();
        $countryName = Mage::app()
            ->getLocale()
            ->getCountryTranslation($countryIsoCode);

        return [
            "first_name" => $address->getFirstname(),
            "last_name" => $address->getLastname(),
            "business_name" => $address->getCompany(),
            "phone_number" => $address->getTelephone(),
            "address_line_1" => $address->getStreet1(),
            "address_line_2" => $address->getStreet2(),
            "city" => $address->getCity(),
            "province" => $address->getRegion(),
            "province_code" => $address->getRegionCode(),
            "country" => $countryName,
            "country_code" => $address->getCountryId(),
            "postal_code" => $address->getPostcode(),
        ];
    }

    /**
     * Retrieve cart items.
     *
     * @return array
     */
    public static function getCartItems(Mage_Sales_Model_Quote $quote)
    {
        $items = [];
        foreach ($quote->getAllItems() as $item) {
            $items[] = Bold_CheckoutPaymentBooster_Service_Quote_Item::extract($item);
        }

        return $items;
    }

    /**
     * Retrieve taxes.
     *
     * @return array
     */
    public static function getTaxes(Mage_Sales_Model_Quote $quote)
    {
        return [];
    }

    /**
     * @return array
     */
    public static function getDiscounts(Mage_Sales_Model_Quote $quote)
    {
        return [];
    }

    /**
     * Retrieve fees.
     *
     * @return array
     */
    public static function getFees(Mage_Sales_Model_Quote $quote)
    {
        return [];
    }

    /**
     * Retrieve shipping line.
     *
     * @return array
     */
    public static function getShippingLine(Mage_Sales_Model_Quote $quote)
    {
        return [];
    }

    /**
     * Retrieve totals.
     *
     * @return array
     */
    public static function getTotals(Mage_Sales_Model_Quote $quote)
    {
        return [];
    }
}
