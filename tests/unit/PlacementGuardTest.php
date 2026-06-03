<?php

use PHPUnit\Framework\TestCase;

class PlacementGuardTest extends TestCase
{
    protected function setUp(): void
    {
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::reset();
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            Bold_CheckoutPaymentBooster_Model_Config::RESOURCE,
            new Bold_CheckoutPaymentBooster_Model_Config()
        );
        Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::releaseLock();
    }

    public function testEvaluateReturnsBlockWhenQuoteMissing()
    {
        $session = new Mage_Checkout_Model_Session();

        $result = Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::evaluatePlacementRequestForQuote(
            $session,
            null
        );

        $this->assertSame('block', $result['action']);
    }

    public function testEvaluateReturnsBlockInProgressWhenPlacementFlagSet()
    {
        $quote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'is_active' => 1,
        ));
        $session = new Mage_Checkout_Model_Session(array(
            'quote' => $quote,
            Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::SESSION_PLACEMENT_FLAG => 1,
        ));

        $result = Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::evaluatePlacementRequestForQuote(
            $session,
            $quote
        );

        $this->assertSame('block_in_progress', $result['action']);
    }

    public function testEvaluateReturnsAllowForActiveQuoteWithoutDuplicates()
    {
        $quote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'is_active' => 1,
        ));
        $session = new Mage_Checkout_Model_Session(array('quote' => $quote));

        $result = Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::evaluatePlacementRequestForQuote(
            $session,
            $quote,
            null,
            null
        );

        $this->assertSame('allow', $result['action']);
        $this->assertSame(1, $session->getData(Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::SESSION_PLACEMENT_FLAG));
    }

    public function testEvaluateReturnsSuccessExistingForOwnedInactiveQuote()
    {
        $quote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'is_active' => 0,
            'customer_id' => 1,
        ));
        $order = new Mage_Sales_Model_Order(array(
            'entity_id' => 100,
            'quote_id' => 5,
            'increment_id' => '100000099',
            'customer_id' => 1,
        ));
        $session = new Mage_Checkout_Model_Session(array('quote' => $quote));

        $orderModel = new class($order) extends Varien_Object {
            private $order;

            public function __construct($order)
            {
                $this->order = $order;
            }

            public function loadByAttribute($code, $value)
            {
                return $this->order;
            }
        };
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setModel('sales/order', $orderModel);

        $result = Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::evaluatePlacementRequestForQuote(
            $session,
            $quote
        );

        $this->assertSame('success_existing', $result['action']);
        $this->assertSame($order, $result['order']);
    }

    public function testEvaluateBlocksSuccessExistingForForeignInactiveQuoteOrder()
    {
        $quote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'is_active' => 0,
            'customer_id' => 2,
            'customer_email' => 'bob@example.com',
        ));
        $order = new Mage_Sales_Model_Order(array(
            'entity_id' => 100,
            'quote_id' => 99,
            'increment_id' => '100000100',
            'customer_id' => 1,
            'customer_email_direct' => 'alice@example.com',
        ));
        $session = new Mage_Checkout_Model_Session(array('quote' => $quote));

        $orderModel = new class($order) extends Varien_Object {
            private $order;

            public function __construct($order)
            {
                $this->order = $order;
            }

            public function loadByAttribute($code, $value)
            {
                return $this->order;
            }
        };
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setModel('sales/order', $orderModel);

        $result = Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::evaluatePlacementRequestForQuote(
            $session,
            $quote
        );

        $this->assertSame('block', $result['action']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testEvaluateRejectsForeignWalletEpsOrderId()
    {
        $quote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'is_active' => 1,
        ));
        $session = new Mage_Checkout_Model_Session(array(
            'quote' => $quote,
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::SESSION_WALLET_EPS_ORDER_ID => 'eps-mine',
        ));
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton('checkout/session', $session);

        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionCode(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::EXCEPTION_QUOTE_ACCESS_DENIED
        );

        Bold_CheckoutPaymentBooster_Service_Order_PlacementGuard::evaluatePlacementRequestForQuote(
            $session,
            $quote,
            'eps-theirs'
        );
    }
}
