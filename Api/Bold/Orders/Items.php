<?php

/**
 * Update order information on bold side service.
 */
class Bold_Checkout_Api_Bold_Orders_Items
{
    /**
     * Update order line items fulfilment status.
     *
     * @param Mage_Sales_Model_Order $order
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function fulfilItems(Mage_Sales_Model_Order $order)
    {
        if ($order->isCanceled()) {
            return;
        }
        $extOrderData = Mage::getModel(Bold_Checkout_Model_Order::RESOURCE)->load(
            $order->getEntityId(),
            Bold_Checkout_Model_Resource_Order::ORDER_ID
        );
        $publicOrderId = $extOrderData->getPublicId();
        if (!$publicOrderId) {
            return;
        }
        $url = sprintf('/checkout/orders/{{shopId}}/%s/line_items', $publicOrderId);
        $itemsToFulfill = [];
        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($order->getAllItems() as $item) {
            if ($item->getChildrenItems()) {
                continue;
            }
            $fulfilledQty = self::getFulfilledQty($item);
            if (!$fulfilledQty) {
                continue;
            }
            $itemsToFulfill[] = [
                'line_item_key' => (string)$item->getQuoteItemId(),
                'fulfilled_quantity' => $fulfilledQty,
            ];
        }
        if (!$itemsToFulfill) {
            return;
        }
        $websiteId = $order->getStore()->getWebsiteId();
        $body = ['line_items' => $itemsToFulfill];
        Bold_Checkout_Client::call('PATCH', $url, $websiteId, json_encode($body));
    }

    /**
     * Retrieve invoiced and shipped qty for non-virtual and invoiced qty for virtual items.
     *
     * @param Mage_Sales_Model_Order_Item $item
     * @return int
     */
    private static function getFulfilledQty(Mage_Sales_Model_Order_Item $item)
    {
        $item = $item->getParentItem() ?: $item;
        $qtyInvoiced = $item->getQtyInvoiced() - $item->getOrigData('qty_invoiced');
        if ($item->getIsVirtual()) {
            return (int)$qtyInvoiced;
        }
        $qtyShipped = $item->getQtyShipped() - $item->getOrigData('qty_shipped');
        if ($qtyInvoiced && $qtyShipped) {
            return min((int)$qtyInvoiced, (int)$qtyShipped);
        }
        if ($qtyInvoiced) {
            return min((int)$item->getQtyShipped(), (int)$qtyInvoiced);
        }
        if ($qtyShipped) {
            return min((int)$qtyShipped, (int)$item->getQtyInvoiced());
        }

        return 0;
    }
}
