<?php

/**
 * Base admin config button for RSA shared-secret actions (rotate / re-sync).
 */
abstract class Bold_CheckoutPaymentBooster_Block_Adminhtml_System_Config_Button_AbstractRsaAction
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('bold/checkout_payment_booster/system/config/button/rsa_action.phtml');
    }

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    abstract public function getButtonId();

    /**
     * @return string
     */
    abstract public function getButtonLabel();

    /**
     * @return string
     */
    abstract public function getConfirmMessage();

    /**
     * @return string Controller action name without suffix, e.g. rotate or resync
     */
    abstract public function getControllerAction();

    /**
     * @return array
     */
    protected function getScopeParams()
    {
        $params = array();
        $website = $this->getRequest()->getParam('website');
        $store = $this->getRequest()->getParam('store');

        if (!$website && !$store) {
            $requestUri = $this->getRequest()->getRequestUri();
            if (preg_match('#/website/([^/?#]+)#', $requestUri, $matches)) {
                $website = $matches[1];
            } elseif (preg_match('#/store/([^/?#]+)#', $requestUri, $matches)) {
                $store = $matches[1];
            }
        }

        if ($website) {
            $params['website'] = $website;
        }
        if ($store) {
            $params['store'] = $store;
        }

        return $params;
    }

    /**
     * @return string
     */
    public function getActionUrl()
    {
        return $this->getUrl(
            'admin_bold/adminhtml_rsa/' . $this->getControllerAction(),
            $this->getScopeParams()
        );
    }

    /**
     * @return string
     */
    public function getJsFunctionName()
    {
        return 'boldRsa' . ucfirst($this->getControllerAction());
    }

    /**
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id' => $this->getButtonId(),
                'label' => $this->getButtonLabel(),
                'onclick' => $this->getJsFunctionName() . '()',
                'class' => 'scalable',
            ));

        return $button->toHtml();
    }

    /**
     * @return string
     */
    public function getActionScript()
    {
        $actionUrl = $this->getActionUrl();
        $confirmMessage = Mage::helper('core')->jsonEncode($this->getConfirmMessage());
        $jsFunctionName = $this->getJsFunctionName();
        $scopeParams = Mage::helper('core')->jsonEncode($this->getScopeParams());

        return "
        <script type='text/javascript'>
        function {$jsFunctionName}() {
            if (!confirm({$confirmMessage})) {
                return;
            }

            var form = new Element('form', {
                method: 'POST',
                action: '{$actionUrl}'
            });

            var token = new Element('input', {
                type: 'hidden',
                name: 'form_key',
                value: FORM_KEY
            });
            form.appendChild(token);

            var scopeParams = {$scopeParams};
            if (!scopeParams.website && !scopeParams.store) {
                var websiteMatch = window.location.pathname.match(/\\/website\\/([^/]+)/);
                if (websiteMatch) {
                    scopeParams.website = websiteMatch[1];
                }
                var storeMatch = window.location.pathname.match(/\\/store\\/([^/]+)/);
                if (storeMatch) {
                    scopeParams.store = storeMatch[1];
                }
            }

            $H(scopeParams).each(function(pair) {
                if (!pair.value) {
                    return;
                }
                form.appendChild(new Element('input', {
                    type: 'hidden',
                    name: pair.key,
                    value: pair.value
                }));
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        </script>";
    }
}
