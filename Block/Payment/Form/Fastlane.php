<?php

/**
 * Bold payment method block.
 */
class Bold_CheckoutPaymentBooster_Block_Payment_Form_Fastlane extends Mage_Payment_Block_Form
{
    private $clientToken = null;
    /**
     * @var Mage_Sales_Model_Quote|null
     */
    private $quote = null;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->quote = Mage::getSingleton('checkout/session')->getQuote();
        $this->setTemplate('bold/checkout_payment_booster/payment/form/bold_fastlane_method.phtml');
    }

    /**
     * Get quote address id.
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->quote->getCustomerEmail() ?: '';
    }

    /**
     * Get payment gateway data.
     *
     * @return string
     */
    public function getGatewayData()
    {
        // todo: replace with bold api call
        if (!$this->clientToken) {
            $method = 'POST';
            $url = 'https://payments.sandbox.braintree-api.com/graphql';
            $websiteId = $this->quote->getStore()->getWebsiteId();
            $publicKey = 'wr8c486x5vn2trzj'; // only for testing
            $privateKey = '5c034bc43e3c60cdf6b1ccff5f5d7d48'; // only for testing
            $headers = [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode("$publicKey:$privateKey"),
                'Braintree-Version: 2019-01-01',
            ];
            $data = [
                'query' => '
            mutation {
                createClientToken {
                    clientToken
                }
            }
        ',
            ];
            $response = Bold_CheckoutPaymentBooster_Service_Client_Http::call(
                $method,
                $url,
                $websiteId,
                json_encode($data),
                $headers
            );
            $this->clientToken = json_decode($response)->data->createClientToken->clientToken;
        }
        $gatewayData = [
            'is_test_mode' => true,
            'type' => 'braintree',
            'client_token' => $this->clientToken,

        ];
        return json_encode($gatewayData);
    }
}
