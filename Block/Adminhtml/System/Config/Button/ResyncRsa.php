<?php

/**
 * Admin button to force RSA re-registration when inbound webhooks fail auth.
 */
class Bold_CheckoutPaymentBooster_Block_Adminhtml_System_Config_Button_ResyncRsa
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('bold/checkout_payment_booster/system/config/button/resync_rsa.phtml');
    }

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    public function getResyncUrl()
    {
        $params = array();
        $website = $this->getRequest()->getParam('website');
        $store = $this->getRequest()->getParam('store');

        if ($website) {
            $params['website'] = $website;
        }
        if ($store) {
            $params['store'] = $store;
        }

        return $this->getUrl('admin_bold/adminhtml_rsa/resync', $params);
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id' => 'bold_resync_rsa_button',
                'label' => Mage::helper('core')->__('Re-sync RSA with Bold'),
                'onclick' => 'resyncRsa()',
                'class' => 'scalable',
            ));

        return $button->toHtml();
    }

    public function getResyncScript()
    {
        $resyncUrl = $this->getResyncUrl();

        return "
        <script type='text/javascript'>
        function resyncRsa() {
            if (!confirm('Re-sync RSA with Bold? This rotates the shared secret used for inbound payment webhooks.')) {
                return;
            }

            var form = new Element('form', {
                method: 'POST',
                action: '{$resyncUrl}'
            });

            var token = new Element('input', {
                type: 'hidden',
                name: 'form_key',
                value: FORM_KEY
            });
            form.appendChild(token);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        </script>";
    }
}
