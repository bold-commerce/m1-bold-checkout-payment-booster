<?php

/**
 * Quote item service.
 */
class Bold_CheckoutPaymentBooster_Service_Quote_Item
{
    /**
     * Extract quote item data.
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return array
     */
    public static function extract(Mage_Sales_Model_Quote_Item $item)
    {
        return [
            'quantity' => self::getQuantity($item),
            'title' => self::getTitle($item),
            'price' => self::getPrice($item),
            'weight' => (int)self::getWeight($item),
            'taxable' => self::isTaxable($item),
            'image' => self::getImage($item),
            'requires_shipping' => !$item->getIsVirtual(),
            'line_item_key' => (string)$item->getItemId(),
            'sku' => $item->getSku(),
            'vendor' => self::getVendor($item),
        ];
    }

    /**
     * Retrieve item quantity.
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return int
     */
    private static function getQuantity(Mage_Sales_Model_Quote_Item $item)
    {
        $item = $item->getParentItem() ?: $item;
        return (int)$item->getQty();
    }

    /**
     * Retrieve item title.
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return string
     */
    private static function getTitle(Mage_Sales_Model_Quote_Item $item)
    {
        $item = $item->getParentItem() ?: $item;
        return $item->getName();
    }

    /**
     * Retrieve item price.
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return int
     */
    private static function getPrice(Mage_Sales_Model_Quote_Item $item)
    {
        $item = $item->getParentItem() ?: $item;
        return (int)round(($item->getPrice() * 100));
    }

    /**
     * Retrieve item weight.
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return float
     */
    private static function getWeight(Mage_Sales_Model_Quote_Item $item)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $conversionRate = $config->getWeightConversionRate((int)$item->getQuote()->getStore()->getWebsiteId());
        $weight = $item->getWeight();

        return $weight * $conversionRate;
    }

    /**
     * Check if item is taxable.
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return bool
     */
    private static function isTaxable(Mage_Sales_Model_Quote_Item $item)
    {
        return (bool)$item->getData('tax_class_id');
    }

    /**
     * Retrieve item image.
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return string
     */
    public static function getImage(Mage_Sales_Model_Quote_Item $item)
    {
        $item = $item->getParentItem() ?: $item;
        $image = $item->getProduct()->getThumbnail();
        /** @var Mage_Catalog_Helper_Image $imageHelper */
        $imageHelper = Mage::helper('catalog/image');

        return $image ? (string)$imageHelper->init($item->getProduct(), 'thumbnail') : '';
    }

    /**
     * Retrieve item vendor.
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return string
     */
    public static function getVendor(Mage_Sales_Model_Quote_Item $item)
    {
        return ''; //todo: investigate how to get vendor.
    }
}
