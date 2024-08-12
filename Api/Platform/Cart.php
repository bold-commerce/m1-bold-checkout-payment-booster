<?php

/**
 * Cart rest service.
 */
class Bold_Checkout_Api_Platform_Cart
{
    /**
     * Get cart endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function getCart(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        preg_match('/cart\/(.*)/', $request->getRequestUri(), $cartIdMatches);
        $cartId = isset($cartIdMatches[1]) ? $cartIdMatches[1] : null;
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote');
        $quote->loadActive($cartId);
        if (!$quote->getId()) {
            return self::getErrorResult($cartId, $response);
        }
        try {
            self::prepareStore($quote);
            if (!$quote->getIsVirtual()) {
                $quote->getShippingAddress()->setCollectShippingRates(true);
            }
            $quote->collectTotals();
            $quoteData = Bold_Checkout_Service_Extractor_Quote::extract($quote);
        } catch (Mage_Core_Model_Store_Exception $e) {
            return self::buildErrorResponse($e->getMessage(), $response);
        }
        return Bold_Checkout_Rest::buildResponse($response, json_encode($quoteData));
    }

    /**
     * Set cart addresses endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function setAddresses(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        preg_match('/cart\/(.*)\/addresses/', $request->getRequestUri(), $cartIdMatches);
        $cartId = isset($cartIdMatches[1]) ? $cartIdMatches[1] : null;
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote');
        $quote->loadActive($cartId);
        if (!$quote->getId()) {
            return self::getErrorResult($cartId, $response);
        }
        $payload = json_decode($request->getRawBody());
        /** @var Bold_Checkout_Model_Config $config */
        $config = Mage::getSingleton(Bold_Checkout_Model_Config::RESOURCE);
        try {
            self::prepareStore($quote);
            if ($payload->billing_address === null) {
                $quote->removeAddress($quote->getBillingAddress()->getId());
                $quote->removeAddress($quote->getShippingAddress()->getId());
                $quote->setDataChanges(true);
                $quote->collectTotals();
                if (!$config->isCheckoutTypeSelfHosted((int)$quote->getStore()->getWebsiteId())) {
                    $quote->save();
                }
                $quoteData = Bold_Checkout_Service_Extractor_Quote::extract($quote);
                return Bold_Checkout_Rest::buildResponse($response, json_encode($quoteData));
            }
            self::updateAddress($quote->getBillingAddress(), $payload->billing_address, $quote);
            if (!$quote->isVirtual() && $payload->shipping_address) {
                self::updateAddress($quote->getShippingAddress(), $payload->shipping_address, $quote);
                $quote->getShippingAddress()->setCollectShippingRates(true);
            }
            $quote->setDataChanges(true);
            $quote->collectTotals();
            if (!$config->isCheckoutTypeSelfHosted((int)$quote->getStore()->getWebsiteId())) {
                $quote->save();
            }
            $quoteData = Bold_Checkout_Service_Extractor_Quote::extract($quote);
        } catch (Mage_Core_Model_Store_Exception $e) {
            return self::buildErrorResponse($e->getMessage(), $response);
        }
        return Bold_Checkout_Rest::buildResponse($response, json_encode($quoteData));
    }

    /**
     * Estimate cart shipping methods endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function estimateShippingMethods(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        preg_match('/carts\/(.*)\/estimate-shipping-methods/', $request->getRequestUri(), $cartIdMatches);
        $cartId = isset($cartIdMatches[1]) ? $cartIdMatches[1] : null;
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote');
        $quote->loadActive($cartId);
        if (!$quote->getId()) {
            return self::getErrorResult($cartId, $response);
        }
        if ($quote->isVirtual() || 0 == $quote->getItemsCount()) {
            return Bold_Checkout_Rest::buildResponse($response, json_encode([]));
        }
        $payload = json_decode($request->getRawBody());
        self::updateAddress($quote->getShippingAddress(), $payload->address, $quote);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setDataChanges(true);
        $quote->collectTotals();
        try {
            return Bold_Checkout_Rest::buildResponse(
                $response,
                json_encode(Bold_Checkout_Service_Extractor_Quote_ShippingMethods::extract($quote))
            );
        } catch (Mage_Core_Model_Store_Exception $e) {
            return self::buildErrorResponse($e->getMessage(), $response);
        }
    }

    /**
     * Sets the shipping or billing address and returns the quote total information.
     * If the cart is not virtual, the shipping address will be set. Otherwise the billing
     * address will be set.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function totalsInformation(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        preg_match('/carts\/(.*)\/totals-information/', $request->getRequestUri(), $cartIdMatches);
        $cartId = isset($cartIdMatches[1]) ? $cartIdMatches[1] : null;
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote');
        $quote->loadActive($cartId);
        if (!$quote->getId()) {
            return self::getErrorResult($cartId, $response);
        }
        $payload = json_decode($request->getRawBody());
        /** @var Bold_Checkout_Model_Config $config */
        $config = Mage::getSingleton(Bold_Checkout_Model_Config::RESOURCE);
        try {
            self::prepareStore($quote);
            if (!$quote->isVirtual()) {
                self::updateAddress($quote->getShippingAddress(), $payload->addressInformation->address, $quote);
                $quote->getShippingAddress()->setCollectShippingRates(true);
            } else {
                self::updateAddress($quote->getBillingAddress(), $payload->addressInformation->address, $quote);
            }
            $quote->setDataChanges(true);
            $quote->collectTotals();
            if (!$config->isCheckoutTypeSelfHosted((int)$quote->getStore()->getWebsiteId())) {
                $quote->save();
            }

            return Bold_Checkout_Rest::buildResponse(
                $response,
                json_encode(Bold_Checkout_Service_Extractor_Quote_Totals::extract($quote))
            );
        } catch (Mage_Core_Model_Store_Exception $e) {
            return self::buildErrorResponse($e->getMessage(), $response);
        }
    }

    /**
     * Set cart coupon code endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function setCoupon(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        preg_match('/cart\/(.*)\/coupons/', $request->getRequestUri(), $cartIdMatches);
        $cartId = isset($cartIdMatches[1]) ? $cartIdMatches[1] : null;
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote');
        $quote->loadActive($cartId);
        if (!$quote->getId()) {
            return self::getErrorResult($cartId, $response);
        }
        try {
            self::prepareStore($quote);
            $requestBody = json_decode($request->getRawBody());
            $isCodeLengthValid = strlen($requestBody->couponCode) <= 255;
            if (!$quote->getIsVirtual()) {
                $quote->getShippingAddress()->setCollectShippingRates(true);
            }
            $quote->setTotalsCollectedFlag(false);
            $quote->setCouponCode($isCodeLengthValid ? $requestBody->couponCode : '')->collectTotals()->save();
            /** @var Bold_Checkout_Model_Config $config */
            $config = Mage::getSingleton(Bold_Checkout_Model_Config::RESOURCE);
            $validateCouponCodes = $config->getValidateCouponCodes((int)$quote->getStore()->getWebsiteId());
            if ($validateCouponCodes && (!$isCodeLengthValid || $requestBody->couponCode !== $quote->getCouponCode())) {
                $message = Mage::helper('core')->__(
                    'Coupon code "%s" is not valid.',
                    Mage::helper('core')->escapeHtml($requestBody->couponCode)
                );
                $error = new stdClass();
                $error->message = $message;
                $error->code = 422;
                $error->type = 'server.validation_error';
                return Bold_Checkout_Rest::buildResponse(
                    $response,
                    json_encode(
                        [
                            'errors' => [$error],
                        ]
                    )
                );
            }
            $quoteData = Bold_Checkout_Service_Extractor_Quote::extract($quote);
        } catch (Mage_Core_Model_Store_Exception $e) {
            return self::buildErrorResponse($e->getMessage(), $response);
        }
        return Bold_Checkout_Rest::buildResponse($response, json_encode($quoteData));
    }

    /**
     * Remove cart coupon code endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function removeCoupon(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        preg_match('/cart\/(.*)\/coupons/', $request->getRequestUri(), $cartIdMatches);
        $cartId = isset($cartIdMatches[1]) ? $cartIdMatches[1] : null;
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote');
        $quote->loadActive($cartId);
        if (!$quote->getId()) {
            return self::getErrorResult($cartId, $response);
        }
        try {
            self::prepareStore($quote);
            if (!$quote->getIsVirtual()) {
                $quote->getShippingAddress()->setCollectShippingRates(true);
            }
            $quote->setTotalsCollectedFlag(false);
            $quote->setCouponCode('')->collectTotals()->save();
            $quoteData = Bold_Checkout_Service_Extractor_Quote::extract($quote);
        } catch (Mage_Core_Model_Store_Exception $e) {
            self::buildErrorResponse($e->getMessage(), $response);
        }
        return Bold_Checkout_Rest::buildResponse($response, json_encode($quoteData));
    }

    /**
     * Set cart shipping method endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     * @throws Mage_Core_Model_Store_Exception
     */
    public static function setShippingMethod(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        preg_match('/cart\/(.*)\/shippingMethod/', $request->getRequestUri(), $cartIdMatches);
        $cartId = isset($cartIdMatches[1]) ? $cartIdMatches[1] : null;
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote');
        $quote->loadActive($cartId);
        if (!$quote->getId()) {
            return self::getErrorResult($cartId, $response);
        }
        try {
            self::prepareStore($quote);
            $payload = json_decode($request->getRawBody());
            foreach ($quote->getShippingAddress()->getShippingRatesCollection() as $rate) {
                if ($payload->shippingMethodCode === $rate->getMethod()) {
                    $quote->getShippingAddress()->setShippingMethod($rate->getCode());
                }
            }
            $quote->collectTotals();
            $quote->save();
            $quoteData = Bold_Checkout_Service_Extractor_Quote::extract($quote);
        } catch (Mage_Core_Model_Store_Exception $e) {
            return self::buildErrorResponse($e->getMessage(), $response);
        }
        return Bold_Checkout_Rest::buildResponse($response, json_encode($quoteData));
    }

    /**
     * Get address from payload.
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param stdClass $addressPayload
     * @param Mage_Sales_Model_Quote $quote
     * @return void
     */
    private static function updateAddress(
        Mage_Sales_Model_Quote_Address $address,
        stdClass $addressPayload,
        Mage_Sales_Model_Quote $quote
    ) {
        $address->setCustomerId($quote->getCustomerId());
        $email = isset($addressPayload->email)
            ? $addressPayload->email
            : null;
        $regionId = isset($addressPayload->region_id)
            ? $addressPayload->region_id
            : null;
        $regionCode = isset($addressPayload->region_code)
            ? $addressPayload->region_code
            : null;
        $region = isset($addressPayload->region)
            ? $addressPayload->region
            : null;
        $countryId = isset($addressPayload->country_id)
            ? $addressPayload->country_id
            : null;
        $street1 = isset($addressPayload->street[0])
            ? $addressPayload->street[0]
            : null;
        $street2 = isset($addressPayload->street[1])
            ? $addressPayload->street[1]
            : null;
        $postcode = isset($addressPayload->postcode)
            ? $addressPayload->postcode
            : null;
        $telephone = isset($addressPayload->telephone)
            ? $addressPayload->telephone
            : null;
        $city = isset($addressPayload->city)
            ? $addressPayload->city
            : null;
        $firstname = isset($addressPayload->firstname)
            ? $addressPayload->firstname
            : null;
        $lastname = isset($addressPayload->lastname)
            ? $addressPayload->lastname
            : null;
        $sameAsBilling = isset($addressPayload->same_as_billing)
            ? $addressPayload->same_as_billing
            : null;
        $saveInAddressBook = isset($addressPayload->save_in_address_book)
            ? $addressPayload->save_in_address_book
            : null;
        $address->setEmail($email);
        $address->setRegionId($regionId);
        $address->setRegion($region);
        $address->setRegionCode($regionCode);
        $address->setCountryId($countryId);
        $address->setStreet([$street1, $street2]);
        $address->setPostcode($postcode);
        $address->setTelephone($telephone);
        $address->setCity($city);
        $address->setFirstname($firstname);
        $address->setLastname($lastname);
        $address->setSameAsBilling($sameAsBilling);
        $address->setSaveInAddressBook($saveInAddressBook);
        $address->setShouldIgnoreValidation(true);
    }

    /**
     * Build cart not active error result.
     *
     * @param int $cartId
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    private static function getErrorResult($cartId, Mage_Core_Controller_Response_Http $response)
    {
        $error = new stdClass();
        $error->message = 'There is no active cart with id: ' . $cartId;
        $error->code = 422;
        $error->type = 'server.validation_error';
        return Bold_Checkout_Rest::buildResponse(
            $response,
            json_encode(
                [
                    'errors' => [$error],
                ]
            )
        );
    }

    /**
     * Prepare store for the quote re-collect.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return void
     * @throws Mage_Core_Model_Store_Exception
     */
    private static function prepareStore(Mage_Sales_Model_Quote $quote)
    {
        Mage::app()->loadArea(Mage_Core_Model_App_Area::AREA_FRONTEND);
        Mage::app()->setCurrentStore($quote->getStoreId());
        Mage::app()->getStore()->setCurrentCurrencyCode($quote->getQuoteCurrencyCode());
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->setQuoteId($quote->getId());
        $checkoutSession->replaceQuote($quote);
        /** @var Mage_Customer_Model_Session $customerSession */
        $customerSession = Mage::getSingleton('customer/session');
        $customerSession->setCustomerId($quote->getCustomerId());
        $quote->getBillingAddress()->setShouldIgnoreValidation(true);
        if (!$quote->isVirtual()) {
            $quote->getShippingAddress()->setShouldIgnoreValidation(true);
        }
    }

    /**
     * Build error response.
     *
     * @param string $message
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    private static function buildErrorResponse($message, Mage_Core_Controller_Response_Http $response)
    {
        $error = new stdClass();
        $error->message = $message;
        $error->code = 400;
        $error->type = 'server.internal_error';
        return Bold_Checkout_Rest::buildResponse(
            $response,
            json_encode(
                [
                    'errors' => [$error],
                ]
            )
        );
    }
}
