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
     * @param string|null $data
     * @param array $headers
     * @return string
     */
    public static function call($method, $url, $websiteId, $data = null, array $headers = [])
    {
        $tracingId = sha1(microtime());

        Bold_CheckoutPaymentBooster_Service_LogManager::log(
            $tracingId . ': Outgoing Call: ' . $method . ' ' . $url,
            $websiteId,
            Zend_Log::DEBUG
        );
        Bold_CheckoutPaymentBooster_Service_LogManager::log(
            $tracingId . ': Outgoing Call Data: ' . $data,
            $websiteId,
            Zend_Log::DEBUG
        );

        $curl = curl_init();
        $url = self::prepareRequest($method, $curl, $url, $data);
        if ($headers) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 300);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);

        Bold_CheckoutPaymentBooster_Service_LogManager::log(
            $tracingId . ': Outgoing call code: ' . curl_getinfo($curl, CURLINFO_HTTP_CODE),
            $websiteId,
            Zend_Log::DEBUG
        );
        Bold_CheckoutPaymentBooster_Service_LogManager::log(
            $tracingId . ': Outgoing call result: ' . $result,
            $websiteId,
            Zend_Log::DEBUG
        );

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
}
