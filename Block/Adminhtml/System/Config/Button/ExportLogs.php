<?php
/**
 * Bold Checkout Payment Booster - Export Logs Button
 *
 * @category    Bold
 * @package     Bold_CheckoutPaymentBooster
 * @author      Bold Commerce
 * @copyright   Copyright (c) 2024 Bold Commerce
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

class Bold_CheckoutPaymentBooster_Block_Adminhtml_System_Config_Button_ExportLogs extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('bold/checkout_payment_booster/system/config/button/export_logs.phtml');
    }

    /**
     * Return element html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Get the export URL
     *
     * @return string
     */
    public function getExportUrl()
    {
        // Get current scope parameters from the request
        $website = $this->getRequest()->getParam('website');
        $store = $this->getRequest()->getParam('store');
        
        // Debug: Let's see what parameters are available
        $allParams = $this->getRequest()->getParams();
        
        $params = array();
        if ($website) {
            $params['website'] = $website;
        }
        if ($store) {
            $params['store'] = $store;
        }
        
        // If no website/store params, try to get from the current admin session
        if (empty($params)) {
            $adminSession = Mage::getSingleton('admin/session');
            if ($adminSession->getData('admin_user')) {
                // Try to get from the current admin user's last used scope
                $website = $adminSession->getData('admin_user')->getData('extra')['configState']['checkout']['bold_checkout_payment_booster_advanced']['website'] ?? null;
                $store = $adminSession->getData('admin_user')->getData('extra')['configState']['checkout']['bold_checkout_payment_booster_advanced']['store'] ?? null;
                
                if ($website) {
                    $params['website'] = $website;
                }
                if ($store) {
                    $params['store'] = $store;
                }
            }
        }
        
        // Fallback: if still no params, use the current URL's website param
        if (empty($params)) {
            $currentUrl = $this->getRequest()->getRequestUri();
            if (preg_match('/website\/([^\/]+)/', $currentUrl, $matches)) {
                $params['website'] = $matches[1];
            }
        }
        
        return $this->getUrl('admin_bold/adminhtml_logs/export', $params);
    }

    /**
     * Get the button HTML
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id'        => 'bold_export_logs_button',
                'label'     => Mage::helper('core')->__('Export Logs'),
                'onclick'   => 'exportLogs()',
                'class'     => 'scalable'
            ));

        return $button->toHtml();
    }

    /**
     * Get the JavaScript for the export functionality
     *
     * @return string
     */
    public function getExportScript()
    {
        $exportUrl = $this->getExportUrl();
        
        return "
        <script type='text/javascript'>
        function exportLogs() {
            var url = '{$exportUrl}';
            
            // Show loading message
            var messageContainer = $('bold_export_logs_button').up('td').next('td');
            if (messageContainer) {
                messageContainer.innerHTML = '<span style=\"color: #666;\">Exporting logs...</span>';
            }
            
            // Create a form to submit the request
            var form = new Element('form', {
                method: 'POST',
                action: url
            });
            
            // Add CSRF token
            var token = new Element('input', {
                type: 'hidden',
                name: 'form_key',
                value: FORM_KEY
            });
            form.appendChild(token);
            
            // Submit the form
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            // Reset button state after a short delay
            setTimeout(function() {
                if (messageContainer) {
                    messageContainer.innerHTML = '';
                }
            }, 2000);
        }
        </script>";
    }
}
