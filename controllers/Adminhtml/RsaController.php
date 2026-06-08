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

    /**
     * Rotate shared key with Bold and save locally after GET verification.
     */
    public function rotateAction()
    {
        $this->executeSharedSecretRotation();
    }

    /**
     * Legacy route — same behavior as rotateAction().
     */
    public function resyncAction()
    {
        $this->executeSharedSecretRotation();
    }

    /**
     * @return void
     */
    private function executeSharedSecretRotation()
    {
        try {
            $websiteId = $this->resolveWebsiteId();
            if ($websiteId === 0) {
                $this->_getSession()->addError(
                    Mage::helper('core')->__('Please select a website scope before rotating the shared key.')
                );
                $this->_redirectReferer();
                return;
            }

            Bold_CheckoutPaymentBooster_Service_Config_SavePipeline::run(
                $websiteId,
                array('force_rsa' => true)
            );

            $this->_getSession()->addSuccess(
                Mage::helper('core')->__('Shared key was rotated and saved with Bold successfully.')
            );
        } catch (Exception $exception) {
            Mage::logException($exception);
            $this->_getSession()->addError($exception->getMessage());
        }

        $this->_redirectReferer();
    }

    /**
     * @return int
     */
    private function resolveWebsiteId()
    {
        $websiteId = (int)$this->getRequest()->getParam('website', 0);
        if ($websiteId === 0) {
            $storeId = (int)$this->getRequest()->getParam('store', 0);
            if ($storeId > 0) {
                $websiteId = (int)Mage::app()->getStore($storeId)->getWebsiteId();
            }
        }

        return $websiteId;
    }
}
