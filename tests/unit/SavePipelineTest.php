<?php

use PHPUnit\Framework\TestCase;

class SavePipelineTest extends TestCase
{
    protected function setUp(): void
    {
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::reset();
        $config = new Bold_CheckoutPaymentBooster_Test_Stub_Config();
        Bold_CheckoutPaymentBooster_Test_Stub_Mage::setSingleton(
            Bold_CheckoutPaymentBooster_Model_Config::RESOURCE,
            $config
        );
    }

    public function testShouldRotateRsaWhenForceFlagSet()
    {
        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::shouldRotateRsa(1, true)
        );
    }

    public function testShouldRotateRsaWhenSharedSecretMissing()
    {
        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::shouldRotateRsa(1, false)
        );
    }

    public function testShouldRotateRsaWhenApiTokenChangedRegistryFlagSet()
    {
        /** @var Bold_CheckoutPaymentBooster_Test_Stub_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $config->setValue('shared_secret', 'existing');
        Mage::register('bold_checkout_api_token_changed_1', true);

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::shouldRotateRsa(1, false)
        );
    }

    public function testShouldNotRotateRsaWhenNothingChanged()
    {
        /** @var Bold_CheckoutPaymentBooster_Test_Stub_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $config->setValue('shared_secret', 'existing');
        $config->setValue('api_token', 'token-value');
        $config->setValue('api_token_fingerprint', hash('sha256', 'token-value'));

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::shouldRotateRsa(1, false)
        );
    }

    public function testHostsMatchIgnoresWwwDifference()
    {
        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::hostsMatch(
                'www.example.com',
                'example.com'
            )
        );
    }

    public function testNormalizeHostStripsSchemeAndPath()
    {
        $this->assertSame(
            'www.example.com',
            Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::normalizeHost(
                'https://www.example.com/store/'
            )
        );
    }
}
