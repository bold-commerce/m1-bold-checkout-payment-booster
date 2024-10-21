<?php

class Bold_CheckoutPaymentBooster_ExpresspayController extends Mage_Core_Controller_Front_Action
{
    /**
     * @return void
     */
    public function createOrderAction()
    {
        if (!$this->getRequest()->isPost() || !$this->getRequest()->isAjax()) {
            $this->_forward('noroute');

            return;
        }

        $this->parseRawJsonRequestData();

        if (!$this->_validateFormKey()) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode(['error' => Mage::helper('core')->__('Invalid form key.')]));

            return;
        }

        $quoteId = $this->getRequest()->getParam('quote_id');

        if ($quoteId === null) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode(['error' => Mage::helper('core')->__('Please provide a quote ID.')]));

            return;
        }

        $gatewayId = $this->getRequest()->getParam('gateway_id');

        if ($gatewayId === null) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode(['error' => Mage::helper('core')->__('Please provide a gateway ID.')]));

            return;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote')->load($quoteId);

        if ($quote->getId() === null) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode(['error' => Mage::helper('core')->__('Invalid quote ID "%s".', $quoteId)]));

            return;
        }

        $websiteId = $quote->getStore()->getWebsiteId();
        $uri = '/checkout/orders/{{shopId}}/wallet_pay';
        $quoteConverter = new Bold_CheckoutPaymentBooster_Service_ExpressPay_QuoteConverter();
        $expressPayData = $quoteConverter->convertFullQuote($quote, $gatewayId);
        $result = Bold_CheckoutPaymentBooster_Service_BoldClient::post($uri, $websiteId, $expressPayData);

        if (property_exists($result, 'errors') && count($result->errors) > 0) {
            if (is_object($result->errors[0])) {
                $exceptionMessage = Mage::helper('core')
                    ->__('Could not create Express Pay order. Error: "%s"', $result->errors[0]->message);
            } else {
                $exceptionMessage = Mage::helper('core')
                    ->__('Could not create Express Pay order. Error: "%s"', $result->errors[0]);
            }

           $this->getResponse()
               ->setHttpResponseCode(500)
               ->setHeader('Content-Type', 'application/json')
               ->setBody(json_encode(['error' => $exceptionMessage]));

            return;
        }

        if (!property_exists($result, 'data') || !property_exists($result->data, 'order_id')) {
            $this->getResponse()
                ->setHttpResponseCode(500)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(
                    json_encode(
                        [
                            'error' => Mage::helper('core')
                                ->__('An unknown error occurred while creating the Express Pay order.')
                        ]
                    )
                );

            return;
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode(['order_id' => $result->data->order_id]));
    }

    /**
     * @return void
     */
    public function updateOrderAction()
    {
        if (!$this->getRequest()->isPost() || !$this->getRequest()->isAjax()) {
            $this->_forward('noroute');

            return;
        }

        $this->parseRawJsonRequestData();

        if (!$this->_validateFormKey()) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBody(json_encode(['error' => Mage::helper('core')->__('Invalid form key.')]));

            return;
        }

        $quoteId = $this->getRequest()->getParam('quote_id');

        if ($quoteId === null) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBody(json_encode(['error' => Mage::helper('core')->__('Please provide a quote ID.')]));

            return;
        }

        $gatewayId = $this->getRequest()->getParam('gateway_id');

        if ($gatewayId === null) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBody(json_encode(['error' => Mage::helper('core')->__('Please provide a gateway ID.')]));

            return;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote')->load($quoteId);

        if ($quote->getId() === null) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBody(json_encode(['error' => Mage::helper('core')->__('Invalid quote ID "%s".', $quoteId)]));

            return;
        }

        $websiteId = $quote->getStore()->getWebsiteId();
        $uri = '/checkout/orders/{{shopId}}/wallet_pay';
        $quoteConverter = new Bold_CheckoutPaymentBooster_Service_ExpressPay_QuoteConverter();
        $expressPayData = $quoteConverter->convertFullQuote($quote, $gatewayId);
        $result = Bold_CheckoutPaymentBooster_Service_BoldClient::put($uri, $websiteId, $expressPayData);

        if (is_object($result) && property_exists($result, 'errors') && count($result->errors) > 0) {
            if (is_object($result->errors[0])) {
                $exceptionMessage = Mage::helper('core')
                    ->__('Could not update Express Pay order. Error: "%s"', $result->errors[0]->message);
            } else {
                $exceptionMessage = Mage::helper('core')
                    ->__('Could not update Express Pay order. Error: "%s"', $result->errors[0]);
            }

            $this->getResponse()
                ->setHttpResponseCode(500)
                ->setBody(json_encode(['error' => $exceptionMessage]));

            return;
        }

        $this->getResponse()
            ->setHttpResponseCode(201);
    }

    /**
     * @return void
     */
    private function parseRawJsonRequestData()
    {
        if ($this->getRequest()->getHeader('Content-Type') !== 'application/json') {
            return;
        }

        $_POST = json_decode(file_get_contents('php://input'), true);
    }
}