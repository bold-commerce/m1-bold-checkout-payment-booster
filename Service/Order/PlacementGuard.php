<?php

/**
 * Prevents duplicate Magento orders for the same Bold public_order_id.
 */
class Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard
{
    const SESSION_PLACEMENT_FLAG = 'bold_order_placement_in_progress';

    const SESSION_PAYMENT_AUTH_KEY = 'bold_payment_auth_public_order_id';

    const LOCK_TIMEOUT_SECONDS = 0;

    const LOCK_BLOCKING_TIMEOUT_SECONDS = 60;

    const WAIT_FOR_ORDER_MAX_ATTEMPTS = 100;

    const WAIT_FOR_ORDER_SLEEP_MICROSECONDS = 100000;

    /**
     * @var string|null
     */
    private static $heldLockPublicId = null;

    /**
     * @var array<string, Mage_Sales_Model_Order|null>
     */
    private static $orderByPublicIdCache = array();

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
     * @param string $publicOrderId
     * @return bool
     */
    public static function hasPaymentAuthForPublicOrderId($publicOrderId)
    {
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');

        return $session->getData(self::SESSION_PAYMENT_AUTH_KEY) === $publicOrderId;
    }

    /**
     * @param string $publicOrderId
     * @return void
     */
    public static function markPaymentAuthForPublicOrderId($publicOrderId)
    {
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $session->setData(self::SESSION_PAYMENT_AUTH_KEY, $publicOrderId);
    }

