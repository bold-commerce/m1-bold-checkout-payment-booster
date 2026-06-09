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
<<<<<<< Updated upstream
        if (!$this->requirePostAjax() || !$this->validateFormKeyJson()) {
            return;
        }

=======
>>>>>>> Stashed changes
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $cartData = Bold_CheckoutPaymentBooster_Service_Order_Hydrate_ExtractData::extractQuoteData($quote);
        $cartData['quote_currency_code'] = $quote->getQuoteCurrencyCode();
        $cartData['shipping_options'] = Bold_CheckoutPaymentBooster_Service_Order_Hydrate_ExtractData::getQuoteShippingOptions($quote);
        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(json_encode($cartData));
    }

    /**
     * Return current Bold checkout session tokens for client sync before Payments SDK init.
     *
     * @return void
     */
    public function getCheckoutSessionAction()
    {
        if (!$this->requirePostAjax() || !$this->validateFormKeyJson()) {
            return;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if (!$quote || !$quote->getId()) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode(array(
                    'error' => Mage::helper('checkout')->__('Your shopping cart could not be found.'),
                )));

            return;
        }

        try {
            Bold_CheckoutPaymentBooster_Service_Bold::initBoldCheckoutData($quote);
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::CRIT);
            $this->getResponse()
                ->setHttpResponseCode(500)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode(array(
                    'error' => Mage::helper('core')->__('Unable to initialize Bold checkout session.'),
                )));

            return;
        }

        /** @var Bold_CheckoutPaymentBooster_Block_Payment_Form_Base $block */
        $block = Mage::getSingleton('core/layout')->createBlock('bold_checkout_payment_booster/payment_form_base');
        $payload = $block->getCheckoutSessionPayload();

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(Mage::helper('core')->jsonEncode($payload));
    }

    /**
     * @return void
     */
    public function getCartTotalsAction()
    {
        if (!$this->requirePostAjax() || !$this->validateFormKeyJson()) {
            return;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $cartTotals = array_map(
            static function (Mage_Sales_Model_Quote_Address_Total $total) {
                return [
                    'code' => $total->getCode(),
                    'value' => number_format((float)$total->getValue(), 2, '.', ''),
                ];
            },
            $quote->getTotals()
        );

        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(json_encode($cartTotals));
    }

    /**
     * @return void
     */
    public function getCartItemsAction()
    {
        if (!$this->requirePostAjax() || !$this->validateFormKeyJson()) {
            return;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $quoteItems = array_map(
            static function (Mage_Sales_Model_Quote_Item $quoteItem) {
                return [
                    'sku' => $quoteItem->getSku(),
                    'price' => number_format((float)$quoteItem->getPrice(), 2, '.', ''),
                    'name' => $quoteItem->getName(),
                ];
            },
            $quote->getAllVisibleItems()
        );

        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(json_encode($quoteItems));
    }

    /**
     * @return bool
     */
    private function requirePostAjax()
    {
        if (!$this->getRequest()->isPost() || !$this->getRequest()->isAjax()) {
            $this->_forward('noroute');

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function validateFormKeyJson()
    {
        if (!$this->_validateFormKey()) {
            $this->getResponse()
                ->setHttpResponseCode(403)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode(array(
                    'error' => Mage::helper('core')->__('Invalid form key.'),
                )));

            return false;
        }

        return true;
    }
}
