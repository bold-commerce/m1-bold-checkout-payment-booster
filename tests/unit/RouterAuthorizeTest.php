<?php

use PHPUnit\Framework\TestCase;

class RouterAuthorizeTest extends TestCase
{
    public function testVerifyHmacMatchesValidSignature()
    {
        $timestamp = 'Sun, 07 Jun 26 18:25:49 +0000';
        $secret = 'testsec1';
        $signatureHeader = 'keyId="X-HMAC",algorithm="hmac-sha256",headers="x-hmac-timestamp",signature="'
            . base64_encode(hash_hmac('sha256', 'x-hmac-timestamp: ' . $timestamp, $secret, true))
            . '"';

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifyHmac(
                $secret,
                $signatureHeader,
                $timestamp
            )
        );
    }

    public function testVerifyHmacFailsWhenTimestampMissing()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifyHmac(
                'testsec1',
                'signature="abc"',
                null
            )
        );
    }

    public function testGetInboundHeaderFallsBackToServerVariable()
    {
        $request = new Zend_Controller_Request_Http();
        $_SERVER['HTTP_X_HMAC_TIMESTAMP'] = 'Sun, 07 Jun 26 18:25:49 +0000';

        $this->assertSame(
            'Sun, 07 Jun 26 18:25:49 +0000',
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::getInboundHeader(
                $request,
                'X-HMAC-Timestamp'
            )
        );

        unset($_SERVER['HTTP_X_HMAC_TIMESTAMP']);
    }

    public function testSecretFingerprintReturnsStablePrefix()
    {
        $this->assertSame(
            substr(hash('sha256', 'secret'), 0, 8),
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::secretFingerprint('secret')
        );
    }

    public function testBuildBoldSignatureHeaderMatchesVerifyHmac()
    {
        $timestamp = 'Sun, 07 Jun 26 18:25:49 +0000';
        $secret = 'testsec1';
        $signatureHeader = Bold_CheckoutPaymentBooster_Service_Inbound_Auth::buildBoldSignatureHeader(
            $secret,
            $timestamp
        );

        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifyHmac(
                $secret,
                $signatureHeader,
                $timestamp
            )
        );
    }

    public function testVerifySharedSecretLocallyAcceptsValidSecret()
    {
        $this->assertTrue(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifySharedSecretLocally('testsec1')
        );
    }
}
