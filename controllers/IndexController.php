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
        $this->addAddressDataToQuote($quote, $post);
        Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
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
        $this->getResponse()->setBody(json_encode($cartData));
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
