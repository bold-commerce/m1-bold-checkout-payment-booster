<?php

/**
 * Bold checkout payment booster index controller.
 */
class Bold_CheckoutPaymentBooster_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * Sync order data action.
     *
     * @return void
     * @throws Mage_Core_Exception
     * @throws Exception
     */
    public function syncOrderDataAction()
    {
        if (!$this->_validateFormKey()) {
            return;
        }
        $post = $this->getRequest()->getPost();
        if (empty($post)) {
            return;
        }
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if (!$quote->getId()) {
            return;
        }

        if (isset($post['address_id']) && $quote->getCustomer()->getId()) {
            Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
            return;
        }

        unset($post['form_key']);
        $post['street'] = $post['street2']
            ? $post['street1'] . "\n" . $post['street2']
            : $post['street1'];
        $quote->getBillingAddress()->addData($post)->save();
        Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
    }
}
