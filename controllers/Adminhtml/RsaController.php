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
     * Rotate shared key with Bold and save locally after Magento inbound auth verification.
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
        $website = $this->getRequest()->getParam('website');
        if ($website) {
            try {
                return (int)Mage::app()->getWebsite($website)->getId();
            } catch (Exception $exception) {
                Mage::logException($exception);
            }
        }

        $store = $this->getRequest()->getParam('store');
        if ($store) {
            try {
                return (int)Mage::app()->getStore($store)->getWebsiteId();
            } catch (Exception $exception) {
                Mage::logException($exception);
            }
        }

        $referer = (string)$this->getRequest()->getServer('HTTP_REFERER');
        if ($referer !== '') {
            if (preg_match('#/website/([^/?#]+)#', $referer, $matches)) {
                try {
                    return (int)Mage::app()->getWebsite($matches[1])->getId();
                } catch (Exception $exception) {
                    Mage::logException($exception);
                }
            }

            if (preg_match('#/store/([^/?#]+)#', $referer, $matches)) {
                try {
                    return (int)Mage::app()->getStore($matches[1])->getWebsiteId();
                } catch (Exception $exception) {
                    Mage::logException($exception);
                }
            }
        }

        return 0;
    }
}
