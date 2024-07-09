<?php

class Bold_CheckoutPaymentBooster_Service_PIGI
{
    const PAYMENT_CSS_API_URI = 'checkout/shop/{shopId}/payment_css';

    public static function build(array $cssRules = [], array $mediaRules = [])
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
     * @return string
     */
    public static function getDefaultCss()
    {
        $dir = Mage::getModuleDir('data', 'Bold_CheckoutPaymentBooster');
        $io = new Varien_Io_File();
        $io->open(['path' => $dir]);
        return (string)$io->read('iframe-styles.css');
    }

    public static function getStyles($websiteId)
    {
        $result = Bold_CheckoutPaymentBooster_Service_Client::get(self::PAYMENT_CSS_API_URI, $websiteId);
        if (isset($result->errors)) {
            $error = current($result->errors);
            if (isset($error->message)) {
                $error = $error->message;
            }
            Mage::log($error, Zend_Log::ERR, Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME);
            return [];
        }
        return $result->data->style_sheet;
    }

    public static function updateStyles($websiteId, $styles)
    {
        $result = Bold_CheckoutPaymentBooster_Service_Client::post(self::PAYMENT_CSS_API_URI, $websiteId, $styles);
        if (isset($result->errors)) {
            $error = current($result->errors);
            if (is_array($error)) {
                $error = serialize($error);
            }
            Mage::throwException($error);
        }
    }
}
