<?php

/**
 * Cart inventory verification rest service.
 */
class Bold_Checkout_Api_Platform_Cart_Inventory
{
    /**
     * Get cart inventory.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @return Mage_Core_Controller_Response_Http
     */
    public static function getInventory(
        Mage_Core_Controller_Request_Http $request,
        Mage_Core_Controller_Response_Http $response
    ) {
        preg_match('/cart\/(.*)\/inventory/', $request->getRequestUri(), $cartIdMatches);
        $cartId = isset($cartIdMatches[1]) ? $cartIdMatches[1] : null;
        /** @var Mage_Sales_Model_Quote $cart */
        $cart = Mage::getModel('sales/quote');
        $quote = $cart->loadActive($cartId);
        if (!$quote->getId()) {
            $error = new stdClass();
            $error->message = 'There is no active cart with id: ' . $cartId;
            $error->code = 422;
            $error->type = 'server.validation_error';
            return Bold_Checkout_Rest::buildResponse($response, json_encode(
                    [
                        'errors' => [$error],
                    ]
                )
            );
        }
        $inventoryResult = [];
        foreach ($quote->getAllItems() as $item) {
            if (!Bold_Checkout_Service_Extractor_Quote_Item::shouldAppearInCart($item)) {
                continue;
            }
            $itemInventoryData = new stdClass();
            $itemInventoryData->cart_item_id = (int)$item->getId();
            $itemInventoryData->salable = (bool)$item->getProduct()->isSalable();
            $inventoryResult[] = $itemInventoryData;
        }
        return Bold_Checkout_Rest::buildResponse($response, json_encode(
                [
                    'errors' => [],
                    'inventory_data' => $inventoryResult,
                ]
            )
        );
    }
}
