<?php

/**
 * Verifies Magento orders and quotes belong to the current checkout session.
 */
class Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership
{
    const EXCEPTION_QUOTE_ACCESS_DENIED = 403;

    /** Checkout session key for EPS wallet_pay order id created in this session. */
    const SESSION_WALLET_EPS_ORDER_ID = 'bold_wallet_eps_order_id';
    const SESSION_WALLET_EPS_QUOTE_ID = 'bold_wallet_eps_quote_id';
    const SESSION_WALLET_EPS_CREATED_AT = 'bold_wallet_eps_created_at';
    /** Seconds to reuse the same wallet_pay order id for parallel createOrder calls. */
    const WALLET_EPS_COALESCE_SECONDS = 15;
    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Quote $quote
     * @return bool
     */
    public static function orderBelongsToCheckoutSession(Mage_Sales_Model_Order $order, Mage_Sales_Model_Quote $quote)
    {
        if (!$order->getId() || !$quote->getId()) {
            return false;
        }

        if ((int) $order->getQuoteId() !== (int) $quote->getId()) {
            return false;
        }

        $orderCustomerId = (int) $order->getCustomerId();
        $quoteCustomerId = (int) $quote->getCustomerId();

        if ($orderCustomerId > 0 && $quoteCustomerId > 0) {
            return $orderCustomerId === $quoteCustomerId;
        }

        $orderEmail = self::normalizeEmail(self::getOrderEmail($order));
        $quoteEmail = self::normalizeEmail((string) $quote->getCustomerEmail());

        if ($orderEmail !== '' && $quoteEmail !== '') {
            return $orderEmail === $quoteEmail;
        }

        return true;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Quote $quote
     * @param string $context
     * @return array{action:string,order?:Mage_Sales_Model_Order,message?:string}
     */
    public static function buildSuccessExistingEvaluation(
        Mage_Sales_Model_Order $order,
        Mage_Sales_Model_Quote $quote,
        $context
    ) {
        if (self::orderBelongsToCheckoutSession($order, $quote)) {
            return array(
                'action' => 'success_existing',
                'order' => $order,
            );
        }

        $logId = $order->getIncrementId() ?: (string) $order->getId();
        Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::logDuplicateOrderAttempt(
            $logId,
            'success_existing_rejected_' . $context
        );

        return array(
            'action' => 'block',
            'message' => Mage::helper('checkout')->__(
                'This Bold payment has already been used to place an order.'
            ),
        );
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function assertQuoteMatchesCheckoutSession(Mage_Sales_Model_Quote $quote)
    {
        if (!$quote->getId()) {
            Mage::throwException(
                Mage::helper('checkout')->__('Your shopping cart could not be found.')
            );
        }

        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $sessionQuoteId = (int) $session->getQuoteId();

        if ($sessionQuoteId <= 0 || (int) $quote->getId() !== $sessionQuoteId) {
            throw new Mage_Core_Exception(
                Mage::helper('checkout')->__('You are not authorized to access this cart.'),
                self::EXCEPTION_QUOTE_ACCESS_DENIED
            );
        }
    }

    /**
     * @param string|null $quoteIdParam
     * @return Mage_Sales_Model_Quote
     * @throws Mage_Core_Exception
     */
    public static function loadCheckoutSessionQuote($quoteIdParam)
    {
        if ($quoteIdParam === null || $quoteIdParam === '') {
            /** @var Mage_Sales_Model_Quote $quote */
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            self::assertQuoteMatchesCheckoutSession($quote);

            return $quote;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote')->load($quoteIdParam);

        if (!$quote->getId()) {
            Mage::throwException(
                Mage::helper('core')->__('Invalid quote ID "%s".', $quoteIdParam)
            );
        }

        self::assertQuoteMatchesCheckoutSession($quote);

        return $quote;
    }

    /**
     * Remember wallet_pay order id for this checkout session (after expresspay/createOrder).
     *
     * @param string $epsOrderId
     * @return void
     */
    public static function registerWalletEpsOrderId($epsOrderId)
    {
        if ($epsOrderId === '') {
            return;
        }

        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $session->setData(self::SESSION_WALLET_EPS_ORDER_ID, (string) $epsOrderId);
    }

    /**
     * @param int $quoteId
     * @return string
     */
    public static function getRecentWalletEpsOrderId($quoteId)
    {
        if ($quoteId <= 0) {
            return '';
        }

        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $epsOrderId = (string) $session->getData(self::SESSION_WALLET_EPS_ORDER_ID);
        $storedQuoteId = (int) $session->getData(self::SESSION_WALLET_EPS_QUOTE_ID);
        $createdAt = (int) $session->getData(self::SESSION_WALLET_EPS_CREATED_AT);

        if ($epsOrderId === ''
            || $storedQuoteId !== $quoteId
            || $createdAt <= 0
            || (time() - $createdAt) > self::WALLET_EPS_COALESCE_SECONDS) {
            return '';
        }

        return $epsOrderId;
    }

    /**
     * @param int $quoteId
     * @param string $epsOrderId
     * @return void
     */
    public static function rememberWalletEpsOrderCreate($quoteId, $epsOrderId)
    {
        if ($quoteId <= 0 || $epsOrderId === '') {
            return;
        }

        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $session->setData(self::SESSION_WALLET_EPS_ORDER_ID, (string) $epsOrderId);
        $session->setData(self::SESSION_WALLET_EPS_QUOTE_ID, $quoteId);
        $session->setData(self::SESSION_WALLET_EPS_CREATED_AT, time());
    }

    /**
     * @param string $epsOrderId
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function assertWalletEpsOrderIdBelongsToSession($epsOrderId)
    {
        if ($epsOrderId === '') {
            throw new Mage_Core_Exception(
                Mage::helper('checkout')->__('You are not authorized to access this payment.'),
                self::EXCEPTION_QUOTE_ACCESS_DENIED
            );
        }

        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $registered = (string) $session->getData(self::SESSION_WALLET_EPS_ORDER_ID);

        if ($registered === '' || $registered !== (string) $epsOrderId) {
            throw new Mage_Core_Exception(
                Mage::helper('checkout')->__('You are not authorized to access this payment.'),
                self::EXCEPTION_QUOTE_ACCESS_DENIED
            );
        }
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    private static function getOrderEmail(Mage_Sales_Model_Order $order)
    {
        $email = (string) $order->getCustomerEmail();
        if ($email !== '') {
            return $email;
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress && $billingAddress->getEmail()) {
            return (string) $billingAddress->getEmail();
        }

        return '';
    }

    /**
     * @param string $email
     * @return string
     */
    private static function normalizeEmail($email)
    {
        return strtolower(trim($email));
    }
}
