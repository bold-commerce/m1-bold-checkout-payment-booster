<?php

/**
 * Bold checkout payment booster index controller.
 */
class Bold_CheckoutPaymentBooster_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * Get cart data action.
     *
     * @return void
     */
    public function getCartDataAction()
    {
        if (!$this->_validateFormKey()) {
            return;
        }
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $cartData = Bold_CheckoutPaymentBooster_Service_Order_Hydrate_ExtractData::extractQuoteData($quote);
        $cartData['quote_currency_code'] = $quote->getQuoteCurrencyCode();
        $this->getResponse()->setBody(json_encode($cartData));
    }

    public function getCartTotalsAction()
    {
        if (!$this->_validateFormKey()) {
            return;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $cartTotals = array_map(
            static function (Mage_Sales_Model_Quote_Address_Total $total) {
                return [
                    'code' => $total->getCode(),
                    'value' => number_format((float)$total->getValue(), 2, '.', '')
                ];
            },
            $quote->getTotals()
        );

        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(json_encode($cartTotals));
    }

    public function getCartItemsAction()
    {
        if (!$this->_validateFormKey()) {
            return;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
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

        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(json_encode($quoteItems));
    }

    /**
     * Add post data to quote billing address.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array $post
     * @return void
     */
    private function addAddressDataToQuote(Mage_Sales_Model_Quote $quote, array $post)
    {
        $quote->getBillingAddress()->addData($post)->save();
        if (!$quote->getCustomerEmail()) {
            $quote->setCustomerEmail($quote->getBillingAddress()->getEmail());
        }
        if (!$quote->getCustomerFirstname()) {
            $quote->setCustomerFirstname($quote->getBillingAddress()->getFirstname());
        }
        if (!$quote->getCustomerLastname()) {
            $quote->setCustomerLastname($quote->getBillingAddress()->getLastname());
        }
    }
}
