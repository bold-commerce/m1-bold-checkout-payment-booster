<?php

/**
 * Translates Magento Quote data into Bold Checkout Express Pay Order data
 */
class Bold_CheckoutPaymentBooster_Service_ExpressPay_QuoteConverter
{
    /**
     * @var bool
     */
    private $areTotalsCollected = false;

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param string $gatewayId
     * @return array
     * @phpstan-return array<string, string|array<string, array<string, string|float|array<string, string|float>>>>
     */
    public function convertFullQuote(Mage_Sales_Model_Quote $quote, $gatewayId)
    {
        return array_merge_recursive(
            $this->convertGatewayIdentifier($gatewayId),
            $this->convertLocale($quote),
            $this->convertCustomer($quote),
            $this->convertShippingInformation($quote),
            $this->convertQuoteItems($quote),
            $this->convertTotal($quote),
            $this->convertTaxes($quote),
            $this->convertDiscount($quote)
        );
    }

    /**
     * @param string $gatewayId
     * @return array
     * @phpstan-return array<string, string>
     */
    public function convertGatewayIdentifier($gatewayId)
    {
        return [
            'gateway_id' => $gatewayId
        ];
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     * @phpstan-return array<string, array<string, string>>
     */
    public function convertLocale(Mage_Sales_Model_Quote $quote)
    {
        $locale = Mage::getStoreConfig('general/locale/code', $quote->getStoreId());

        return [
            'order_data' => [
                'locale' => str_replace('_', '-', $locale)
            ]
        ];
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     * @phpstan-return array<string, array<string, array<string, string>>>
     */
    public function convertCustomer(Mage_Sales_Model_Quote $quote)
    {
        $billingAddress = $quote->getBillingAddress();

        if ($billingAddress->getId() === null) {
            return [];
        }

        return [
            'order_data' => [
                'customer' => [
                    'first_name' => $billingAddress->getFirstname() ?: '',
                    'last_name' => $billingAddress->getLastname() ?: '',
                    'email' => $billingAddress->getEmail() ?: ''
                ]
            ]
        ];
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param bool $includeAddress
     * @return array
     * @phpstan-return array<string, array<string, array<array<string, array<string, string>|string>|string>>>
     */
    public function convertShippingInformation(Mage_Sales_Model_Quote $quote, $includeAddress = true)
    {
        $shippingAddress = $quote->getShippingAddress();

        if ($quote->getIsVirtual()) {
            return [];
        }

        $convertedQuote = [
            'order_data' => [
                'shipping_address' => [],
                'selected_shipping_option' => [],
                'shipping_options' => []
            ]
        ];
        $currencyCode = $quote->getQuoteCurrencyCode() ?: '';

        if ($includeAddress) {
            $streetAddress = $shippingAddress->getStreet();
            $convertedQuote['order_data']['shipping_address'] = [
                'address_line_1' => isset($streetAddress[0]) ? $streetAddress[0] : '',
                'address_line_2' => isset($streetAddress[1]) ? $streetAddress[1] : '',
                'city' => $shippingAddress->getCity() ?: '',
                'country_code' => $shippingAddress->getCountryId() ?: '',
                'postal_code' => $shippingAddress->getPostcode() ?: '',
                'state' => $shippingAddress->getRegion() ?: ''
            ];
        }

        if ($shippingAddress->hasShippingMethod()) {
            $convertedQuote['order_data']['selected_shipping_option'] = [
                'id' => $shippingAddress->getShippingMethod(),
                'label' => $shippingAddress->getShippingDescription(),
                'type' => 'SHIPPING',
                'amount' => [
                    'currency_code' => $currencyCode,
                    'value' => number_format((float)$shippingAddress->getShippingAmount(), 2)
                ],
            ];
        }

        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();

        $usedRateCodes = [];
        /** @var Mage_Sales_Model_Quote_Address_Rate[] $shippingRates */
        $shippingRates = array_filter(
            $shippingAddress->getShippingRatesCollection()->getItems(),
            static function (Mage_Sales_Model_Quote_Address_Rate $rate) use (&$usedRateCodes) {
                if (in_array($rate->getCode(), $usedRateCodes)) {
                    return false;
                }

                $usedRateCodes[] = $rate->getCode();

                return true;
            }
        ); // Work-around for Magento bug causing duplicated shipping rates

        if (count($shippingRates) > 0) {
            $convertedQuote['order_data']['shipping_options'] = array_map(
                static function (Mage_Sales_Model_Quote_Address_Rate $rate) use ($currencyCode) {
                    return [
                        'id' => $rate->getCode(),
                        'label' => trim("{$rate->getCarrierTitle()} - {$rate->getMethodTitle()}", ' -'),
                        'type' => 'SHIPPING',
                        'amount' => [
                            'currency_code' => $currencyCode,
                            'value' => number_format((float)$rate->getPrice(), 2)
                        ]
                    ];
                },
                array_values($shippingRates)
            );
        }

        return $convertedQuote;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     * @phpstan-return array<string, array<string, array<array<string, array<string, string>|bool|int|string>|string>>>
     */
    public function convertQuoteItems(Mage_Sales_Model_Quote $quote)
    {
        $quoteItems = $quote->getAllItems();

        if (count($quoteItems) === 0) {
            return [];
        }

        $currencyCode = $quote->getQuoteCurrencyCode() ?: '';
        $convertedQuote = [
            'order_data' => [
                'items' => array_map(
                    static function (Mage_Sales_Model_Quote_Item $quoteItem) use ($currencyCode) {
                        return [
                            'name' => $quoteItem->getName(),
                            'sku' => $quoteItem->getSku(),
                            'unit_amount' => [
                                'currency_code' => $currencyCode,
                                'value' => number_format((float)$quoteItem->getPrice(), 2)
                            ],
                            'quantity' => (int)(ceil($quoteItem->getQty()) ?: $quoteItem->getQty()),
                            'is_shipping_required' => !in_array(
                                $quoteItem->getProductType(),
                                [
                                    'virtual',
                                    'downloadable'
                                ],
                                true
                            ),
                        ];
                    },
                    $quoteItems
                ),
                'item_total' => [
                    'currency_code' => $currencyCode,
                    'value' => number_format(
                        array_sum(
                            array_map(
                                static function (Mage_Sales_Model_Quote_Item $quoteItem) {
                                    return $quoteItem->getPrice() * $quoteItem->getQty();
                                },
                                $quoteItems
                            )
                        ),
                        2
                    )
                ]
            ]
        ];

        $this->convertCustomTotals($quote, $convertedQuote);

        return $convertedQuote;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     * @phpstan-return array<string, array<string, array<string, string>>>
     */
    public function convertTotal(Mage_Sales_Model_Quote $quote)
    {
        $currencyCode = $quote->getQuoteCurrencyCode() ?: '';

        if (!$this->areTotalsCollected) {
            $quote->collectTotals();

            $this->areTotalsCollected = true;
        }

        return [
            'order_data' => [
                'amount' => [
                    'currency_code' => $currencyCode,
                    'value' => number_format((float)$quote->getGrandTotal(), 2)
                ]
            ]
        ];
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     * @phpstan-return array<string, array<string, array<string, string>>>
     */
    public function convertTaxes(Mage_Sales_Model_Quote $quote)
    {
        $currencyCode = $quote->getQuoteCurrencyCode() ?: '';
        $convertedQuote = [
            'order_data' => [
                'tax_total' => [
                    'currency_code' => $currencyCode,
                    'value' => ''
                ]
            ]
        ];

        if ($quote->getIsVirtual()) {
            /** @var Mage_Sales_Model_Quote_Item[] $quoteItems */
            $quoteItems = $quote->getAllItems();
            $convertedQuote['order_data']['tax_total']['value'] = number_format(
                array_sum(
                    array_map(
                        static function (Mage_Sales_Model_Quote_Item $item) {
                            return $item->getTaxAmount() ?: 0.00;
                        },
                        $quoteItems
                    )
                ),
                2
            );
        } else {
            $convertedQuote['order_data']['tax_total']['value'] = number_format(
                (float)($quote->getShippingAddress()->getTaxAmount() ?: 0.00),
                2
            );
        }

        return $convertedQuote;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     * @phpstan-return array<string, array<string, array<string, string>>>
     */
    public function convertDiscount(Mage_Sales_Model_Quote $quote)
    {
        $currencyCode = $quote->getQuoteCurrencyCode() ?: '';

        return [
            'order_data' => [
                'discount' => [
                    'currency_code' => $currencyCode,
                    'value' => number_format((float)($quote->getSubtotal() - $quote->getSubtotalWithDiscount()), 2)
                ]
            ]
        ];
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param array $convertedQuote
     * @phpstan-param array<string, array<string, array<array<string, array<string, string>|bool|int|string>|string>>> $convertedQuote
     * @return void
     */
    private function convertCustomTotals(Mage_Sales_Model_Quote $quote, array &$convertedQuote)
    {
        if (!$this->areTotalsCollected) {
            $quote->collectTotals();

            $this->areTotalsCollected = true;
        }

        $currencyCode = $quote->getQuoteCurrencyCode() ?: '';
        $excludedTotals = ['subtotal', 'shipping', 'tax', 'grand_total'];
        $customTotals = array_filter(
            $quote->getTotals(),
            static function (Mage_Sales_Model_Quote_Address_Total $total) use ($excludedTotals) {
                return !in_array($total->getCode(), $excludedTotals, true);
            }
        );

        if (count($customTotals) === 0) {
            return;
        }

        $customTotalsValue = 0;
        $totalItems = array_filter(
            array_map(
                static function (Mage_Sales_Model_Quote_Address_Total $total) use ($currencyCode, &$customTotalsValue) {
                    /** @var string|null $name */
                    $name = $total->getData('title') ?: '';
                    /** @var float|string|null $value */
                    $value = $total->getData('value') ?: 0;

                    if ((float)$value === 0.00) {
                        return null;
                    }

                    $customTotalsValue += (float)$value;

                    return [
                        'name' => $name,
                        'sku' => $total->getCode() ?: '',
                        'unit_amount' => [
                            'currency_code' => $currencyCode ?: '',
                            'value' => number_format((float)$value, 2)
                        ],
                        'quantity' => 1,
                        'is_shipping_required' => false
                    ];
                },
                array_values($customTotals)
            )
        );

        if ($customTotalsValue === 0) {
            return;
        }

        $convertedQuote['order_data']['items'] = array_merge($convertedQuote['order_data']['items'], $totalItems);
        $convertedQuote['order_data']['item_total']['value'] = number_format(
            ((float)$convertedQuote['order_data']['item_total']['value']) + $customTotalsValue,
            2
        );
    }
}
