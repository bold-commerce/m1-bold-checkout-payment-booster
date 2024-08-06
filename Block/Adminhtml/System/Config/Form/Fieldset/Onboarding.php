<?php

class Bold_CheckoutPaymentBooster_Block_Adminhtml_System_Config_Form_Fieldset_Onboarding
    extends Mage_Adminhtml_Block_Template
    implements Varien_Data_Form_Element_Renderer_Interface
{
    protected $_template = 'bold/checkout_payment_booster/form/fieldset/onboarding.phtml';
    /**
     * @var Mage_Core_Model_Website|null
     */
    private $_currentWebsite;

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->renderView();
    }

    /**
     * @return array{
     *      header: string,
     *      body_text: string,
     *      body_link_text?: string,
     *      body_link_url?: string,
     *      button_text: string,
     *      button_link: string,
     *      sidebar_link_text?: string,
     *      sidebar_link_url?: string
     *  }|null
     */
    public function getOnboardingBannerData()
    {
        $currentWebsite = $this->_getCurrentWebsite();

        if ($currentWebsite === null) {
            return null;
        }

        $apiUrl = $this->_getBaseAccountCenterUrl() . '/onboarding_banner/magento-1/' . $this->getOnboardingStatus();
        /** @var array{
         *     header: string,
         *     body_text: string,
         *     body_link_text?: string,
         *     body_link_url?: string,
         *     button_text: string,
         *     button_link: string,
         *     sidebar_link_text?: string,
         *     sidebar_link_url?: string,
         *     meessage?: string,
         *     errors?: array{
         *         message: string,
         *         code: int
         *     }
         * } $result
         */
        $result = json_decode(
            Bold_CheckoutPaymentBooster_Service_Client_Http::call('GET', $apiUrl, $currentWebsite->getId(), []),
            true
        );

        if (
            json_last_error() !== JSON_ERROR_NONE
            || array_key_exists('errors', $result)
            || array_key_exists('message', $result)
        ) {
            return null;
        }

        return $result;
    }

    /**
     * @return string
     * @phpstan-return 'unknown'|'in_progress'|'complete'
     */
    public function getOnboardingStatus()
    {
        $currentWebsite = $this->_getCurrentWebsite();

        if ($currentWebsite === null) {
            return 'unknown';
        }

        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return !$config->isPaymentBoosterEnabled($currentWebsite->getId()) ? 'in_progress' : 'complete';
    }

    /**
     * @return Mage_Core_Model_Website|null
     */
    private function _getCurrentWebsite()
    {
        if ($this->_currentWebsite !== null) {
            return $this->_currentWebsite;
        }

        $websiteCode = Mage::app()->getRequest()->getParam('website');

        try {
            $this->_currentWebsite = Mage::app()->getWebsite($websiteCode);
        } catch (Mage_Core_Exception $e) {
            return null;
        }

        return $this->_currentWebsite;
    }

    /**
     * @return string
     */
    private function _getBaseAccountCenterUrl()
    {
        $currentWebsite = $this->_getCurrentWebsite();

        if ($currentWebsite === null) {
            return '';
        }

        /** @var Bold_CheckoutPaymentBooster_Model_Config $config */
        $config = Mage::getSingleton(Bold_CheckoutPaymentBooster_Model_Config::RESOURCE);

        return $config->getAccountCenterUrl($currentWebsite->getId());
    }
}
