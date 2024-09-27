<?php

class Bold_CheckoutPaymentBooster_Model_Api2_Expresspay_Rest extends Bold_CheckoutPaymentBooster_Model_Api2_Expresspay
{
    /**
     * Creates an Express Pay order in Bold Checkout
     */
    protected function _create(array $data)
    {
        $quoteId = $this->getRequest()->getParam('quoteId');
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('quote/quote')->load($quoteId);

        if ($quote->getId() === null) {
             $this->_error(
                 Mage::helper('core')->__('Could not create Express Pay order. Invalid quote ID "%s".', $quoteId),
                 Mage_Api2_Model_Server::HTTP_BAD_REQUEST
             );
        }

        $websiteId = $quote->getStore()->getWebsiteId();
        $uri = '/checkout/orders/{{shopId}}/wallet_pay';
        $quoteConverter = new Bold_CheckoutPaymentBooster_Service_ExpressPay_QuoteConverter();
        $expressPayData = $quoteConverter->convertFullQuote($quote, $data['gateway_id']);

        try {
            $result = Bold_CheckoutPaymentBooster_Service_Client::post($uri, $websiteId, $expressPayData);
        } catch (Mage_Core_Exception $exception) {
            $this->_critical(
                Mage::helper('core')->__('Could not create Express Pay order. Error: "%s"', $exception->getMessage()),
                Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR
            );
        }

        if (property_exists($result, 'errors') && count($result->errors) > 0) {
            if (is_array($result->errors[0])) {
                $exceptionMessage = Mage::helper('core')
                    ->__(
                        'Could not create Express Pay order. Errors: "%s"',
                        implode(', ', array_column($result->errors, 'message'))
                    );
            } else {
                $exceptionMessage = Mage::helper('core')
                    ->__('Could not create Express Pay order. Error: "%s"', $result->errors[0]);
            }

            $this->_critical($exceptionMessage, Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        }

        if (!property_exists($result, 'body') || count($result->body) === 0) {
            $this->_critical(
                Mage::helper('core')->__('An unknown error occurred while creating the Express Pay order.'),
                Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR
            );
        }

        return $this->_getLocation(
            Mage::getModel('bold_checkout_payment_booster/expressPayApiResult')
                ->setData(
                    [
                        'id' => null,
                        'order_id' => $result->body['data']['order_id'],
                        'error' => null
                    ]
                )
        );
    }

    /**
     * Updates an Express Pay order in Bold Checkout
     */
    protected function _update(array $data)
    {
        $quoteId = $this->getRequest()->getParam('quoteId');
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('quote/quote')->load($quoteId);

        if ($quote->getId() === null) {
            $this->_error(
                Mage::helper('core')->__('Could not update Express Pay order. Invalid quote ID "%s".', $quoteId),
                Mage_Api2_Model_Server::HTTP_BAD_REQUEST
            );
        }

        $websiteId = $quote->getStore()->getWebsiteId();
        $uri = '/checkout/orders/{{shopId}}/wallet_pay';
        $quoteConverter = new Bold_CheckoutPaymentBooster_Service_ExpressPay_QuoteConverter();
        $expressPayData = $quoteConverter->convertFullQuote($quote, $data['gateway_id']);

        try {
            $result = Bold_CheckoutPaymentBooster_Service_Client::put($uri, $websiteId, $expressPayData);
        } catch (Mage_Core_Exception $exception) {
            $this->_critical(
                Mage::helper('core')->__('Could not update Express Pay order. Error: "%s"', $exception->getMessage()),
                Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR
            );
        }

        if (property_exists($result, 'errors') && count($result->errors) > 0) {
            if (is_array($result->errors[0])) {
                $exceptionMessage = Mage::helper('core')
                    ->__(
                        'Could not update Express Pay order. Errors: "%s"',
                        implode(', ', array_column($result->errors, 'message'))
                    );
            } else {
                $exceptionMessage = Mage::helper('core')
                    ->__('Could not update Express Pay order. Error: "%s"', $result->errors[0]);
            }

            $this->_critical($exceptionMessage, Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        }

        return $this->_getLocation(
            Mage::getModel('bold_checkout_payment_booster/expressPayApiResult')
                ->setData(
                    [
                        'id' => null,
                        'error' => null
                    ]
                )
        );
    }
}