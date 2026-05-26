<?php

/**
 * Prevents duplicate Magento orders for the same Bold checkout session or Express Pay order.
 *
 * CHANGES vs main: new file. Used by SaveOrderObserver (predispatch saveOrder) and CheckoutObserver.
 *
 * Flow:
 * 1. predispatch saveOrder → evaluatePlacementRequest() → allow | block | success_existing (redirect JSON).
 * 2. checkout_type_onepage_save_order → assertQuoteCanSubmit() before Magento creates order.
 *
 * Identifiers checked: Bold public_order_id (session), payment[additional_data][order_id] (wallet/EPS),
 * quote_id, MySQL GET_LOCK per public order id.
 */
class Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard
{
    /** [vs main] Session flag set while first saveOrder is in flight (blocks double-submit / FC reload). */
    const SESSION_PLACEMENT_FLAG = 'bold_order_placement_in_progress';

    /** [vs main] Log file for duplicate placement attempts (var/log/). */
    const DUPLICATE_ORDER_LOG_FILE = 'bold_checkout_payment_booster_duplicate_order.log';

    /**
     * @var string|null
     */
    private static $heldLockPublicId = null;

    /**
     * @return string[]
     */
    public static function getBoldPaymentMethodCodes()
    {
        return array(
            Bold_CheckoutPaymentBooster_Model_Payment_Bold::CODE,
            Bold_CheckoutPaymentBooster_Model_Payment_Fastlane::CODE,
        );
    }

    /**
     * @param string|null $method
     * @return bool
     */
    public static function isBoldPaymentMethod($method)
    {
        return $method && in_array($method, self::getBoldPaymentMethodCodes(), true);
    }

    /**
     * @return string|null
     */
    public static function getPaymentMethodFromRequest()
    {
        $payment = Mage::app()->getRequest()->getParam('payment');
        if (is_array($payment) && !empty($payment['method'])) {
            return $payment['method'];
        }

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if ($quote && $quote->getId() && $quote->getPayment()) {
            return $quote->getPayment()->getMethod();
        }

        return null;
    }

    /**
     * [vs main] Wallet/Express Pay order id from payment[additional_data][order_id] on saveOrder POST.
     *
     * @return string|null
     */
    public static function getEpsOrderIdFromRequest()
    {
        $payment = Mage::app()->getRequest()->getParam('payment');
        if (!is_array($payment) || empty($payment['additional_data'])) {
            return null;
        }

        $additionalData = $payment['additional_data'];
        if (is_string($additionalData)) {
            parse_str($additionalData, $additionalData);
        }

        if (!is_array($additionalData) || empty($additionalData['order_id'])) {
            return null;
        }

        return (string) $additionalData['order_id'];
    }

    /**
     * @param string $publicOrderId
     * @return Mage_Sales_Model_Order|null
     */
    public static function findOrderByPublicId($publicOrderId)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Resource_Order_Collection $collection */
        $collection = Mage::getModel('bold_checkout_payment_booster/order')->getCollection();
        $collection->addFieldToFilter('public_id', $publicOrderId);
        $collection->addFieldToFilter('order_id', array('notnull' => true));
        $collection->setPageSize(1);

        $mapping = $collection->getFirstItem();
        if (!$mapping->getId() || !$mapping->getOrderId()) {
            return null;
        }

        $order = Mage::getModel('sales/order')->load($mapping->getOrderId());

