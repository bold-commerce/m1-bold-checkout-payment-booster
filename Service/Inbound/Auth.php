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
     * @param string|null $sharedSecret
     * @return string
     */
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
