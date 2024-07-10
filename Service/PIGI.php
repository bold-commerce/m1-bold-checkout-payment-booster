<?php

/**
 * PIGI management service.
 */
class Bold_CheckoutPaymentBooster_Service_PIGI
{
    const PAYMENT_CSS_API_URI = 'checkout/shop/{shopId}/payment_css';

    /**
     * Build the payload for PIGI styles update.
     *
     * @param array $cssRules
     * @param array $mediaRules
     * @return array
     */
    public static function buildStylesPayload(array $cssRules = [], array $mediaRules = [])
    {
        $bodyToSend = [];
        foreach ($cssRules as $rule) {
            $bodyToSend['css_rules'][]['cssText'] = $rule;
        }
        foreach ($mediaRules as $condition => $rules) {
            $cssRules = [];
            foreach ($rules as $rule) {
                $cssRules[]['cssText'] = $rule;
            }
            $bodyToSend['media_rules'][] = [
                'conditionText' => $condition,
                'cssRules' => $cssRules,
            ];
        }
        return $bodyToSend;
    }

    /**
     * Get PIGI default styles from iframe-styles.css file.
     *
     * @return string
     */
    public static function getDefaultCss()
    {
        $dir = Mage::getModuleDir('data', 'Bold_CheckoutPaymentBooster');
        $io = new Varien_Io_File();
        $io->open(['path' => $dir]);
        return (string)$io->read('iframe-styles.css');
    }

    /**
     * Get PIGI styles from Bold.
     *
     * @param int $websiteId
     * @return array
     */
    public static function getStyles($websiteId)
    {
        $result = Bold_CheckoutPaymentBooster_Service_Client::get(self::PAYMENT_CSS_API_URI, $websiteId);
        $errors = isset($result->errors) ? $result->errors : [];
        if ($errors) {
            $error = current($errors);
            if (isset($error->message)) {
                $error = $error->message;
            }
            Mage::log($error, Zend_Log::ERR, Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME);
            return [];
        }
        return $result->data->style_sheet;
    }

    /**
     * Send new PIGI styles to Bold.
     *
     * @param int $websiteId
     * @param array $styles
     * @return void
     * @throws Mage_Core_Exception
     */
    public static function updateStyles($websiteId, array $styles)
    {
        $result = Bold_CheckoutPaymentBooster_Service_Client::post(self::PAYMENT_CSS_API_URI, $websiteId, $styles);
        $errors = isset($result->errors) ? $result->errors : [];
        if ($errors) {
            $error = current($errors);
            if (isset($error->message)) {
                $error = $error->message;
            }
            Mage::throwException($error);
        }
    }
}
