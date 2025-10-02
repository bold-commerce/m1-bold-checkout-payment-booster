<?php
class Bold_CheckoutPaymentBooster_Adminhtml_LogsController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return true;
    }

    public function exportAction()
    {
        try {
            $config = Mage::getModel('bold_checkout_payment_booster/config');
            $websiteId = Mage::app()->getWebsite()->getId();
            $logFile = Mage::getBaseDir('log') . DS . Bold_CheckoutPaymentBooster_Model_Config::LOG_FILE_NAME;
            
            // Check if log file exists
            if (!file_exists($logFile)) {
                $this->_getSession()->addError(
                    Mage::helper('core')->__('Log file does not exist.')
                );
                $this->_redirectReferer();
                return;
            }
            
            // Check if logs are enabled using Config model
            // Get website ID from admin request parameters (selected scope)
            $websiteId = $this->getRequest()->getParam('website', 0);
            if ($websiteId == 0) {
                // If no website param, try to get from store param
                $storeId = $this->getRequest()->getParam('store', 0);
                if ($storeId > 0) {
                    $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();
                }
            }
            
            if (!$config->isLogEnabled($websiteId)) {
                $this->_getSession()->addError(
                    Mage::helper('core')->__('Logging is not enabled for this website. Please enable it in System > Configuration > Checkout > Bold Checkout Payment Booster Extension Advanced Settings for the website scope.')
                );
                $this->_redirectReferer();
                return;
            }
            
            // Get file content
            $content = file_get_contents($logFile);
            if ($content === false) {
                $this->_getSession()->addError(
                    Mage::helper('core')->__('Unable to read log file.')
                );
                $this->_redirectReferer();
                return;
            }
            
            // Generate filename with timestamp
            $filename = 'bold_checkout_payment_booster_log_' . date('Y-m-d_H-i-s') . '.log';
            
            // Use Magento's native download response method
            return $this->_prepareDownloadResponse(
                $filename,
                $content,
                'application/octet-stream'
            );
            
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError(
                Mage::helper('core')->__('An error occurred while exporting the log file: %s', $e->getMessage())
            );
            $this->_redirectReferer();
        }
    }
}
