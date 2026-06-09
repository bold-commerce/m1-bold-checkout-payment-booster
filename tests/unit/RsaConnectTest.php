<?php

use PHPUnit\Framework\TestCase;

class RsaConnectTest extends TestCase
{
    protected function setUp(): void
    {
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::reset();
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            'website_default_store_1',
            new Mage_Core_Model_Store('https://www.example.com/')
        );
    }

    public function testGetRestCallbackUrlUsesWebsiteDefaultStore()
    {
        $url = Bold_CheckoutPaymentBooster_Service_Rsa_Connect::getRestCallbackUrl(1);

        $this->assertSame('https://www.example.com/rest/V1', $url);
    }

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

    public function testIsRegistrationSuccessReturnsTrueWithoutErrors()
    {
        $result = (object)array('data' => (object)array('ok' => true));

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRegistrationSuccess($result)
        );
    }

    public function testIsRegistrationSuccessReturnsFalseWithErrors()
    {
        $result = (object)array(
            'errors' => array((object)array('message' => 'failed')),
        );

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRegistrationSuccess($result)
        );
    }

    public function testIsRsaNotConfiguredDetectsErrorCode02_89()
    {
        $result = (object)array(
            'errors' => array((object)array('code' => '02-89', 'message' => 'Remote State Authority not configured')),
        );

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRsaNotConfigured($result)
        );
    }

    public function testIsRsaNotConfiguredReturnsFalseForOtherErrors()
    {
        $result = (object)array(
            'errors' => array((object)array('code' => '02-02', 'message' => 'something else')),
        );

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRsaNotConfigured($result)
        );
    }

    public function testExtractRsaConfigFromResponseReadsDataWrapper()
    {
        $result = (object)array(
            'data' => (object)array(
                'url' => 'https://www.example.com/rest/V1/',
                'shared_secret' => 'abc12345',
            ),
        );

        $this->assertSame(
            array(
                'url' => 'https://www.example.com/rest/V1/',
                'shared_secret' => 'abc12345',
            ),
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::extractRsaConfigFromResponse($result)
        );
    }

    public function testExtractRsaConfigFromResponseReturnsNullWhenFieldsMissing()
    {
        $result = (object)array('data' => (object)array('url' => 'https://www.example.com/rest/V1'));

        $this->assertNull(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::extractRsaConfigFromResponse($result)
        );
    }

    public function testRsaConfigMatchesIgnoresTrailingSlashOnUrl()
    {
        $expected = array(
            'url' => 'https://www.example.com/rest/V1',
            'shared_secret' => 'abc12345',
        );
        $remote = array(
            'url' => 'https://www.example.com/rest/V1/',
            'shared_secret' => 'abc12345',
        );

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::rsaConfigMatches($expected, $remote)
        );
    }

    public function testRsaConfigMatchesReturnsFalseWhenSharedSecretDiffers()
    {
        $expected = array(
            'url' => 'https://www.example.com/rest/V1',
            'shared_secret' => 'abc12345',
        );
        $remote = array(
            'url' => 'https://www.example.com/rest/V1',
            'shared_secret' => 'other123',
        );

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::rsaConfigMatches($expected, $remote)
        );
    }

    public function testGetVerificationFailureMessageIncludesRollbackNoteWhenPreviousSecretExists()
    {
        $message = Bold_CheckoutPaymentBooster_Service_Rsa_Connect::getVerificationFailureMessage(true);

        $this->assertStringContainsString('restored on Bold', $message);
    }
}
