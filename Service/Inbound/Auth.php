<?php

/**
 * Inbound Bold webhook HMAC verification helpers.
 */
class Bold_CheckoutPaymentBooster_Service_Inbound_Auth
{
    /**
     * @param Zend_Controller_Request_Http $request
     * @param string $name
     * @return string|null
     */
    public static function getInboundHeader(Zend_Controller_Request_Http $request, $name)
    {
        $header = $request->getHeader($name);
        if ($header) {
            return $header;
        }

        // nginx/Apache rewrites may expose HMAC headers only via $_SERVER, not getHeader().
        $serverKey = 'HTTP_' . str_replace('-', '_', strtoupper($name));
        if (isset($_SERVER[$serverKey]) && $_SERVER[$serverKey] !== '') {
            return $_SERVER[$serverKey];
        }

        return null;
    }

    /**
     * @param string|null $sharedSecret
     * @param string|null $signatureHeader
     * @param string|null $timestamp
     * @return bool
     */
    public static function verifyHmac($sharedSecret, $signatureHeader, $timestamp)
    {
        if (!$sharedSecret || !$signatureHeader || !$timestamp) {
            return false;
        }

        preg_match('/signature="(\S*?)"/', $signatureHeader, $matches);
        $signature = isset($matches[1]) ? $matches[1] : null;
        if (!$signature) {
            return false;
        }

        $expected = base64_encode(
            hash_hmac(
                'sha256',
                'x-hmac-timestamp: ' . $timestamp,
                $sharedSecret,
                true
            )
        );

        return hash_equals($expected, $signature);
    }

    /**
     * Build the Signature header Bold sends on inbound REST webhooks.
     *
     * @param string $sharedSecret
     * @param string $timestamp
     * @return string
     */
    public static function buildBoldSignatureHeader($sharedSecret, $timestamp)
    {
        $signature = base64_encode(
            hash_hmac(
                'sha256',
                'x-hmac-timestamp: ' . $timestamp,
                $sharedSecret,
                true
            )
        );

        return 'keyId="X-HMAC",algorithm="hmac-sha256",headers="x-hmac-timestamp",signature="'
            . $signature
            . '"';
    }

    /**
     * RFC1123 timestamp used by Bold inbound webhook signatures.
     *
     * @return string
     */
    public static function buildBoldTimestamp()
    {
        return gmdate('D, d M y H:i:s') . ' +0000';
    }

    /**
     * Verify Magento would accept inbound webhooks signed with this shared secret.
     *
     * @param string $sharedSecret
     * @return bool
     */
    public static function verifySharedSecretLocally($sharedSecret)
    {
        if (!$sharedSecret) {
            return false;
        }

        $timestamp = self::buildBoldTimestamp();
        $signatureHeader = self::buildBoldSignatureHeader($sharedSecret, $timestamp);

        return self::verifyHmac($sharedSecret, $signatureHeader, $timestamp);
    }

    /**
     * POST to Magento REST like a Bold inbound webhook.
     *
     * @param string $callbackUrl RSA callback base URL, e.g. https://store.example/rest/V1
     * @param string $shopIdentifier
     * @param string $sharedSecret
     * @return array{http_code:int,error:string}
     */
    public static function sendSimulatedInboundWebhook($callbackUrl, $shopIdentifier, $sharedSecret)
    {
        $timestamp = self::buildBoldTimestamp();
        $url = rtrim($callbackUrl, '/')
            . '/shops/'
            . rawurlencode($shopIdentifier)
            . '/orders/rsa-rotation-verify/payments';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Signature: ' . self::buildBoldSignatureHeader($sharedSecret, $timestamp),
            'X-HMAC-Timestamp: ' . $timestamp,
            'Content-Type: application/json',
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        return array(
            'http_code' => $httpCode,
            'error' => (string)$error,
        );
    }

    /**
     * Safe log identifier for diagnosing secret drift without logging the secret itself.
     */
    public static function secretFingerprint($sharedSecret)
    {
        if (!$sharedSecret) {
            return '00000000';
        }

        return substr(hash('sha256', $sharedSecret), 0, 8);
    }
}
