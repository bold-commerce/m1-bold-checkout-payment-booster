<?php

use PHPUnit\Framework\TestCase;

class RouterAuthorizeTest extends TestCase
{
    // -------------------------------------------------------------------------
    // verifyHmac — happy paths
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // verifyHmac — failure paths
    // -------------------------------------------------------------------------

    public function testVerifyHmacFailsWithWrongSecret()
    {
        $timestamp = 'Sun, 07 Jun 26 18:25:49 +0000';
        $goodSecret = 'testsec1';
        $wrongSecret = 'wrongsec';
        $signatureHeader = 'keyId="X-HMAC",algorithm="hmac-sha256",headers="x-hmac-timestamp",signature="'
            . base64_encode(hash_hmac('sha256', 'x-hmac-timestamp: ' . $timestamp, $goodSecret, true))
            . '"';

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifyHmac(
                $wrongSecret,
                $signatureHeader,
                $timestamp
            )
        );
    }

    public function testVerifyHmacFailsWithTamperedSignature()
    {
        $timestamp = 'Sun, 07 Jun 26 18:25:49 +0000';
        $secret = 'testsec1';
        $tamperedHeader = 'keyId="X-HMAC",algorithm="hmac-sha256",headers="x-hmac-timestamp",signature="AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="';

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifyHmac(
                $secret,
                $tamperedHeader,
                $timestamp
            )
        );
    }

    public function testVerifyHmacFailsWithWrongTimestamp()
    {
        $goodTimestamp = 'Sun, 07 Jun 26 18:25:49 +0000';
        $wrongTimestamp = 'Mon, 08 Jun 26 12:00:00 +0000';
        $secret = 'testsec1';
        $signatureHeader = 'keyId="X-HMAC",algorithm="hmac-sha256",headers="x-hmac-timestamp",signature="'
            . base64_encode(hash_hmac('sha256', 'x-hmac-timestamp: ' . $goodTimestamp, $secret, true))
            . '"';

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifyHmac(
                $secret,
                $signatureHeader,
                $wrongTimestamp
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

    public function testVerifyHmacFailsWhenSecretMissing()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifyHmac(
                null,
                'signature="abc"',
                'Sun, 07 Jun 26 18:25:49 +0000'
            )
        );
    }

    public function testVerifyHmacFailsWhenSignatureHeaderMissing()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifyHmac(
                'testsec1',
                null,
                'Sun, 07 Jun 26 18:25:49 +0000'
            )
        );
    }

    public function testVerifyHmacFailsWhenSignatureNotPresentInHeader()
    {
        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifyHmac(
                'testsec1',
                'keyId="X-HMAC",algorithm="hmac-sha256"',
                'Sun, 07 Jun 26 18:25:49 +0000'
            )
        );
    }

    // -------------------------------------------------------------------------
    // buildBoldSignatureHeader + verifyHmac round-trip
    // -------------------------------------------------------------------------

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

    public function testBuildBoldSignatureHeaderIsNotVerifiableWithDifferentSecret()
    {
        $timestamp = 'Sun, 07 Jun 26 18:25:49 +0000';
        $signatureHeader = Bold_CheckoutPaymentBooster_Service_Inbound_Auth::buildBoldSignatureHeader(
            'correctSecret',
            $timestamp
        );

        $this->assertFalse(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::verifyHmac(
                'wrongSecret',
                $signatureHeader,
                $timestamp
            )
        );
    }

    // -------------------------------------------------------------------------
    // buildBoldTimestamp
    // -------------------------------------------------------------------------

    public function testBuildBoldTimestampReturnsUtcRfc1123Format()
    {
        $timestamp = Bold_CheckoutPaymentBooster_Service_Inbound_Auth::buildBoldTimestamp();

        // RFC 1123 short-year format: "Mon, 09 Jun 26 12:00:00 +0000"
        $this->assertMatchesRegularExpression(
            '/^\w{3}, \d{2} \w{3} \d{2} \d{2}:\d{2}:\d{2} \+0000$/',
            $timestamp
        );
    }

    // -------------------------------------------------------------------------
    // getInboundHeader
    // -------------------------------------------------------------------------

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

    public function testGetInboundHeaderPrefersGetHeaderOverServerVariable()
    {
        $request = new Zend_Controller_Request_Http();
        $request->setHeader('X-HMAC-Timestamp', 'from-get-header');
        $_SERVER['HTTP_X_HMAC_TIMESTAMP'] = 'from-server';

        $this->assertSame(
            'from-get-header',
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::getInboundHeader(
                $request,
                'X-HMAC-Timestamp'
            )
        );

        unset($_SERVER['HTTP_X_HMAC_TIMESTAMP']);
    }

    public function testGetInboundHeaderReturnsNullWhenAbsent()
    {
        $request = new Zend_Controller_Request_Http();

        $this->assertNull(
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::getInboundHeader(
                $request,
                'X-HMAC-Timestamp'
            )
        );
    }

    // -------------------------------------------------------------------------
    // secretFingerprint
    // -------------------------------------------------------------------------

    public function testSecretFingerprintReturnsStablePrefix()
    {
        $this->assertSame(
            substr(hash('sha256', 'secret'), 0, 8),
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::secretFingerprint('secret')
        );
    }

    public function testSecretFingerprintReturnsEightHexCharacters()
    {
        $fp = Bold_CheckoutPaymentBooster_Service_Inbound_Auth::secretFingerprint('anyvalue');

        $this->assertSame(8, strlen($fp));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $fp);
    }

    public function testSecretFingerprintReturnsZerosForNull()
    {
        $this->assertSame(
            '00000000',
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::secretFingerprint(null)
        );
    }

    public function testSecretFingerprintReturnsZerosForEmptyString()
    {
        $this->assertSame(
            '00000000',
            Bold_CheckoutPaymentBooster_Service_Inbound_Auth::secretFingerprint('')
        );
    }

    public function testSecretFingerprintIsDeterministicForSameInput()
    {
        $fp1 = Bold_CheckoutPaymentBooster_Service_Inbound_Auth::secretFingerprint('stableSecret');
        $fp2 = Bold_CheckoutPaymentBooster_Service_Inbound_Auth::secretFingerprint('stableSecret');

        $this->assertSame($fp1, $fp2);
    }

    public function testSecretFingerprintDiffersForDifferentSecrets()
    {
        $fp1 = Bold_CheckoutPaymentBooster_Service_Inbound_Auth::secretFingerprint('secretA');
        $fp2 = Bold_CheckoutPaymentBooster_Service_Inbound_Auth::secretFingerprint('secretB');

        $this->assertNotSame($fp1, $fp2);
    }
}
