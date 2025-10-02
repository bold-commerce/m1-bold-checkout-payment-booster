<?php
/**
 * Override of Mage_Adminhtml_Block_Sales_Order_Payment
 * Adds Bold transactions functionality
 */
class Bold_CheckoutPaymentBooster_Block_Adminhtml_Sales_Order_Payment 
    extends Mage_Adminhtml_Block_Sales_Order_Payment
{
    /**
     * Override _beforeToHtml to add Bold transactions after order is set
     *
     * @return Mage_Core_Block_Abstract
     */
    protected function _beforeToHtml()
    {
        parent::_beforeToHtml();

        $order = $this->getOrder();
        if (!$order && $this->getPayment()) {
            $order = $this->getPayment()->getOrder();
        }

        if ($order && $order->getId()) {
            $payment = $order->getPayment();
            $transactions = $payment->getAdditionalInformation('bold_transactions');
            
            if (!empty($transactions) && is_array($transactions)) {
                $transactionsBlock = $this->getLayout()->createBlock(
                    'bold_checkout_payment_booster/adminhtml_sales_info_transactions',
                    'bold_transactions',
                    array(
                        'template' => 'bold/checkout_payment_booster/sales/info/transactions.phtml'
                    )
                );
                $transactionsBlock->setOrder($order);
                $this->setChild('bold_transactions', $transactionsBlock);
            }
        }
        
        return $this;
    }
    
    /**
     * Override _toHtml to include Bold transactions
     *
     * @return string
     */
    protected function _toHtml()
    {
        $html = parent::_toHtml();
        $transactionsHtml = $this->getChildHtml('bold_transactions');
        if ($transactionsHtml) {
            $html .= $transactionsHtml;
        }
        
        return $html;
    }
}
