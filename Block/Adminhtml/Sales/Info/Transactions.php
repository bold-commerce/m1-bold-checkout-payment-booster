<?php
class Bold_CheckoutPaymentBooster_Block_Adminhtml_Sales_Info_Transactions extends Mage_Core_Block_Template
{
    protected $_template = 'bold/checkout_payment_booster/sales/info/transactions.phtml';

    protected $_order = null;

    /**
     * Get current order
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (is_null($this->_order)) {
            if ($this->hasOrder()) {
                $this->_order = $this->getData('order');
            } elseif (Mage::registry('current_order')) {
                $this->_order = Mage::registry('current_order');
            } elseif (Mage::registry('order')) {
                $this->_order = Mage::registry('order');
            }
        }
        return $this->_order;
    }

    /**
     * Get Bold transactions
     *
     * @return array|null
     */
    public function getBoldTransactions()
    {
        $order = $this->getOrder();
        if (!$order || !$order->getId()) {
            return null;
        }

        $payment = $order->getPayment();
        return $payment->getAdditionalInformation('bold_transactions');
    }

    /**
     * Get card details
     *
     * @return array|null
     */
    public function getCardDetails()
    {
        $order = $this->getOrder();
        if (!$order || !$order->getId()) {
            return null;
        }

        $payment = $order->getPayment();
        $cardDetails = $payment->getAdditionalInformation('card_details');

        if (is_string($cardDetails)) {
            $cardDetails = @unserialize($cardDetails);
        }

        return is_array($cardDetails) ? $cardDetails : null;
    }

    /**
     * Check if should display block (only for Bold orders with transactions)
     *
     * @return bool
     */
    public function shouldDisplay()
    {
        $order = $this->getOrder();
        if (!$order || !$order->getId()) {
            return false;
        }
        
        $payment = $order->getPayment();
        if (!$payment) {
            return false;
        }
        
        // Check if payment method is Bold
        $paymentMethod = $payment->getMethod();
        if (strpos($paymentMethod, 'bold') === false && strpos($paymentMethod, 'checkout_payment_booster') === false) {
            return false;
        }
        
        // Check if transactions exist
        $transactions = $this->getBoldTransactions();
        return !empty($transactions) && is_array($transactions);
    }

    /**
     * Render block HTML
     * Only render if there are transactions
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->shouldDisplay()) {
            return '';
        }
        return parent::_toHtml();
    }
}