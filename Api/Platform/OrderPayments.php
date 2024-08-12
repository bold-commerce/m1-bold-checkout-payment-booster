<?php

/**
 * Platform payments api service.
 */
class Bold_Checkout_Api_Platform_OrderPayments
{
    /**
     * Update payment information.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     * @phpcs:disable Zend.NamingConventions.ValidVariableName.NotCamelCaps
     */
    public static function update(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        $payload = json_decode($request->getRawBody());
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($payload->payment->payment->parent_id);
        if (self::isDelayedCapture($order) || $order->hasInvoices()) {
            return Bold_Checkout_Rest::buildResponse(
                $response,
                json_encode(
                    [
                        'errors' => [],
                        'payment' => Bold_Checkout_Service_Extractor_Order_Payment::extract($order->getPayment()),
                    ]
                )
            );
        }
        Bold_Checkout_Service_Order_Payment::processPayment(
            $order,
            $payload->payment->payment,
            $payload->payment->transaction
        );
        try {
            self::invoice($order);
        } catch (Exception $e) {
            $error = new stdClass();
            $error->message = $e->getMessage();
            $error->code = 500;
            $error->type = 'server.internal_error';
            return Bold_Checkout_Rest::buildResponse($response, json_encode(['errors' => [$error]]));
        }
        return Bold_Checkout_Rest::buildResponse(
            $response,
            json_encode(
                [
                    'errors' => [],
                    'payment' => Bold_Checkout_Service_Extractor_Order_Payment::extract($order->getPayment()),
                ]
            )
        );
    }

    /**
     * Create invoice for order.
     *
     * @param Mage_Sales_Model_Order $order
     * @return void
     * @throws Exception
     */
    private static function invoice(
        Mage_Sales_Model_Order $order
    ) {
        $payment = $order->getPayment();
        if (!$payment->getBaseAmountPaid() || $order->hasInvoices()) {
            return;
        }
        $invoice = $order->prepareInvoice();
        $invoice->setRequestedCaptureCase('offline');
        $invoice->register();
        $invoice->setEmailSent(true);
        $invoice->setTransactionId($payment->getLastTransId());
        $invoice->getOrder()->setCustomerNoteNotify(true);
        $invoice->getOrder()->setIsInProcess(true);
        $order->addRelatedObject($invoice);
        $order->save();
    }

    /**
     * Verify if order using delayed payment capture.
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    private static function isDelayedCapture(Mage_Sales_Model_Order $order)
    {
        /** @var Bold_Checkout_Model_Order $orderExtensionData */
        $orderExtensionData = Mage::getModel(Bold_Checkout_Model_Order::RESOURCE);
        $orderExtensionData->load($order->getEntityId(), Bold_Checkout_Model_Resource_Order::ORDER_ID);
        return (bool)$orderExtensionData->getIsDelayedCapture();
    }
}
