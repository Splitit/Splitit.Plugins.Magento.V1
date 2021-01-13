<?php
/**
 * Class PayItSimple_Payment_Block_Adminhtml_Sales_Order_Create_Totals_Fee
 */
class PayItSimple_Payment_Block_Adminhtml_Sales_Order_Creditmemo_Totals extends Mage_Adminhtml_Block_Sales_Order_Creditmemo_Totals{

	/**
     * Initialize order totals array
     *
     * @return Mage_Sales_Block_Order_Totals
     */
    protected function _initTotals()
    {
        parent::_initTotals();
        if ((float) $this->getSource()->getFeeAmount()) {
            $source = $this->getSource();
            $orderItems = $this->_order->getAllVisibleItems();
            $r=$c=$o=0;
            foreach ($orderItems as $oitem) {
                $o+=$oitem->getQtyOrdered();
                $r+=$oitem->getQtyRefunded();
            }
            $creditmemoItems = $source->getAllItems();
            foreach ($creditmemoItems as $citems) {
                if ($citems->getOrderItem()->getParentItem()){
                    continue;
                }
                $c+=$citems->getQty();
            }
            // echo "orderItems=$o creditmemoItems=$c QtyRefunded=$r";exit;
            $feeAmount = 0 ;
            if(($c==($o-$r))||$source->getFeeAmount()){
              $feeAmount = $this->_order->getFeeAmount();
            }
            $total = new Varien_Object(array(
                'code'  => 'fee',
                'value' => $feeAmount,
                'label' => $this->__('Splitit Fee')
            ));
            $this->addTotal($total);
        }
        return $this;
    }
}