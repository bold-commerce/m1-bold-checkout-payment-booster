<?php

/**
 * Bold order hydrate service.
 */
class Bold_CheckoutPaymentBooster_Service_Order_Hydrate
{
    const HYDRATE_ORDER_URI = '/checkout_sidekick/{{shopId}}/order/%s';
    // list of system totals which need to be skipped during preparing discounts and fees list
    const SYSTEM_TOTALS = [
        'subtotal',
        'tax',
        'discount',
        'shipping',
        'grand_total',
    ];
    private static $requiredFields = [
        'city',
        'firstname',
        'street',
        'lastname',
        'telephone',
        'postcode',
        'region',
        'region_id',
    ];

    private static $discounts = [];

    private static $fees = [];

    /**
     * Hydrate Bold order.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function hydrate(Mage_Sales_Model_Quote $quote)
    {
        $boldCheckoutData = Bold_CheckoutPaymentBooster_Service_Bold::getBoldCheckoutData();
        $publicOrderId = $boldCheckoutData->public_order_id;
        if (!$publicOrderId) {
            Mage::throwException('There is no public order ID in the checkout session.');
        }
        $apiUri = sprintf(self::HYDRATE_ORDER_URI, $publicOrderId);
        self::getDiscountsAndFees($quote);
        $body = [
            'customer' => self::getCustomer($quote),
            'billing_address' => self::convertQuoteAddress($quote, 'billing'),
            'shipping_address' => self::convertQuoteAddress($quote, 'shipping'),
            'cart_items' => self::getCartItems($quote),
            'taxes' => self::getTaxes($quote),
            'discounts' => self::$discounts,
            'fees' => self::$fees,
            'shipping_line' => self::getShippingLine($quote),
            'totals' => self::getTotals($quote),
        ];
        $response = Bold_CheckoutPaymentBooster_Service_Client::put(
            $apiUri,
            $quote->getStore()->getWebsiteId(),
            json_encode($body)
        );
        Mage::log(json_encode($body), Zend_Log::DEBUG, 'hydrate.log', true);
        if (isset($response->errors) || isset($response->error)) {
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
            'platform_id' => $quote->getCustomerId() ? (string)$quote->getCustomerId() : null,
            'first_name' => $quote->getCustomerFirstname(),
            'last_name' => $quote->getCustomerLastname(),
            'email_address' => $quote->getCustomerEmail(),
        ];
    }

    /**
     * Convert quote billing|shipping address to appropriate format.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     */
    private static function convertQuoteAddress(Mage_Sales_Model_Quote $quote, $type)
    {
        $billingAddress = $quote->getbillingAddress();
        $shippingAddress = $quote->getShippingAddress();
        $address = $type === 'billing' ? $billingAddress : $shippingAddress;
        foreach (self::$requiredFields as $field) {
            if (!$address->getData($field)) {
                $address = $billingAddress->getData($field) ? $billingAddress : $shippingAddress;
                break;
            }
        }
        $countryIsoCode = Mage::getModel('directory/country')
            ->load($address->getCountryId())
            ->getIso2Code();
        $countryName = Mage::app()
            ->getLocale()
            ->getCountryTranslation($countryIsoCode);

        return [
            'first_name' => $address->getFirstname(),
            'last_name' => $address->getLastname(),
            'business_name' => $address->getCompany() ? $address->getCompany() : '',
            'phone_number' => $address->getTelephone(),
            'address_line_1' => $address->getStreet1(),
            'address_line_2' => $address->getStreet2(),
            'city' => $address->getCity(),
            'province' => $address->getRegion(),
            'province_code' => $address->getRegionCode(),
            'country' => $countryName,
            'country_code' => $address->getCountryId(),
            'postal_code' => $address->getPostcode(),
        ];
    }

    /**
     * Retrieve cart items.
     *
     * @return array
     */
    private static function getCartItems(Mage_Sales_Model_Quote $quote)
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
    private static function getTaxes(Mage_Sales_Model_Quote $quote)
    {
        $totals = $quote->getTotals();
        $taxInfo = isset($totals['tax']['full_info']) ? $totals['tax']['full_info'] : [];
        $taxes = [];
        foreach ($taxInfo as $tax) {
            $taxes[] = [
                'name' => $tax['id'],
                'value' => self::convertToCents($tax['base_amount']),
            ];
        }

        return $taxes;
    }

    /**
     * Retrieve shipping line.
     *
     * @return array
     */
    private static function getShippingLine(Mage_Sales_Model_Quote $quote)
    {
        if ($quote->isVirtual()) {
            return [];
        }

        return [
            'rate_name' => $quote->getShippingAddress()->getShippingDescription() ?: '',
            'cost' => self::convertToCents($quote->getShippingAddress()->getShippingAmount()),
        ];
    }

    /**
     * Retrieve discounts and fees.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return void
     */
    private static function getDiscountsAndFees(Mage_Sales_Model_Quote $quote)
    {
        $totals = $quote->getTotals();

        if (isset($totals['discount'])) {
            self::$discounts[] = [
                'line_text' => isset($totals['discount']['title']) ? $totals['discount']['title'] : '',
                'value' => abs(self::convertToCents($totals['discount']['value'])),
            ];
        }

        foreach ($totals as $total) {
            if (in_array($total['code'], self::SYSTEM_TOTALS) || !$total['value']) {
                continue;
            }

            $title = isset($total['title'])
                ? $total['title']
                : ucfirst(str_replace('_', ' ', $total['code']));

            if ($total['value'] > 0) {
                self::$fees[] = [
                    'description' => $title,
                    'value' => self::convertToCents($total['value']),
                ];
            } else {
                self::$discounts[] = [
                    'line_text' => $title,
                    'value' => abs(self::convertToCents($total['value'])),
                ];
            }
        }
    }

    /**
     * Retrieve totals.
     *
     * @return array
     */
    private static function getTotals(Mage_Sales_Model_Quote $quote)
    {
        $totals = $quote->getTotals();

        return [
            'sub_total' => self::convertToCents($totals['subtotal']['value']),
            'tax_total' => $totals['tax']['value'] ? self::convertToCents($totals['tax']['value']) : 0,
            'discount_total' => self::getDiscountTotal(),
            'shipping_total' => $totals['shipping']['value'] ? self::convertToCents($totals['shipping']['value']) : 0,
            'order_total' => self::convertToCents($totals['grand_total']['value']),
        ];
    }

    /**
     * Retrieve discount total.
     *
     * @return int
     */
    private static function getDiscountTotal()
    {
        return !empty(self::$discounts)
            ? array_sum(array_column(self::$discounts, 'value'))
            : 0;
    }

    /**
     * Convert an amount to cents.
     *
     * @param float|string $amount
     * @return integer
     */
    private static function convertToCents($amount)
    {
        return (int)round(floatval($amount) * 100);
    }
}
