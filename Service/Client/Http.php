<?php

/**
 * Perform curl request service.
 */
class Bold_CheckoutPaymentBooster_Service_Client_Http
{
    /**
     * Perform request.
     *
     * @param string $method
     * @param string $url
     * @param int $websiteId
     * @param array $headers
     * @param string|null $body
     * @return string
     */
    public static function call($method, $url, $websiteId, array $headers, $body = null)
    {
        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);
        $tracingId = sha1(microtime());
        if ($config->isLogEnabled($websiteId)) {
            $incomingRequest = self::collectIncomingRequestMetadata();
            if ($incomingRequest !== array()) {
                Mage::log(
                    $tracingId . ': Incoming request: ' . json_encode($incomingRequest),
                    Zend_Log::DEBUG,
                    Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
                    true
                );
            }

            Mage::log(
                $tracingId . ': Outgoing Call: ' . $method . ' ' . $url,
                Zend_Log::DEBUG,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
                true
            );
            Mage::log(
                $tracingId . ': Outgoing Call headers: ' . json_encode(self::sanitizeHeaderList($headers)),
                Zend_Log::DEBUG,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
                true
            );
            Mage::log(
                $tracingId . ': Outgoing Call Data: ' . $body,
                Zend_Log::DEBUG,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
                true
            );
        }
        $curl = curl_init();
        $url = self::prepareRequest($method, $curl, $url, $body);
        if ($headers) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 300);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        if ($config->isLogEnabled($websiteId)) {
            Mage::log(
                $tracingId . ': Outgoing call code: ' . curl_getinfo($curl, CURLINFO_HTTP_CODE),
                Zend_Log::DEBUG,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
                true
            );
            Mage::log(
                $tracingId . ': Outgoing call result: ' . $result,
                Zend_Log::DEBUG,
                Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME,
                true
            );
        }
        curl_close($curl);
        try {
            $isJson = json_decode($result);
        } catch (Exception $e) {
            $isJson = false;
        }
        if ($isJson === false) {
            $result = json_encode(
                [
                    'errors' => [
                        'message' => 'Invalid response from Bold',
                        'code' => '500',
                    ],
                ]
            );
        }

        return $result;
    }

    /**
     * Build request with given data.
     *
     * @param string $method
     * @param resource $curl
     * @param string $url
     * @param string|null $data
     * @return string
     */
    private static function prepareRequest($method, $curl, $url, $data = null)
    {
        switch ($method) {
            case 'POST':
                self::preparePostRequest($curl, $data);
                break;
            case 'PUT':
                self::preparePutRequest($curl, $data);
                break;
            case 'PATCH':
                self::preparePatchRequest($curl, $data);
                break;
            case 'DELETE' :
                self::prepareDeleteRequest($curl, $data);
                break;
            default:
                if ($data) {
                    $data = json_decode($data);
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }
        }

        return $url;
    }

    /**
     * Prepare POST request.
     *
     * @param resource $curl
     * @param string|null $data
     * @return void
     */
    private static function preparePostRequest($curl, $data)
    {
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
    }

    /**
     * Prepare PUT request.
     *
     * @param resource $curl
     * @param string|null $data
     * @return void
     */
    private static function preparePutRequest($curl, $data)
    {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
    }

    /**
     * Prepare PATCH request.
     *
     * @param resource $curl
     * @param string|null $data
     * @return void
     */
    private static function preparePatchRequest($curl, $data)
    {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
    }

    /**
     * Prepare DELETE request.
     *
     * @param resource $curl
     * @param string|null $data
     * @return void
     */
    private static function prepareDeleteRequest($curl, $data)
    {
        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    /**
     * Incoming Magento HTTP request metadata (browser → shop), for bold_checkout_payment_booster.log.
     *
     * @return array
     */
    private static function collectIncomingRequestMetadata()
    {
        if (!method_exists('Mage', 'app')) {
            return array();
        }

        /** @var Mage_Core_Controller_Request_Http $request */
        $request = Mage::app()->getRequest();

        return array(
            'method' => $request->getMethod(),
            'path' => $request->getRequestUri(),
            'remote_addr' => $request->getClientIp(false),
            'is_ajax' => $request->isXmlHttpRequest(),
            'headers' => self::collectIncomingRequestHeaders(),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function collectIncomingRequestHeaders()
    {
        $raw = array();
        if (function_exists('getallheaders')) {
            $fromApache = getallheaders();
            if (is_array($fromApache)) {
                $raw = $fromApache;
            }
        }

        if ($raw === array()) {
            foreach ($_SERVER as $name => $value) {
                if (strpos($name, 'HTTP_') !== 0) {
                    continue;
                }

                $headerName = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($name, 5))))
                );
                $raw[$headerName] = $value;
            }
        }

        return self::sanitizeHeaderMap($raw);
    }

    /**
     * @param array $headers Outgoing curl headers (numeric keys) or name => value map.
     * @return array
     */
    private static function sanitizeHeaderList(array $headers)
    {
        $map = array();
        foreach ($headers as $key => $value) {
            if (is_int($key) && is_string($value) && strpos($value, ':') !== false) {
                list($name, $headerValue) = explode(':', $value, 2);
                $map[trim($name)] = trim($headerValue);
            } else {
                $map[(string) $key] = (string) $value;
            }
        }

        return self::sanitizeHeaderMap($map);
    }

    /**
     * @param array $headers
     * @return array<string, string>
     */
    private static function sanitizeHeaderMap(array $headers)
    {
        $redacted = array(
            'authorization',
            'proxy-authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'x-auth-token',
        );

        $sanitized = array();
        foreach ($headers as $name => $value) {
            $normalized = strtolower(trim($name));
            $sanitized[$normalized] = in_array($normalized, $redacted, true)
                ? '[REDACTED]'
                : (strlen($value) > 500 ? substr($value, 0, 500) . '…' : (string) $value);
        }

        ksort($sanitized);

        return $sanitized;
    }
}
