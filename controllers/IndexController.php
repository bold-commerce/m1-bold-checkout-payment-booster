<?php

/**
 * Bold checkout payment booster index controller.
 */
class Bold_CheckoutPaymentBooster_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * Hydrate order data to Bold order action.
     *
     * @return void
     */
    public function hydrateOrderDataAction()
    {
        if (!$this->_validateFormKey()) {
            return;
        }
        $post = $this->getRequest()->getPost();
        unset($post['form_key']);
        if (empty($post)) {
            Mage::throwException('No data provided.');
        }
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if (!$quote->getId()) {
            Mage::throwException('No quote found.');
        }
        if (isset($post['address_id']) && $quote->getCustomer()->getId()) {
            Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
            return;
        }
        $post['street'] = $post['street2']
            ? $post['street1'] . "\n" . $post['street2']
            : $post['street1'];
        $this->addBillingAddressDataToQuote($quote, $post);
        Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
    }

    public function updateCartAddressAction()
    {
        if (!$this->_validateFormKey()) {
            return;
        }
        $post = $this->getRequest()->getPost();
        unset($post['form_key']);
        if (empty($post)) {
            Mage::throwException('No data provided.');
        }
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if (!$quote->getId()) {
            Mage::throwException('No quote found.');
        }
        $billingAddressData = isset($post['billing'])
            ? Mage::helper('core')->jsonDecode($post['billing'])
            : [];
        $shippingAddressData = isset($post['shipping'])
            ? Mage::helper('core')->jsonDecode($post['shipping'])
            : [];
        $billingAddressData = $billingAddressData ?: [];
        $shippingAddressData = $shippingAddressData ?: [];
        $this->addBillingAddressDataToQuote($quote, $billingAddressData);
        $this->addShippingAddressDataToQuote($quote, $shippingAddressData);
        $quote->collectTotals()->save();
    }

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
        $cartData['shipping_options'] = Bold_CheckoutPaymentBooster_Service_Order_Hydrate_ExtractData::getShippingOptions($quote);
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

    /**
     * Add post data to quote billing address.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array $addressData
     * @return void
     */
    private function addBillingAddressDataToQuote(Mage_Sales_Model_Quote $quote, array $addressData = [])
    {
        $addressData = $this->cleanUpAddressData($addressData);
        if (!$addressData) {
            return;
        }
        $quote->getBillingAddress()->addData($addressData)->save();
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

    private function addShippingAddressDataToQuote(Mage_Sales_Model_Quote $quote, array $addressData)
    {
        $addressData = $this->cleanUpAddressData($addressData);
        if (!$addressData) {
            return;
        }
        $quote->getShippingAddress()->addData($addressData)->save();
    }

    private function cleanUpAddressData(array $addressData)
    {
        foreach ($addressData as $key => $value) {
            if ($value === null) {
                unset($addressData[$key]);
            }
            if ($key === 'street' && is_array($value) && $value[0] === null) {
                unset($addressData[$key]);
            }
        }
        return $addressData;
    }
}
