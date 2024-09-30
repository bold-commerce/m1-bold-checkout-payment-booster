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
        $quote->collectTotals();
        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
        if (!$publicOrderId) {
            Mage::throwException('There is no public order ID in the checkout session.');
        }
        $apiUri = sprintf(self::HYDRATE_ORDER_URI, $publicOrderId);
        self::getDiscountsAndFees($quote);
        $body = [
            'customer' => self::getCustomer($quote),
            'billing_address' => self::convertQuoteAddress($quote, 'billing'),
            'cart_items' => self::getCartItems($quote),
            'taxes' => self::getTaxes($quote),
            'discounts' => self::$discounts,
            'fees' => self::$fees,
            'totals' => self::getTotals($quote),
        ];
        if (!$quote->isVirtual()) {
            $body['shipping_address'] = self::convertQuoteAddress($quote, 'shipping');
            $body['shipping_line'] = self::getShippingLine($quote);
        }
        $response = Bold_CheckoutPaymentBooster_Service_BoldClient::put(
            $apiUri,
            $quote->getStore()->getWebsiteId(),
            $body
        );
        if (isset($response->errors) || isset($response->error)) {
            // @TODO if this fails for a user error we need to inform them
            $error = '';
            if (isset($response->errors)) {
                $error .= print_r($response->errors, true);
            }
            if (isset($response->error)) {
                $error .= print_r($response->error, true);
            }
            Mage::log($error, Zend_Log::CRIT);
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
        foreach ($quote->getAllVisibleItems() as $item) {
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
        $subTotal = isset($totals['subtotal']['value_excl_tax'])
            ? $totals['subtotal']['value_excl_tax']
            : $totals['subtotal']['value'];
        $shippingTotal = isset($totals['shipping']['value_excl_tax'])
            ? $totals['shipping']['value_excl_tax']
            : $totals['shipping']['value'];
        $grandTotal = isset($totals['grand_total']['value_incl_tax'])
            ? $totals['grand_total']['value_incl_tax']
            : $totals['grand_total']['value'];
        $processedTotals = [
            'sub_total' => self::convertToCents($subTotal),
            'tax_total' => $totals['tax']['value'] ? self::convertToCents($totals['tax']['value']) : 0,
            'discount_total' => self::getDiscountTotal(),
            'shipping_total' => self::convertToCents($shippingTotal),
            'order_total' => self::convertToCents($grandTotal),
        ];
        $calculatedGrandTotal = $processedTotals['sub_total'] + $processedTotals['tax_total']
            + $processedTotals['shipping_total'] - $processedTotals['discount_total'];
        if ($calculatedGrandTotal > $processedTotals['order_total']
            && $calculatedGrandTotal === $processedTotals['order_total'] + $processedTotals['tax_total']) {
            $processedTotals['order_total'] = $calculatedGrandTotal;
        }
        return $processedTotals;
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
