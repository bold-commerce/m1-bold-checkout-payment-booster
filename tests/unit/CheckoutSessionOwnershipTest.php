<?php

use PHPUnit\Framework\TestCase;

class CheckoutSessionOwnershipTest extends TestCase
{
    protected function setUp(): void
    {
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::reset();
    }

    public function testOrderBelongsWhenQuoteIdsMatchForGuestWithoutEmails()
    {
        $order = new Mage_Sales_Model_Order(array(
            'entity_id' => 10,
            'quote_id' => 5,
            'customer_id' => 0,
        ));
        $quote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'customer_id' => 0,
        ));

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::orderBelongsToCheckoutSession(
                $order,
                $quote
            )
        );
    }

    public function testOrderDoesNotBelongWhenQuoteIdsDiffer()
    {
        $order = new Mage_Sales_Model_Order(array(
            'entity_id' => 10,
            'quote_id' => 5,
            'customer_id' => 0,
        ));
        $quote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 99,
            'customer_id' => 0,
        ));

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::orderBelongsToCheckoutSession(
                $order,
                $quote
            )
        );
    }

    public function testLoggedInCustomerMustMatch()
    {
        $order = new Mage_Sales_Model_Order(array(
            'entity_id' => 10,
            'quote_id' => 5,
            'customer_id' => 1,
        ));
        $matchingQuote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'customer_id' => 1,
        ));
        $foreignQuote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'customer_id' => 2,
        ));

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::orderBelongsToCheckoutSession(
                $order,
                $matchingQuote
            )
        );
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::orderBelongsToCheckoutSession(
                $order,
                $foreignQuote
            )
        );
    }

    public function testGuestEmailMustMatchWhenBothPresent()
    {
        $order = new Mage_Sales_Model_Order(array(
            'entity_id' => 10,
            'quote_id' => 5,
            'customer_id' => 0,
            'customer_email_direct' => 'alice@example.com',
        ));
        $matchingQuote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'customer_id' => 0,
            'customer_email' => 'Alice@Example.com',
        ));
        $foreignQuote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'customer_id' => 0,
            'customer_email' => 'bob@example.com',
        ));

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::orderBelongsToCheckoutSession(
                $order,
                $matchingQuote
            )
        );
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::orderBelongsToCheckoutSession(
                $order,
                $foreignQuote
            )
        );
    }

    public function testBuildSuccessExistingReturnsBlockForForeignOrder()
    {
        $order = new Mage_Sales_Model_Order(array(
            'entity_id' => 10,
            'quote_id' => 5,
            'increment_id' => '100000010',
            'customer_id' => 0,
            'customer_email_direct' => 'alice@example.com',
        ));
        $quote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 99,
            'customer_id' => 0,
            'customer_email' => 'bob@example.com',
        ));

        $result = Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::buildSuccessExistingEvaluation(
            $order,
            $quote,
            'eps_order_id'
        );

        $this->assertSame('block', $result['action']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty(Bold_CheckoutPaymentBooster_Test_Stub_Mage::$logMessages);
    }

    public function testBuildSuccessExistingReturnsSuccessForOwnedOrder()
    {
        $order = new Mage_Sales_Model_Order(array(
            'entity_id' => 10,
            'quote_id' => 5,
            'increment_id' => '100000011',
            'customer_id' => 1,
        ));
        $quote = new Mage_Sales_Model_Quote(array(
            'entity_id' => 5,
            'customer_id' => 1,
        ));

        $result = Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::buildSuccessExistingEvaluation(
            $order,
            $quote,
            'inactive_quote'
        );

        $this->assertSame('success_existing', $result['action']);
        $this->assertSame($order, $result['order']);
    }

    public function testAssertQuoteMatchesCheckoutSessionThrows403WhenMismatch()
    {
        $session = new Mage_Checkout_Model_Session(array('quote_id' => 5));
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton('checkout/session', $session);

        $quote = new Mage_Sales_Model_Quote(array('entity_id' => 99));

        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionCode(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::EXCEPTION_QUOTE_ACCESS_DENIED
        );

        Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::assertQuoteMatchesCheckoutSession($quote);
    }

    public function testLoadCheckoutSessionQuoteRejectsForeignQuoteId()
    {
        $session = new Mage_Checkout_Model_Session(array('quote_id' => 5));
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton('checkout/session', $session);

        $foreignQuote = new Mage_Sales_Model_Quote(array('entity_id' => 99));
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setQuoteById('99', $foreignQuote);

        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionCode(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::EXCEPTION_QUOTE_ACCESS_DENIED
        );

        Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::loadCheckoutSessionQuote('99');
    }

    public function testLoadCheckoutSessionQuoteReturnsSessionQuoteWhenParamEmpty()
    {
        $quote = new Mage_Sales_Model_Quote(array('entity_id' => 5));
        $session = new Mage_Checkout_Model_Session(array(
            'quote_id' => 5,
            'quote' => $quote,
        ));
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton('checkout/session', $session);

        $loaded = Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::loadCheckoutSessionQuote('');

        $this->assertSame($quote, $loaded);
    }

    public function testRegisterAndAssertWalletEpsOrderId()
    {
        $session = new Mage_Checkout_Model_Session();
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton('checkout/session', $session);

        Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::registerWalletEpsOrderId('eps-abc-123');

        Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::assertWalletEpsOrderIdBelongsToSession(
            'eps-abc-123'
        );

        $this->assertTrue(true);
    }

    public function testAssertWalletEpsOrderIdRejectsForeignId()
    {
        $session = new Mage_Checkout_Model_Session(array(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::SESSION_WALLET_EPS_ORDER_ID => 'eps-mine',
        ));
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton('checkout/session', $session);

        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionCode(
            Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::EXCEPTION_QUOTE_ACCESS_DENIED
        );

        Bold_CheckoutPaymentBooster_Service_Order_CheckoutSessionOwnership::assertWalletEpsOrderIdBelongsToSession(
            'eps-theirs'
        );
    }
}
