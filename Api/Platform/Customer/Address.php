<?php

/**
 * Customer address validation rest service.
 */
class Bold_Checkout_Api_Platform_Customer_Address
{
    /**
     * Validate customer address endpoint.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function validate(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        $payload = json_decode($request->getRawBody());
        $addressData = isset($payload->address) ? $payload->address : new stdClass();
        $address = Bold_Checkout_Service_Customer_Address_Convertor::getAddress($addressData);
        if (!$address->getTelephone()) {
            $address->setTelephone('0000000000');// Set a default phone number placeholder if none is provided.
        }
        $validationResult = $address->validate();
        if ($validationResult === true) {
            return Bold_Checkout_Rest::buildResponse($response, json_encode(
                [
                    'valid' => true,
                    'errors' => [],
                ]
            ));
        }
        $errors = [];
        foreach ($validationResult as $error) {
            $errors[] = [
                'code' => 422,
                'type' => 'server.validation_error',
                'message' => $error,
            ];
        }
        $result = [
            'valid' => false,
            'errors' => $errors,
        ];
        return Bold_Checkout_Rest::buildResponse($response, json_encode($result));
    }
}
