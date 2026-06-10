<?php

use PHPUnit\Framework\TestCase;

class RsaConnectTest extends TestCase
{
    protected function setUp(): void
    {
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::reset();
        Bold_CheckoutPaymentBooster_Test_Stub_BoldClient::reset();
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            'website_default_store_1',
            new Mage_Core_Model_Store('https://www.example.com/')
        );
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            'core/resource',
            new Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Resource()
        );
    }

    // -------------------------------------------------------------------------
    // getRestCallbackUrl
    // -------------------------------------------------------------------------

    public function testGetRestCallbackUrlUsesWebsiteDefaultStore()
    {
        $url = Bold_CheckoutPaymentBooster_Service_Rsa_Connect::getRestCallbackUrl(1);

        $this->assertSame('https://www.example.com/rest/V1', $url);
    }

    // -------------------------------------------------------------------------
    // assertShopIdPresent
    // -------------------------------------------------------------------------

    public function testAssertShopIdPresentThrowsWhenMissing()
    {
        $config = new Bold_CheckoutPaymentBooster_Test_Stub_Config();
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            Bold_CheckoutPaymentBooster_Model_Config::RESOURCE,
            $config
        );

        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionMessage('Bold shop ID is missing');

        Bold_CheckoutPaymentBooster_Service_Rsa_Connect::assertShopIdPresent(1);
    }

    public function testAssertShopIdPresentDoesNotThrowWhenPresent()
    {
        $config = new Bold_CheckoutPaymentBooster_Test_Stub_Config();
        $config->setValue('shop_id', 'abc123');
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            Bold_CheckoutPaymentBooster_Model_Config::RESOURCE,
            $config
        );

        $this->expectNotToPerformAssertions();
        Bold_CheckoutPaymentBooster_Service_Rsa_Connect::assertShopIdPresent(1);
    }

    // -------------------------------------------------------------------------
    // isRegistrationSuccess
    // -------------------------------------------------------------------------

    public function testIsRegistrationSuccessReturnsTrueWithoutErrors()
    {
        $result = (object)array('data' => (object)array('ok' => true));

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRegistrationSuccess($result)
        );
    }

    public function testIsRegistrationSuccessReturnsFalseWithErrorsArray()
    {
        $result = (object)array(
            'errors' => array((object)array('message' => 'failed')),
        );

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRegistrationSuccess($result)
        );
    }

    public function testIsRegistrationSuccessReturnsFalseWithTopLevelErrorKey()
    {
        $result = (object)array('error' => 'Unauthorized');

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRegistrationSuccess($result)
        );
    }

    public function testIsRegistrationSuccessReturnsFalseForNull()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRegistrationSuccess(null)
        );
    }

    public function testIsRegistrationSuccessReturnsFalseForNonObject()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRegistrationSuccess('error string')
        );
    }

    // -------------------------------------------------------------------------
    // isRsaNotConfigured
    // -------------------------------------------------------------------------

    public function testIsRsaNotConfiguredDetectsErrorCode02_89WithObject()
    {
        $result = (object)array(
            'errors' => array((object)array('code' => '02-89', 'message' => 'Remote State Authority not configured')),
        );

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRsaNotConfigured($result)
        );
    }

    public function testIsRsaNotConfiguredDetectsErrorCode02_89WithArrayError()
    {
        $result = (object)array(
            'errors' => array(array('code' => '02-89', 'message' => 'RSA not configured')),
        );

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRsaNotConfigured($result)
        );
    }

    public function testIsRsaNotConfiguredReturnsFalseForOtherErrorCode()
    {
        $result = (object)array(
            'errors' => array((object)array('code' => '02-02', 'message' => 'something else')),
        );

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRsaNotConfigured($result)
        );
    }

    public function testIsRsaNotConfiguredReturnsFalseForNull()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRsaNotConfigured(null)
        );
    }

    public function testIsRsaNotConfiguredReturnsFalseForEmptyErrors()
    {
        $result = (object)array('errors' => array());

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRsaNotConfigured($result)
        );
    }

    // -------------------------------------------------------------------------
    // getRegistrationErrorMessage
    // -------------------------------------------------------------------------

    public function testGetRegistrationErrorMessageExtractsFromErrorsArray()
    {
        $result = (object)array(
            'errors' => array((object)array('code' => '02-99', 'message' => 'Shop not found')),
        );

        $this->assertSame(
            'Shop not found',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::getRegistrationErrorMessage($result)
        );
    }

    public function testGetRegistrationErrorMessageExtractsErrorDescription()
    {
        $result = (object)array('error_description' => 'Invalid API token');

        $this->assertSame(
            'Invalid API token',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::getRegistrationErrorMessage($result)
        );
    }

    public function testGetRegistrationErrorMessageExtractsNestedErrorMessage()
    {
        $result = (object)array('error' => (object)array('message' => 'Nested error detail'));

        $this->assertSame(
            'Nested error detail',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::getRegistrationErrorMessage($result)
        );
    }

    public function testGetRegistrationErrorMessageReturnsEmptyForNull()
    {
        $this->assertSame(
            '',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::getRegistrationErrorMessage(null)
        );
    }

    public function testGetRegistrationErrorMessageReturnsEmptyForNonObject()
    {
        $this->assertSame(
            '',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::getRegistrationErrorMessage('raw string')
        );
    }

    // -------------------------------------------------------------------------
    // isCheckSharedSuccess
    // -------------------------------------------------------------------------

    public function testIsCheckSharedSuccessAcceptsIntegerOne()
    {
        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isCheckSharedSuccess(
                (object)array('data' => 1)
            )
        );
    }

    public function testIsCheckSharedSuccessAcceptsStringOne()
    {
        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isCheckSharedSuccess(
                (object)array('data' => '1')
            )
        );
    }

    public function testIsCheckSharedSuccessAcceptsBooleanTrue()
    {
        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isCheckSharedSuccess(
                (object)array('data' => true)
            )
        );
    }

    public function testIsCheckSharedSuccessRejectsIntegerZero()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isCheckSharedSuccess(
                (object)array('data' => 0)
            )
        );
    }

    public function testIsCheckSharedSuccessRejectsBooleanFalse()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isCheckSharedSuccess(
                (object)array('data' => false)
            )
        );
    }

    public function testIsCheckSharedSuccessRejectsMissingData()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isCheckSharedSuccess(
                (object)array('errors' => array())
            )
        );
    }

    public function testIsCheckSharedSuccessRejectsNull()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isCheckSharedSuccess(null)
        );
    }

    // -------------------------------------------------------------------------
    // getVerificationFailureMessage
    // -------------------------------------------------------------------------

    public function testGetVerificationFailureMessageIncludesRollbackNoteWhenPreviousSecretExists()
    {
        $message = Bold_CheckoutPaymentBooster_Service_Rsa_Connect::getVerificationFailureMessage(true);

        $this->assertStringContainsString('restored on Bold', $message);
        $this->assertStringContainsString('Bold could not confirm the new shared secret', $message);
        $this->assertStringContainsString('Rotate Shared Key', $message);
    }

    public function testGetVerificationFailureMessageNoPreviousSecretMentionsRotateSharedKey()
    {
        $message = Bold_CheckoutPaymentBooster_Service_Rsa_Connect::getVerificationFailureMessage(false);

        $this->assertStringContainsString('Bold could not confirm the new shared secret', $message);
        $this->assertStringContainsString('Rotate Shared Key', $message);
        $this->assertStringNotContainsString('restored on Bold', $message);
    }

    // -------------------------------------------------------------------------
    // verifySharedSecretWithBoldCheckShared
    // -------------------------------------------------------------------------

    public function testVerifySharedSecretWithBoldCheckSharedReturnsTrueWhenBoldConfirms()
    {
        Bold_CheckoutPaymentBooster_Test_Stub_BoldClient::setResponse(
            'POST',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::CHECK_SHARED_URL,
            (object)array('data' => 1)
        );

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::verifySharedSecretWithBoldCheckShared(
                1,
                'newsecret',
                'https://www.example.com/rest/V1'
            )
        );
    }

    public function testVerifySharedSecretWithBoldCheckSharedReturnsFalseWhenBoldRejects()
    {
        Bold_CheckoutPaymentBooster_Test_Stub_BoldClient::setResponse(
            'POST',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::CHECK_SHARED_URL,
            (object)array('data' => 0)
        );

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::verifySharedSecretWithBoldCheckShared(
                1,
                'newsecret',
                'https://www.example.com/rest/V1'
            )
        );
    }

    // -------------------------------------------------------------------------
    // rotation lock
    // -------------------------------------------------------------------------

    public function testAcquireRotationLockReturnsFalseWhenRegistryLockHeld()
    {
        Mage::register('bold_checkout_rsa_rotating_1', true);

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::acquireRotationLock(1)
        );
    }

    public function testAcquireAndReleaseRotationLock()
    {
        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::acquireRotationLock(1)
        );

        Bold_CheckoutPaymentBooster_Service_Rsa_Connect::releaseRotationLock(1);

        $this->assertNull(Mage::registry('bold_checkout_rsa_rotating_1'));
    }

    // -------------------------------------------------------------------------
    // registerRsaConfig — error paths
    // -------------------------------------------------------------------------

    public function testRegisterRsaConfigThrowsWhenShopIdMissing()
    {
        $config = new Bold_CheckoutPaymentBooster_Test_Stub_Config();
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            Bold_CheckoutPaymentBooster_Model_Config::RESOURCE,
            $config
        );

        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionMessage('Bold shop ID is missing');

        Bold_CheckoutPaymentBooster_Service_Rsa_Connect::registerRsaConfig(1, true);
    }

    public function testRegisterRsaConfigThrowsWhenBoldReturnsPermanentError()
    {
        $config = new Bold_CheckoutPaymentBooster_Test_Stub_Config();
        $config->setValue('shop_id', 'shop_abc123');
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            Bold_CheckoutPaymentBooster_Model_Config::RESOURCE,
            $config
        );

        $errorResponse = (object)array(
            'errors' => array((object)array('code' => '99-99', 'message' => 'Permanent failure')),
        );
        Bold_CheckoutPaymentBooster_Test_Stub_BoldClient::setResponse(
            'PATCH',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::URL,
            $errorResponse
        );

        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionMessage('RSA registration failed');

        Bold_CheckoutPaymentBooster_Service_Rsa_Connect::registerRsaConfig(1, true);
    }

    public function testRegisterRsaConfigThrowsWhenCheckSharedFails()
    {
        $config = new Bold_CheckoutPaymentBooster_Test_Stub_Config();
        $config->setValue('shop_id', 'shop_abc123');
        $config->setValue('shared_secret', 'oldSecret');
        $config->setValue('is_check_shared_enabled', true);
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            Bold_CheckoutPaymentBooster_Model_Config::RESOURCE,
            $config
        );

        Bold_CheckoutPaymentBooster_Test_Stub_BoldClient::setResponse(
            'POST',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::CHECK_SHARED_URL,
            (object)array('data' => 0)
        );

        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionMessage('Bold could not confirm the new shared secret');

        Bold_CheckoutPaymentBooster_Service_Rsa_Connect::registerRsaConfig(1, true);
    }

    public function testRegisterRsaConfigDoesNotPersistSecretUntilCheckSharedPasses()
    {
        $config = new Bold_CheckoutPaymentBooster_Test_Stub_Config();
        $config->setValue('shop_id', 'shop_abc123');
        $config->setValue('shared_secret', 'oldSecret');
        $config->setValue('is_check_shared_enabled', true);
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            Bold_CheckoutPaymentBooster_Model_Config::RESOURCE,
            $config
        );

        Bold_CheckoutPaymentBooster_Test_Stub_BoldClient::setResponse(
            'POST',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::CHECK_SHARED_URL,
            (object)array('data' => 1)
        );

        Bold_CheckoutPaymentBooster_Service_Rsa_Connect::registerRsaConfig(1, true);

        $this->assertNotSame('oldSecret', $config->getSharedSecret(1));
        $this->assertNotEmpty($config->getSharedSecret(1));
    }

    public function testRegisterRsaConfigFallsBackToPostWhenPatchReturns0289()
    {
        $config = new Bold_CheckoutPaymentBooster_Test_Stub_Config();
        $config->setValue('shop_id', 'shop_abc123');
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            Bold_CheckoutPaymentBooster_Model_Config::RESOURCE,
            $config
        );

        $notConfiguredResponse = (object)array(
            'errors' => array((object)array('code' => '02-89', 'message' => 'RSA not configured')),
        );
        Bold_CheckoutPaymentBooster_Test_Stub_BoldClient::setResponse(
            'PATCH',
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::URL,
            $notConfiguredResponse
        );

        $this->expectNotToPerformAssertions();
        Bold_CheckoutPaymentBooster_Service_Rsa_Connect::registerRsaConfig(1, true);
    }

    public function testRegisterRsaConfigThrowsWhenRotationAlreadyInProgress()
    {
        Mage::register('bold_checkout_rsa_rotating_1', true);

        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionMessage('RSA rotation is already in progress');

        Bold_CheckoutPaymentBooster_Service_Rsa_Connect::registerRsaConfig(1, true);
    }
}
