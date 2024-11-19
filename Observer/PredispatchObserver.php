<?php

/**
 * Observer for `controller_action_predispatch` event
 *
 * @see Mage_Core_Controller_Varien_Action::preDispatch
 */
class Bold_CheckoutPaymentBooster_Observer_PredispatchObserver
{
    private $allowedActions = [
        'cms_index_index',
        'cms_index_noRoute',
        'cms_page_view',
        'catalog_category_view',
        'catalog_product_view',
        'checkout_cart_index',
        'checkout_onepage_index',
        'customer_account_create',
        'customer_account_login',
        'customer_account_index',
        'customer_account_edit',
        'customer_account_logoutsuccess',
        'customer_account_confirmation',
        'customer_account_forgotpassword',
        'customer_account_changeforgotten',
        'customer_address_index',
        'customer_address_new',
        'customer_address_form',
        'sales_guest_form',
        'sales_order_history',
        'sales_billing_agreement_index',
        'sales_recurring_profile_index',
        'review_customer_index',
        'oauth_customer_token_index',
        'newsletter_manage_index',
        'downloadable_customer_products',
        'catalog_seo_sitemap_category',
        'catalogsearch_result_index',
        'catalogsearch_advanced_index',
        'catalogsearch_term_popular',
        'wishlist_index_index'
    ];

    /**
     * Initialize Bold order
     *
     * @param Varien_Event_Observer $observer
     * @return void
     * @throws Exception
     */
    public function initializeBoldOrder(Varien_Event_Observer $observer)
    {
        if (
            !in_array(
                strtolower($observer->getEvent()->getControllerAction()->getFullActionName()),
                $this->allowedActions
            )
        ) {
            return;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('checkout/cart')->getQuote();

        if (count($quote->getAllVisibleItems()) === 0) {
            return;
        }

        try {
            Bold_CheckoutPaymentBooster_Service_Bold::initBoldCheckoutData($quote);

            $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();

            if ($publicOrderId === null) {
                return;
            }
        } catch (Exception $exception) {
            Mage::log($exception->getMessage(), Zend_Log::CRIT);
        }

        Mage::getSingleton('checkout/session')->setCartWasUpdated(false);
    }
}
