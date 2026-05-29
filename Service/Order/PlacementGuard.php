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
    /** Session flag set while first saveOrder is in flight (blocks double-submit). */
    const SESSION_PLACEMENT_FLAG = 'bold_order_placement_in_progress';

    /** Payment additional_information key for EPS wallet order id (exact lookup). */
    const PAYMENT_ADDITIONAL_EPS_ORDER_ID = 'bold_eps_order_id';

    /**
     * @var string|null
     */
    private static $heldLockPublicId = null;

    /**
     * Predispatch saveOrder entry (logs even when payment method is skipped).
     *
     * @param string|null $paymentMethod
     * @param bool $willEvaluate
     * @return void
     */
    public static function logSaveOrderPredispatch($paymentMethod, $willEvaluate)
    {
        self::writeLog('[PlacementGuard] ' . json_encode(array(
            'event' => 'saveOrder_predispatch',
            'payment_method' => $paymentMethod,
            'will_evaluate' => $willEvaluate,
            'request_path' => self::getRequestPathSafe(),
        )));
    }

    /**
     * @return string|null
     */
    private static function getRequestPathSafe()
    {
        try {
            return Mage::app()->getRequest()->getRequestUri();
        } catch (Exception $e) {
            return null;
        } catch (Error $e) {
            return null;
        }
    }

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

        self::writeLog(
            '[DuplicateOrder] ' . $message,
            defined('Zend_Log::WARN') ? Zend_Log::WARN : 4
        );
    }

    /**
     * @param string $phase predispatch|assert_submit
     * @param string $check
     * @param string $outcome
     * @param array $context
     * @return void
     */
    private static function logPlacementCheck($phase, $check, $outcome, array $context = array())
    {
        $payload = array(
            'phase' => $phase,
            'check' => $check,
            'outcome' => $outcome,
        );

        if ($context !== array()) {
            $payload['context'] = $context;
        }

        self::writeLog('[PlacementGuard] ' . json_encode($payload));
    }

    /**
     * Same logging path as Bold API logs: bold_checkout_payment_booster.log with forceLog.
     *
     * @param string $message
     * @param int $level
     * @return void
     */
    private static function writeLog($message, $level = null)
    {
        if (!self::isBoldLoggingEnabled()) {
            return;
        }

        if ($level === null) {
            $level = defined('Zend_Log::DEBUG') ? Zend_Log::DEBUG : 7;
        }

        Mage::log(
            $message,
            $level,
            Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
            true
        );
    }

    /**
     * @return bool
     */
    private static function isBoldLoggingEnabled()
    {
        if (!class_exists('Bold_CheckoutPaymentBooster_Model_Config', false)) {
            return false;
        }

        try {
            /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
            $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
            if (!$config) {
                return false;
            }

            return $config->isLogEnabled(self::resolveWebsiteIdForLogging());
        } catch (Exception $e) {
            return false;
        } catch (Error $e) {
            return false;
        }
    }

    /**
     * @return int
     */
    private static function resolveWebsiteIdForLogging()
    {
        try {
            /** @var Mage_Checkout_Model_Session $session */
            $session = Mage::getSingleton('checkout/session');
            $quote = $session->getQuote();
            if ($quote && $quote->getId() && $quote->getStore()) {
                return (int) $quote->getStore()->getWebsiteId();
            }
        } catch (Exception $e) {
            // fall through
        } catch (Error $e) {
            // fall through
        }

        return (int) Mage::app()->getStore()->getWebsiteId();
    }

    /**
     * @param Mage_Sales_Model_Quote|null $quote
     * @param string|null $publicOrderId
     * @param string|null $epsOrderId
     * @param Mage_Checkout_Model_Session $session
     * @return array
     */
    private static function buildPlacementContext($quote, $publicOrderId, $epsOrderId, Mage_Checkout_Model_Session $session)
    {
        return array(
            'quote_id' => $quote && $quote->getId() ? (int) $quote->getId() : null,
            'quote_active' => $quote && $quote->getId() ? (bool) $quote->getIsActive() : null,
            'public_order_id' => $publicOrderId !== null && $publicOrderId !== '' ? $publicOrderId : null,
            'eps_order_id' => $epsOrderId !== null && $epsOrderId !== '' ? $epsOrderId : null,
            'placement_in_progress' => (bool) $session->getData(self::SESSION_PLACEMENT_FLAG),
            'payment_method' => self::getPaymentMethodFromRequestSafe(),
        );
    }

    /**
     * @return string|null
     */
    private static function getPaymentMethodFromRequestSafe()
    {
        if (!method_exists('Mage', 'app')) {
            return null;
        }

        try {
            return self::getPaymentMethodFromRequest();
        } catch (Exception $e) {
            return null;
        } catch (Error $e) {
            return null;
        }
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
        if ($epsOrderId === '') {
            return null;
        }

        /** @var Mage_Sales_Model_Resource_Order_Payment_Collection $collection */
        $collection = Mage::getModel('sales/order_payment')->getCollection();
        $collection->addFieldToFilter('method', array('in' => self::getBoldPaymentMethodCodes()));
        $collection->addFieldToFilter(
            'additional_information',
            array('like' => '%' . self::PAYMENT_ADDITIONAL_EPS_ORDER_ID . '%')
        );
        $collection->setPageSize(50);

        foreach ($collection as $payment) {
            if ($payment->getAdditionalInformation(self::PAYMENT_ADDITIONAL_EPS_ORDER_ID) === $epsOrderId) {
                $order = Mage::getModel('sales/order')->load($payment->getParentId());

                return $order->getId() ? $order : null;
            }
        }

        return null;
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
     * Core predispatch decision for SaveOrderObserver (standard checkout).
     *
     * @return array{action:string,order?:Mage_Sales_Model_Order,message?:string}
     */
    public static function evaluatePlacementRequest()
    {
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');

        return self::evaluatePlacementRequestForQuote(
            $session,
            $session->getQuote(),
            self::getEpsOrderIdFromRequest(),
            Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId()
        );
    }

    /**
     * Testable predispatch evaluation with injectable session/quote.
     *
     * @param Mage_Checkout_Model_Session $session
     * @param Mage_Sales_Model_Quote|null $quote
     * @param string|null $epsOrderId
     * @param string|null $publicOrderId
     * @return array{action:string,order?:Mage_Sales_Model_Order,message?:string}
     */
    public static function evaluatePlacementRequestForQuote(
        Mage_Checkout_Model_Session $session,
        $quote,
        $epsOrderId = null,
        $publicOrderId = null,
        $phase = 'predispatch'
    ) {
        $baseContext = self::buildPlacementContext($quote, $publicOrderId, $epsOrderId, $session);
        self::logPlacementCheck($phase, 'start', 'evaluating', $baseContext);

        if (!$quote || !$quote->getId()) {
            self::logPlacementCheck($phase, 'quote_exists', 'block', array('reason' => 'missing_quote'));

            return array(
                'action' => 'block',
                'message' => Mage::helper('checkout')->__('Your shopping cart could not be found.'),
            );
        }

        if (!$quote->getIsActive()) {
            $existingOrder = self::findOrderByQuoteId($quote->getId());
            self::logPlacementCheck($phase, 'quote_active', 'inactive', array(
                'existing_order_id' => $existingOrder ? (int) $existingOrder->getId() : null,
                'existing_increment_id' => $existingOrder ? $existingOrder->getIncrementId() : null,
            ));

            if ($existingOrder) {
                $result = Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::buildSuccessExistingEvaluation(
                    $existingOrder,
                    $quote,
                    'inactive_quote'
                );
                self::logPlacementCheck($phase, 'inactive_quote_ownership', $result['action'], array(
                    'context' => 'inactive_quote',
                    'existing_increment_id' => $existingOrder->getIncrementId(),
                ));

                return $result;
            }

            return array(
                'action' => 'block',
                'message' => Mage::helper('checkout')->__('Your shopping cart is no longer active.'),
            );
        }

        self::logPlacementCheck($phase, 'quote_active', 'pass', array('quote_id' => (int) $quote->getId()));

        if ($session->getData(self::SESSION_PLACEMENT_FLAG)) {
            self::logPlacementCheck($phase, 'session_placement_flag', 'block_in_progress', array(
                'flag' => self::SESSION_PLACEMENT_FLAG,
            ));

            return array(
                'action' => 'block_in_progress',
                'message' => Mage::helper('checkout')->__('Your order is already being placed. Please wait.'),
            );
        }

        self::logPlacementCheck($phase, 'session_placement_flag', 'pass');

        if ($publicOrderId) {
            $existingOrder = self::findOrderByPublicId($publicOrderId);
            self::logPlacementCheck($phase, 'public_order_id_lookup', $existingOrder ? 'found' : 'not_found', array(
                'public_order_id' => $publicOrderId,
                'existing_increment_id' => $existingOrder ? $existingOrder->getIncrementId() : null,
            ));

            if ($existingOrder) {
                self::logDuplicateOrderAttempt($publicOrderId, 'predispatch');

                $result = Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::buildSuccessExistingEvaluation(
                    $existingOrder,
                    $quote,
                    'public_order_id'
                );
                self::logPlacementCheck($phase, 'public_order_id_ownership', $result['action'], array(
                    'context' => 'public_order_id',
                ));

                return $result;
            }

            $lockAcquired = self::acquireLock($publicOrderId);
            self::logPlacementCheck($phase, 'mysql_get_lock', $lockAcquired ? 'acquired' : 'failed', array(
                'public_order_id' => $publicOrderId,
            ));

            if (!$lockAcquired) {
                return array(
                    'action' => 'block_in_progress',
                    'message' => Mage::helper('checkout')->__('Your order is already being placed. Please wait.'),
                );
            }

            self::$heldLockPublicId = $publicOrderId;

            $existingOrder = self::findOrderByPublicId($publicOrderId);
            self::logPlacementCheck($phase, 'public_order_id_lookup_after_lock', $existingOrder ? 'found' : 'not_found', array(
                'public_order_id' => $publicOrderId,
                'existing_increment_id' => $existingOrder ? $existingOrder->getIncrementId() : null,
            ));

            if ($existingOrder) {
                self::releaseLock();

                $result = Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::buildSuccessExistingEvaluation(
                    $existingOrder,
                    $quote,
                    'public_order_id_after_lock'
                );
                self::logPlacementCheck($phase, 'public_order_id_ownership', $result['action'], array(
                    'context' => 'public_order_id_after_lock',
                ));

                return $result;
            }
        } else {
            self::logPlacementCheck($phase, 'public_order_id_lookup', 'skipped', array('reason' => 'no_public_order_id'));
        }

        if ($epsOrderId) {
            $existingOrder = self::findOrderByEpsOrderId($epsOrderId);
            self::logPlacementCheck($phase, 'eps_order_id_lookup', $existingOrder ? 'found' : 'not_found', array(
                'eps_order_id' => $epsOrderId,
                'existing_increment_id' => $existingOrder ? $existingOrder->getIncrementId() : null,
            ));

            if ($existingOrder) {
                self::releaseLock();

                $result = Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::buildSuccessExistingEvaluation(
                    $existingOrder,
                    $quote,
                    'eps_order_id'
                );
                self::logPlacementCheck($phase, 'eps_order_id_ownership', $result['action'], array(
                    'context' => 'eps_order_id',
                ));

                return $result;
            }
        } else {
            self::logPlacementCheck($phase, 'eps_order_id_lookup', 'skipped', array('reason' => 'no_eps_order_id'));
        }

        $session->setData(self::SESSION_PLACEMENT_FLAG, 1);
        self::logPlacementCheck($phase, 'final', 'allow', array('placement_flag_set' => true));

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
        $phase = 'assert_submit';
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $publicOrderId = Bold_CheckoutPaymentBooster_Service_Bold::getPublicOrderId();
        $epsOrderId = self::getEpsOrderIdFromRequest();

        self::logPlacementCheck(
            $phase,
            'start',
            'evaluating',
            self::buildPlacementContext($quote, $publicOrderId, $epsOrderId, $session)
        );

        $alreadyUsedMessage = Mage::helper('checkout')->__(
            'This Bold payment has already been used to place an order.'
        );

        if ($publicOrderId) {
            $existingOrder = self::findOrderByPublicId($publicOrderId);
            self::logPlacementCheck($phase, 'public_order_id_lookup', $existingOrder ? 'found' : 'not_found', array(
                'public_order_id' => $publicOrderId,
                'existing_increment_id' => $existingOrder ? $existingOrder->getIncrementId() : null,
            ));

            if ($existingOrder) {
                self::assertExistingOrderBlocksPlacement($existingOrder, $quote, $publicOrderId, 'assert_public_order_id', $alreadyUsedMessage, $phase);
            }
        } else {
            self::logPlacementCheck($phase, 'public_order_id_lookup', 'skipped', array('reason' => 'no_public_order_id'));
        }

        if ($epsOrderId) {
            $existingOrder = self::findOrderByEpsOrderId($epsOrderId);
            self::logPlacementCheck($phase, 'eps_order_id_lookup', $existingOrder ? 'found' : 'not_found', array(
                'eps_order_id' => $epsOrderId,
                'existing_increment_id' => $existingOrder ? $existingOrder->getIncrementId() : null,
            ));

            if ($existingOrder) {
                self::assertExistingOrderBlocksPlacement($existingOrder, $quote, $epsOrderId, 'assert_eps_order_id', $alreadyUsedMessage, $phase);
            }
        } else {
            self::logPlacementCheck($phase, 'eps_order_id_lookup', 'skipped', array('reason' => 'no_eps_order_id'));
        }

        if (!$quote->getIsActive()) {
            $existingOrder = self::findOrderByQuoteId($quote->getId());
            self::logPlacementCheck($phase, 'quote_active', 'inactive', array(
                'existing_order_id' => $existingOrder ? (int) $existingOrder->getId() : null,
                'existing_increment_id' => $existingOrder ? $existingOrder->getIncrementId() : null,
            ));

            if ($existingOrder) {
                $alreadyPlacedMessage = Mage::helper('checkout')->__('This order has already been placed.');
                self::assertExistingOrderBlocksPlacement(
                    $existingOrder,
                    $quote,
                    (string) $quote->getId(),
                    'assert_inactive_quote',
                    $alreadyPlacedMessage,
                    $phase
                );
            }
        } else {
            self::logPlacementCheck($phase, 'quote_active', 'pass', array('quote_id' => (int) $quote->getId()));
        }

        self::logPlacementCheck($phase, 'final', 'allow');
    }

    /**
     * @param Mage_Sales_Model_Order $existingOrder
     * @param Mage_Sales_Model_Quote $quote
     * @param string $logId
     * @param string $context
     * @param string $message
     * @return void
     * @throws Mage_Core_Exception
     */
    private static function assertExistingOrderBlocksPlacement(
        Mage_Sales_Model_Order $existingOrder,
        Mage_Sales_Model_Quote $quote,
        $logId,
        $context,
        $message,
        $phase = 'assert_submit'
    ) {
        $belongs = Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::orderBelongsToCheckoutSession(
            $existingOrder,
            $quote
        );

        self::logPlacementCheck($phase, 'session_ownership', $belongs ? 'owned' : 'foreign', array(
            'context' => $context,
            'existing_increment_id' => $existingOrder->getIncrementId(),
            'existing_quote_id' => (int) $existingOrder->getQuoteId(),
            'quote_id' => (int) $quote->getId(),
        ));

        if (!$belongs) {
            self::logDuplicateOrderAttempt($logId, 'assert_rejected_' . $context);
        }

        self::logPlacementCheck($phase, $context, 'block', array(
            'reason' => $belongs ? 'duplicate_same_session' : 'duplicate_foreign_session',
        ));

        self::blockPlacement($message);
    }
}