    /**
     * @param string $publicOrderId
     * @return Mage_Sales_Model_Order|null
     */
    public static function findOrderByPublicId($publicOrderId)
    {
        if (array_key_exists($publicOrderId, self::$orderByPublicIdCache)) {
            return self::$orderByPublicIdCache[$publicOrderId];
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton('core/resource')->getTableName(
            Bold_CheckoutPaymentBooster_Model_Order::RESOURCE
        );
        $orderId = $connection->fetchOne(
            $connection->select()
                ->from($tableName, array('order_id'))
                ->where('public_id = ?', $publicOrderId)
                ->where('order_id IS NOT NULL')
                ->limit(1)
        );

        if (!$orderId) {
            self::$orderByPublicIdCache[$publicOrderId] = null;

            return null;
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        $result = $order->getId() ? $order : null;
        self::$orderByPublicIdCache[$publicOrderId] = $result;

        return $result;
    }

    /**
     * @param string $publicOrderId
     * @param int $timeoutSeconds
     * @return bool
     */
    public static function acquireLock($publicOrderId, $timeoutSeconds = null)
    {
        if ($timeoutSeconds === null) {
            $timeoutSeconds = self::LOCK_TIMEOUT_SECONDS;
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $lockName = 'bold_checkout_place_' . md5($publicOrderId);
        $result = $connection->fetchOne(
            'SELECT GET_LOCK(?, ?)',
            array($lockName, (int) $timeoutSeconds)
        );

        return (int) $result === 1;
    }

    /**
     * @param int $quoteId
     * @return Mage_Sales_Model_Order|null
     */
    public static function findOrderByQuoteId($quoteId)
    {
        if (!$quoteId) {
            return null;
        }

        $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $quoteId);

        return $order->getId() ? $order : null;
    }

    /**
     * @param Mage_Core_Controller_Varien_Action|null $controller
     * @return Mage_Sales_Model_Order|null
     */
    public static function findExistingOrderForCheckout($controller = null)
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if ($quote && $quote->getId()) {
            $order = self::findOrderByQuoteId($quote->getId());
            if ($order) {
                return $order;
            }
        }

        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
        if ($publicOrderId) {
            return self::findOrderByPublicId($publicOrderId);
        }

        return null;
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
     * Predispatch: seamless success when order already exists or finishes while waiting.
     *
     * @param Mage_Core_Controller_Varien_Action $controller
     * @return void
     */
    public static function handleSaveOrderPredispatch(Mage_Core_Controller_Varien_Action $controller)
    {
        $existingOrder = self::findExistingOrderForCheckout($controller);
        if ($existingOrder) {
            self::completeWithExistingOrder($controller, $existingOrder, 'DUPLICATE_ORDER_SEAMLESS_PREDISPATCH');
        }
    }

    /**
     * Runs once per order submission (checkout_type_onepage_save_order).
     *
     * @return void
     */
    public static function assertCanPlaceOrder()
    {
        self::resolveDuplicatePlacement(null);
    }

    /**
     * Block duplicate placement; redirect seamlessly when an order already exists.
     *
     * @param Mage_Core_Controller_Varien_Action|null $controller
     * @return void
     */
    public static function resolveDuplicatePlacement($controller = null)
    {
        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $quoteId = $quote && $quote->getId() ? (int) $quote->getId() : null;

        $existingOrder = self::findExistingOrderForCheckout($controller);
        if ($existingOrder) {
            self::completeWithExistingOrder($controller, $existingOrder, 'DUPLICATE_ORDER_SEAMLESS_EXISTING');

            return;
        }

        if (!$publicOrderId) {
            return;
        }

        if (self::waitAndAcquirePlacementLock($publicOrderId, $quoteId)) {
            /** @var Mage_Checkout_Model_Session $session */
            $session = Mage::getSingleton('checkout/session');
            $session->setData(self::SESSION_PLACEMENT_FLAG, 1);

            $existingOrder = self::findExistingOrderForCheckout($controller);
            if ($existingOrder) {
                self::releaseLock($publicOrderId);
                self::completeWithExistingOrder($controller, $existingOrder, 'DUPLICATE_ORDER_SEAMLESS_AFTER_LOCK');

                return;
            }

            return;
        }

        $existingOrder = self::findExistingOrderForCheckout($controller);
        if ($existingOrder) {
            self::completeWithExistingOrder($controller, $existingOrder, 'DUPLICATE_ORDER_SEAMLESS_AFTER_WAIT');

            return;
        }

        self::logDuplicateOrderAttempt('DUPLICATE_ORDER_WAITING_BLOCKING_LOCK', array(
            'quote_id' => $quoteId,
        ));

        if (self::acquireLock($publicOrderId, self::LOCK_BLOCKING_TIMEOUT_SECONDS)) {
            self::$heldLockPublicId = $publicOrderId;
            Mage::getSingleton('checkout/session')->setData(self::SESSION_PLACEMENT_FLAG, 1);

            $existingOrder = self::findExistingOrderForCheckout($controller);
            if ($existingOrder) {
                self::releaseLock($publicOrderId);
                self::completeWithExistingOrder($controller, $existingOrder, 'DUPLICATE_ORDER_SEAMLESS_AFTER_LOCK');
            }
        }
    }

    /**
     * @param string $publicOrderId
     * @param int|null $quoteId
     * @return bool
     */
    public static function waitAndAcquirePlacementLock($publicOrderId, $quoteId = null)
    {
        for ($attempt = 0; $attempt < self::WAIT_FOR_ORDER_MAX_ATTEMPTS; $attempt++) {
            if (self::acquireLock($publicOrderId)) {
                self::$heldLockPublicId = $publicOrderId;

                return true;
            }

            $existingOrder = self::findExistingOrderForCheckout();
            if ($existingOrder) {
                return false;
            }

            if ($quoteId) {
                $order = self::findOrderByQuoteId($quoteId);
                if ($order) {
                    return false;
                }
            }

            usleep(self::WAIT_FOR_ORDER_SLEEP_MICROSECONDS);
        }

        if (self::acquireLock($publicOrderId, self::LOCK_BLOCKING_TIMEOUT_SECONDS)) {
            self::$heldLockPublicId = $publicOrderId;

            return true;
        }

        return false;
    }

    /**
     * @param string $publicOrderId
     * @return Mage_Sales_Model_Order|null
     */
    public static function waitForOrderByPublicId($publicOrderId)
    {
        for ($attempt = 0; $attempt < self::WAIT_FOR_ORDER_MAX_ATTEMPTS; $attempt++) {
            unset(self::$orderByPublicIdCache[$publicOrderId]);
            $existingOrder = self::findOrderByPublicId($publicOrderId);
            if ($existingOrder) {
                return $existingOrder;
            }

            $quote = Mage::getSingleton('checkout/session')->getQuote();
            if ($quote && $quote->getId()) {
                $order = self::findOrderByQuoteId($quote->getId());
                if ($order) {
                    return $order;
                }
            }

            usleep(self::WAIT_FOR_ORDER_SLEEP_MICROSECONDS);
        }

        return null;
    }

    /**
     * @param Mage_Core_Controller_Varien_Action|null $controller
     * @param Mage_Sales_Model_Order $order
     * @param string $event
     * @return void
     */
    public static function completeWithExistingOrder($controller, Mage_Sales_Model_Order $order, $event)
    {
        self::logDuplicateOrderAttempt($event, array(
            'existing_magento_order_id' => $order->getId(),
            'existing_magento_increment_id' => $order->getIncrementId(),
        ));
        self::prepareCheckoutSessionForExistingOrder($order);
        self::clearPlacementState();

        if ($controller === null) {
            $controller = Mage::app()->getFrontController()->getAction();
        }

        if ($controller && self::isSaveOrderControllerAction($controller)) {
            self::respondWithExistingOrderSuccess($controller, $order);
            exit;
        }
    }

    /**
     * @param Mage_Core_Controller_Varien_Action $controller
     * @return bool
     */
    public static function isSaveOrderControllerAction(Mage_Core_Controller_Varien_Action $controller)
    {
        return $controller->getRequest()->getActionName() === 'saveOrder';
    }

    /**
     * @param Mage_Core_Controller_Varien_Action $controller
     * @param Mage_Sales_Model_Order $order
     * @return void
     */
    public static function respondWithExistingOrderSuccess($controller, Mage_Sales_Model_Order $order)
    {
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
     * @return void
     */
    public static function clearPlacementState()
    {
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $session->unsetData(self::SESSION_PLACEMENT_FLAG);
        $session->unsetData(self::SESSION_PAYMENT_AUTH_KEY);
        self::releaseLock();
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
     * @param string $event
     * @param array $context
     * @return void
     */
    public static function logDuplicateOrderAttempt($event, array $context = array())
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        if (!$config->isLogEnabled($websiteId)) {
            return;
        }

        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $request = Mage::app()->getRequest();
        $paymentMethod = null;
        if ($quote && $quote->getId() && $quote->getPayment()) {
            $paymentMethod = $quote->getPayment()->getMethod();
        }

        $parts = array(
            'event=' . $event,
            'public_order_id=' . ($publicOrderId ?: ''),
            'quote_id=' . ($quote && $quote->getId() ? $quote->getId() : ''),
            'payment_method=' . ($paymentMethod ?: ''),
            'route=' . $request->getModuleName()
                . '/' . $request->getControllerName()
                . '/' . $request->getActionName(),
        );

        foreach ($context as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        Mage::log(
            'DUPLICATE_ORDER_GUARD ' . implode(' ', $parts),
            Zend_Log::WARN,
            Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME
        );
    }
}
