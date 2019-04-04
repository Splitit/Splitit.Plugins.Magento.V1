<?php

/**
 * Class PayItSimple_Payment_Model_Sales_Order_Total_Creditmemo_Fee
 */
class PayItSimple_Payment_Model_Sales_Order_Total_Creditmemo_Fee extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract 
{
    /**
     * Collect credit memo total
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @return PayItSimple_Payment_Model_Sales_Order_Total_Creditmemo_Fee
     */
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo) {
        $order = $creditmemo->getOrder();
        $orderItems = $order->getAllVisibleItems();
        $r=$c=$o=0;
        foreach ($orderItems as $oitem) {
            // echo "o==$o\n";
            $o+=$oitem->getQtyOrdered();
            $r+=$oitem->getQtyRefunded();
        }
        $creditmemoItems = $creditmemo->getAllItems();
        foreach ($creditmemoItems as $citems) {
            if ($citems->getOrderItem()->getParentItem()){
                continue;
            }
            // echo "c==$c\n";
            $c+=$citems->getQty();
        }
        // echo "orderItems=$o creditmemoItems=$c QtyRefunded=$r";exit;
        if($c==($o-$r)){
            if ($order->getFeeAmountInvoiced() > 0) {
                $feeAmountLeft     = $order->getFeeAmountInvoiced() - $order->getFeeAmountRefunded();
                $basefeeAmountLeft = $order->getBaseFeeAmountInvoiced() - $order->getBaseFeeAmountRefunded();
                if ($basefeeAmountLeft > 0) {
                    $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $feeAmountLeft);
                    $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $basefeeAmountLeft);
                    $creditmemo->setFeeAmount($feeAmountLeft);
                    $creditmemo->setBaseFeeAmount($basefeeAmountLeft);
                    $order->setFeeAmountRefunded($feeAmountLeft);
                    $order->setBaseFeeAmountRefunded($basefeeAmountLeft);
                }
            } else {
                $feeAmount     = $order->getFeeAmount();
                $basefeeAmount = $order->getBaseFeeAmount();
                $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $feeAmount);
                $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $basefeeAmount);
                $creditmemo->setFeeAmount($feeAmount);
                $creditmemo->setBaseFeeAmount($basefeeAmount);
                $order->setFeeAmountRefunded($feeAmount);
                $order->setBaseFeeAmountRefunded($basefeeAmount);
            }
        }

        return $this;
    }
}
