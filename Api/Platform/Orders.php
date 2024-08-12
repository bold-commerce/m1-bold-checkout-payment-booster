<?php

/**
 * Platform orders api service.
 *
 * phpcs:disable Zend.NamingConventions.ValidVariableName.NotCamelCaps
 */
class Bold_Checkout_Api_Platform_Orders
{
    /**
     * Retrieve order list created with bold checkout.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function getList(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        $listBuilder = function ($limit, $cursor, $websiteId) {
            return Bold_Checkout_Model_Resource_OrderListBuilder::buildList($limit, $cursor, $websiteId);
        };
        try {
            return Bold_Checkout_Rest::buildListResponse($request, $response, 'orders', $listBuilder);
        } catch (Exception $e) {
            return Bold_Checkout_Rest::buildErrorResponse($response, $e->getMessage());
        }
    }

    /**
     * Retrieve order by increment id.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function get(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        preg_match('/orders\/(.*)/', $request->getRequestUri(), $orderIdMatches);
        $orderId = isset($orderIdMatches[1]) ? $orderIdMatches[1] : null;
        $order = Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            return self::getValidationErrorResponse(
                Mage::helper('core')->__(
                    'Order with id: "%1" not found.',
                    $orderId
                ),
                $response
            );
        }
        $orderData = current(Bold_Checkout_Service_Extractor_Order::extract([$order]));
        return Bold_Checkout_Rest::buildResponse(
            $response,
            json_encode($orderData)
        );
    }

    /**
     * Create and save in db magento order from request.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function create(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        $requestBody = json_decode($request->getRawBody());
        $quoteId = $requestBody->order->quoteId;
        if (Bold_Checkout_Service_Order_Progress::isInProgress($quoteId)) {
            return self::getValidationErrorResponse(
                Mage::helper('core')->__(
                    'Order for cart id: "%1" already in progress.',
                    $quoteId
                ),
                $response
            );
        }
        Bold_Checkout_Service_Order_Progress::start($quoteId);
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote');
        $quote->load($quoteId);
        try {
            $websiteId = (int)$quote->getStore()->getWebsiteId();
            /** @var Bold_Checkout_Model_Config $config */
            $config = Mage::getModel(Bold_Checkout_Model_Config::RESOURCE);
            $magentoOrder = $config->isCheckoutTypeSelfHosted($websiteId)
                ? Bold_Checkout_Service_Order_ProcessOrder::process($requestBody)
                : Bold_Checkout_Service_Order_CreateOrder::create($requestBody, $quote);
        } catch (Exception $e) {
            Bold_Checkout_Service_Order_Progress::stop($quoteId);
            return self::getErrorResponse($e->getMessage(), $response);
        }
        Bold_Checkout_Service_Order_Progress::stop($quoteId);
        return self::getSuccessResponse($magentoOrder, $response);
    }

    /**
     * Update order status.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function update(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        $payload = json_decode($request->getRawBody());
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($payload->entity->entity_id);
        $order->setState($payload->entity->state);
        $order->setStatus($payload->entity->status);
        $order->save();
        $orderData = current(Bold_Checkout_Service_Extractor_Order::extract([$order]));
        return Bold_Checkout_Rest::buildResponse($response, json_encode($orderData));
    }

    /**
     * @param $message
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    private static function getValidationErrorResponse($message, Mage_Core_Controller_Response_Http $response)
    {
        $error = new stdClass();
        $error->message = $message;
        $error->code = 422;
        $error->type = 'server.validation_error';
        return Bold_Checkout_Rest::buildResponse($response, json_encode(
                [
                    'errors' => [$error],
                ]
            )
        );
    }

    private static function getErrorResponse($message, Mage_Core_Controller_Response_Http $response)
    {
        $error = new stdClass();
        $error->message = $message;
        $error->code = 500;
        $error->type = 'server.internal_error';
        return Bold_Checkout_Rest::buildResponse($response, json_encode(
                [
                    'errors' => [$error],
                ]
            )
        );
    }

    private static function getSuccessResponse($magentoOrder, Mage_Core_Controller_Response_Http $response)
    {
        return Bold_Checkout_Rest::buildResponse($response, json_encode(
                [
                    'order' => $magentoOrder,
                    'errors' => [],
                ]
            )
        );
    }
}
