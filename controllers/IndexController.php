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
     * @throws Mage_Core_Exception
     */
    public function syncOrderDataAction()
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
        $websiteId = $quote->getStore()->getWebsiteId();
        if (!$quote->getId()) {
            Mage::throwException('No quote found.');
        }
        if (isset($post['address_id']) && $quote->getCustomer()->getId()) {
            try {
                Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
            } catch (Exception $exception) {
                Bold_CheckoutPaymentBooster_Service_LogManager::log(
                    'ERROR: Hydrate order data failed. ' . $exception->getMessage(),
                    $websiteId
                );
            }
            return;
        }

        $post['street'] = $post['street2']
            ? $post['street1'] . "\n" . $post['street2']
            : $post['street1'];

        try {
            $quote->getBillingAddress()->addData($post)->save();
            Bold_CheckoutPaymentBooster_Service_Order_Hydrate::hydrate($quote);
        } catch (Exception $exception) {
            Bold_CheckoutPaymentBooster_Service_LogManager::log(
                'ERROR: Hydrate order data failed. ' . $exception->getMessage(),
                $websiteId
            );
        }
    }
}
