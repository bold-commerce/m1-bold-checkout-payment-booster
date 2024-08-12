<?php

/**
 * Platform customer service.
 */
class Bold_Checkout_Api_Platform_Customers
{
    /**
     * Retrieve magento customers.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function search(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        return Bold_Checkout_Rest::buildResponse(
            $response,
            json_encode(
                Bold_Checkout_Model_CustomerListBuilder::build($request->getQuery())
            )
        );
    }

    /**
     * Save customer endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function save(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        $requestBody = json_decode($request->getRawBody());
        if (!isset($requestBody->customer)) {
            return Bold_Checkout_Rest::buildErrorResponse(
                $response,
                'Please specify customer data in request.',
                400,
                'server.validation_error'
            );
        }
        if (!isset($requestBody->customer->website_id)) {
            return Bold_Checkout_Rest::buildErrorResponse(
                $response,
                'Please specify website id in request.',
                400,
                'server.validation_error'
            );
        }
        $customerData = $requestBody->customer;
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId($requestBody->customer->website_id);
        $customer->loadByEmail($customerData->email);
        if ($customer->getId()) {
            $responseBody = current(Bold_Checkout_Service_Extractor_Customer::extract([$customer]));
            return Bold_Checkout_Rest::buildResponse($response, json_encode($responseBody));
        }
        try {
            $customer = self::getCustomer($customerData);
            $customer->setWebsiteId($requestBody->customer->website_id);
            $customer->setPassword($customer->generatePassword());
            $customer->setPasswordCreatedAt(Mage::getSingleton('core/date')->gmtTimestamp());
            $customer->setForceConfirmed(true);
            $customer->save();
        } catch (Exception $e) {
            return Bold_Checkout_Rest::buildErrorResponse($response, $e->getMessage());
        }
        $body = current(Bold_Checkout_Service_Extractor_Customer::extract([$customer]));

        return Bold_Checkout_Rest::buildResponse($response, json_encode($body));
    }

    /**
     * Create customer model from customer data.
     *
     * @param stdClass $customerData
     * @return Mage_Customer_Model_Customer
     */
    private static function getCustomer(stdClass $customerData)
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer');
        $addresses = isset($customerData->addresses) ? $customerData->addresses : [];
        $email = isset($customerData->email) ? $customerData->email : null;
        $firstname = isset($customerData->firstname) ? $customerData->firstname : null;
        $lastname = isset($customerData->lastname) ? $customerData->lastname : null;
        $customer->setEmail($email);
        $customer->setFirstname($firstname);
        $customer->setLastname($lastname);
        foreach ($addresses as $address) {
            $customerAddress = Bold_Checkout_Service_Customer_Address_Convertor::getAddress($address);
            $customerAddress->setShouldIgnoreValidation(true);
            $customer->addAddress($customerAddress);
        }
        return $customer;
    }
}