        return $order->getId() ? $order : null;
    }

    /**
     * @param string $publicOrderId
     * @param string $context
     * @return void
     */
    public static function logDuplicateOrderAttempt($publicOrderId, $context = '')
    {
        if (!$publicOrderId) {
            return;
        }

        $message = sprintf('order %s was attempt to duplicate', $publicOrderId);
        if ($context !== '') {
            $message .= ' [' . $context . ']';
        }

        Mage::log($message, Zend_Log::WARN, self::DUPLICATE_ORDER_LOG_FILE);
    }

    /**
     * @param int $quoteId
     * @return Mage_Sales_Model_Order|null
     */
    public static function findOrderByQuoteId($quoteId)
    {
        $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $quoteId);

        return $order->getId() ? $order : null;
    }

    /**
     * @param string $epsOrderId
     * @return Mage_Sales_Model_Order|null
     */
    public static function findOrderByEpsOrderId($epsOrderId)
    {
        /** @var Mage_Sales_Model_Resource_Order_Payment_Collection $collection */
        $collection = Mage::getModel('sales/order_payment')->getCollection();
        $collection->addFieldToFilter('method', array('in' => self::getBoldPaymentMethodCodes()));
        $collection->addFieldToFilter('additional_information', array('like' => '%' . $epsOrderId . '%'));
        $collection->setPageSize(1);

        $payment = $collection->getFirstItem();
        if (!$payment->getId() || !$payment->getParentId()) {
            return null;
        }

        $order = Mage::getModel('sales/order')->load($payment->getParentId());

        return $order->getId() ? $order : null;
    }

    /**
     * [vs main] MySQL advisory lock to serialize concurrent saveOrder for same Bold public order id.
     *
     * @param string $publicOrderId
     * @return bool
     */
    public static function acquireLock($publicOrderId)
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $lockName = 'bold_checkout_place_' . md5($publicOrderId);
        $result = $connection->fetchOne(
            'SELECT GET_LOCK(?, 10)',
            array($lockName)
        );

        return (int) $result === 1;
    }

    /**
     * @param string|null $publicOrderId
     * @return void
     */
    public static function releaseLock($publicOrderId = null)
    {
        $publicOrderId = $publicOrderId ?: self::$heldLockPublicId;
        if (!$publicOrderId) {
            return;
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $lockName = 'bold_checkout_place_' . md5($publicOrderId);
        $connection->query('SELECT RELEASE_LOCK(?)', array($lockName));

        if (self::$heldLockPublicId === $publicOrderId) {
            self::$heldLockPublicId = null;
        }
    }

    /**
     * [vs main] Core predispatch decision for SaveOrderObserver (FC + standard checkout).
     *
     * @return array{action:string,order?:Mage_Sales_Model_Order,message?:string}
     */
    public static function evaluatePlacementRequest()
    {
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $quote = $session->getQuote();

        if (!$quote || !$quote->getId()) {
            return array(
                'action' => 'block',
                'message' => Mage::helper('checkout')->__('Your shopping cart could not be found.'),
            );
        }

        if (!$quote->getIsActive()) {
            $existingOrder = self::findOrderByQuoteId($quote->getId());
            if ($existingOrder) {
                return array(
                    'action' => 'success_existing',
                    'order' => $existingOrder,
                );
            }

            return array(
                'action' => 'block',
                'message' => Mage::helper('checkout')->__('Your shopping cart is no longer active.'),
            );
        }

        if ($session->getData(self::SESSION_PLACEMENT_FLAG)) {
            return array(
                'action' => 'block_in_progress',
                'message' => Mage::helper('checkout')->__('Your order is already being placed. Please wait.'),
            );
        }

        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
        $epsOrderId = self::getEpsOrderIdFromRequest();

        if ($publicOrderId) {
            $existingOrder = self::findOrderByPublicId($publicOrderId);
            if ($existingOrder) {
                self::logDuplicateOrderAttempt($publicOrderId, 'predispatch');

                return array(
                    'action' => 'success_existing',
                    'order' => $existingOrder,
                );
            }

            if (!self::acquireLock($publicOrderId)) {
                return array(
                    'action' => 'block_in_progress',
                    'message' => Mage::helper('checkout')->__('Your order is already being placed. Please wait.'),
                );
            }

            self::$heldLockPublicId = $publicOrderId;

            $existingOrder = self::findOrderByPublicId($publicOrderId);
            if ($existingOrder) {
                self::releaseLock();

                return array(
                    'action' => 'success_existing',
                    'order' => $existingOrder,
                );
            }
        }

        if ($epsOrderId) {
            $existingOrder = self::findOrderByEpsOrderId($epsOrderId);
            if ($existingOrder) {
                self::releaseLock();

                return array(
                    'action' => 'success_existing',
                    'order' => $existingOrder,
                );
            }
        }

        $session->setData(self::SESSION_PLACEMENT_FLAG, 1);

        return array('action' => 'allow');
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return void
     */
    public static function prepareCheckoutSessionForExistingOrder(Mage_Sales_Model_Order $order)
    {
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $session->setLastQuoteId($order->getQuoteId());
        $session->setLastSuccessQuoteId($order->getQuoteId());
        $session->setLastOrderId($order->getId());
        $session->setLastRealOrderId($order->getIncrementId());
        $session->setRedirectUrl(null);
    }

    /**
     * [vs main] Duplicate saveOrder: return JSON success + redirect (no second Magento order).
     *
     * @param Mage_Core_Controller_Varien_Action $controller
     * @param Mage_Sales_Model_Order $order
     * @return void
     */
    public static function respondWithExistingOrderSuccess($controller, Mage_Sales_Model_Order $order)
    {
        self::prepareCheckoutSessionForExistingOrder($order);
        self::clearPlacementState();

        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $redirectUrl = $checkoutSession->getRedirectUrl();
        if (!$redirectUrl) {
            $redirectUrl = Mage::getUrl('checkout/onepage/success');
        }

        $result = array(
            'success' => true,
            'error' => false,
            'redirect' => $redirectUrl,
        );

        $controller->getResponse()
            ->clearHeaders()
            ->setHeader('Content-type', 'application/json', true)
            ->setBody(Mage::helper('core')->jsonEncode($result));

        $controller->setFlag(
            '',
            Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH,
            true
        );
    }

    /**
     * @return void
     */
    public static function clearPlacementState()
    {
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $session->unsetData(self::SESSION_PLACEMENT_FLAG);
        self::releaseLock();
    }

    /**
     * @param string $message
     * @throws Mage_Core_Exception
     * @return void
     */
    public static function blockPlacement($message)
    {
        Mage::throwException($message);
    }

    /**
     * Second line of defense during order submission.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function assertQuoteCanSubmit(Mage_Sales_Model_Quote $quote)
    {
        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
        if ($publicOrderId) {
            $existingOrder = self::findOrderByPublicId($publicOrderId);
            if ($existingOrder) {
                self::blockPlacement(
                    Mage::helper('checkout')->__('This Bold payment has already been used to place an order.')
                );
            }
        }

        $epsOrderId = self::getEpsOrderIdFromRequest();
        if ($epsOrderId) {
            $existingOrder = self::findOrderByEpsOrderId($epsOrderId);
            if ($existingOrder) {
                self::blockPlacement(
                    Mage::helper('checkout')->__('This Bold payment has already been used to place an order.')
                );
            }
        }

        if (!$quote->getIsActive()) {
            $existingOrder = self::findOrderByQuoteId($quote->getId());
            if ($existingOrder) {
                self::blockPlacement(
                    Mage::helper('checkout')->__('This order has already been placed.')
                );
            }
        }
    }
}
