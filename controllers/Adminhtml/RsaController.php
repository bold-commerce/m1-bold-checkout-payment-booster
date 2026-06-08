<?php

/**
 * Manual RSA recovery when inbound Bold webhooks fail HMAC verification.
 */
class Bold_CheckoutPaymentBooster_Adminhtml_RsaController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/config');
    }

    public function resyncAction()
    {
        try {
            $websiteId = (int)$this->getRequest()->getParam('website', 0);
            if ($websiteId === 0) {
                $storeId = (int)$this->getRequest()->getParam('store', 0);
                if ($storeId > 0) {
                    $websiteId = (int)Mage::app()->getStore($storeId)->getWebsiteId();
                }
            }

            if ($websiteId === 0) {
                $this->_getSession()->addError(
                    Mage::helper('core')->__('Please select a website scope before re-syncing RSA.')
                );
                $this->_redirectReferer();
                return;
            }

            // force_rsa rotates the shared secret even when API token did not change.
            Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::run(
                $websiteId,
                array('force_rsa' => true)
            );

            $this->_getSession()->addSuccess(
                Mage::helper('core')->__('RSA configuration was re-synced with Bold successfully.')
            );
        } catch (Exception $exception) {
            Mage::logException($exception);
            $this->_getSession()->addError($exception->getMessage());
        }

        $this->_redirectReferer();
    }
}
