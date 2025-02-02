<?php

/**
 * @method Bold_CheckoutPaymentBooster_Block_Checkout_Expresspay setPaymentsContainerId(string $paymentsContainerId)
 */
class Bold_CheckoutPaymentBooster_Block_Checkout_Expresspay extends Mage_Core_Block_Template
{
    /**
     * @return bool
     */
    public function isEnabled()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        switch ($this->getBlockAlias()) {
            case 'cart_sidebar.bold.booster.expresspay':
                return $config->isExpressPayEnabledInMiniCart($websiteId);
            case 'product.detail.bold.booster.expresspay':
                return $config->isExpressPayEnabledOnProductPage($websiteId);
            case 'cart.bold.booster.expresspay':
                return $config->isExpressPayEnabledInCart($websiteId);
            case 'checkout.bold.booster.expresspay':
                return $config->isExpressPayEnabled($websiteId);
            default:
                return false;
        }
    }

    public function isCheckoutActive()
    {
        return $this->getAction()->getFullActionName() === 'checkout_onepage_index';
    }

    /**
     * @return string
     */
    public function getPaymentsContainerId()
    {
        $paymentsContainerId = $this->getData('payments_container_id');

        return $paymentsContainerId !== null ? $paymentsContainerId : 'express-pay-container';
    }

    /**
     * @return string
     */
    public function getPageSource()
    {
        switch ($this->getBlockAlias()) {
            case 'cart_sidebar.bold.booster.expresspay':
                $pageSource = 'mini-cart';
                break;
            case 'product.detail.bold.booster.expresspay':
                $pageSource = 'product-details';
                break;
            case 'cart.bold.booster.expresspay':
                $pageSource = 'cart';
                break;
            case 'checkout.bold.booster.expresspay':
                $pageSource = 'checkout';
                break;
            default:
                $pageSource = 'unknown';
        }

        return $pageSource;
    }

    /**
     * @return string
     */
    public function getEpsApiUrl()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->getEpsUrl($websiteId) ?: '';
    }

    /**
     * @return string
     */
    public function getEpsStaticApiUrl()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->getEpsStaticUrl($websiteId) ?: '';
    }

    /**
     * @return string
     */
    public function getShopDomain()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->getShopDomain($websiteId) ?: '';
    }

    /**
     * @return Mage_Sales_Model_Quote|null
     */
    public function getQuote()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');

        return $checkoutSession->getQuote();
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        $quote = $this->getQuote();
        $storeCurrency = Mage::app()->getStore()->getCurrentCurrencyCode();

        if ($quote === null) {
            return $storeCurrency;
        }

        $currency = $quote->getQuoteCurrencyCode();

        return !empty($currency) ? $currency : $storeCurrency;
    }

    /**
     * @return string[][]
     */
    public function getQuoteTotals()
    {
        $quote = $this->getQuote();

        if ($quote === null) {
            return [];
        }

        $totals = array_map(
            static function (Mage_Sales_Model_Quote_Address_Total $total) {
                return [
                    'code' => $total->getCode(),
                    'value' => number_format((float)$total->getValue(), 2, '.', '')
                ];
            },
            $quote->getTotals()
        );

        return $totals;
    }

    /**
     * @return string[][]
     */
    public function getQuoteItems()
    {
        $quote = $this->getQuote();

        if ($quote === null) {
            return [];
        }

        $quoteItems = array_map(
            static function (Mage_Sales_Model_Quote_Item $quoteItem) {
                return [
                    'sku' => $quoteItem->getSku(),
                    'price' => number_format((float)$quoteItem->getPrice(), 2, '.', ''),
                    'name' => $quoteItem->getName()
                ];
            },
            $quote->getAllVisibleItems()
        );

        return $quoteItems;
    }

    /**
     * @return stdClass|null
     */
    public function getBoldCheckoutData()
    {
        return Bold_CheckoutPaymentBooster_Service_Bold::getBoldCheckoutData();
    }

    /**
     * @return string
     */
    public function getFormKey()
    {
        return Mage::getSingleton('core/session')->getFormKey();
    }

    /**
     * @return string
     */
    public function getRegionsAsJson()
    {
        return Mage::helper('directory')->getRegionJsonByStore($this->getQuote()->getStoreId());
    }

    /**
     * @return string[]
     */
    public function getAllowedCountries()
    {
        return explode(',', (string)Mage::getStoreConfig('general/country/allow')) ?: [];
    }

    /**
     * @return int|float
     */
    public function getDefaultProductQuantity()
    {
        /** @var Mage_Catalog_Model_Product|null $product */
        $product = Mage::registry('current_product');

        if ($product === null) {
            return 1;
        }

        /** @var Mage_Catalog_Helper_Product $productHelper */
        $productHelper = Mage::helper('catalog/product');

        return $productHelper->getDefaultQty($product);
    }

    /**
     * @return float
     */
    public function getProductPrice()
    {
        /** @var Mage_Catalog_Model_Product|null $product */
        $product = Mage::registry('current_product');

        if ($product === null) {
            return 0.00;
        }

        if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            return $product->getPriceModel()->getTotalPrices($product, 'min');
        }

        if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            $groupedProductPrices = array_map(
                static function (Mage_Catalog_Model_Product $product) {
                    return $product->getFinalPrice();
                },
                $product->getTypeInstance(true)->getAssociatedProducts($product)
            );

            return min($groupedProductPrices);
        }

        return $product->getFinalPrice();
    }

    /**
     * @return string
     */
    public function getProductAddToCartUrl()
    {
        /** @var Mage_Catalog_Model_Product|null $product */
        $product = Mage::registry('current_product');

        if ($product === null) {
            return '';
        }

        /** @var Mage_Checkout_Helper_Cart $cartHelper */
        $cartHelper = Mage::helper('checkout/cart');

        return $cartHelper->getAddUrl($product);
    }

    /**
     * @return bool
     */
    public function isFastlaneEnabled()
    {
        return Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE)
            ->isFastlaneEnabled(Mage::app()->getStore()->getWebsiteId());
    }
}
