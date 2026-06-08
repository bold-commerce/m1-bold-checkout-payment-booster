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

        $this->expectException(Bold_CheckoutPaymentBooster_Test_Stub_Mage_Core_Exception::class);
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

    public function testIsRetriableRsaConflictDetectsExistingConfiguration()
    {
        $result = (object)array(
            'errors' => array((object)array('message' => 'RSA already configured')),
        );

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRetriableRsaConflict($result)
        );
    }

    public function testIsRetriableRsaConflictReturnsFalseForUnknownErrors()
    {
        $result = (object)array(
            'errors' => array((object)array('message' => 'service unavailable')),
        );

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Rsa_Connect::isRetriableRsaConflict($result)
        );
    }
}
