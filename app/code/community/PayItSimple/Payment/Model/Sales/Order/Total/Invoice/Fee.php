<?php

/**
 * Class PayItSimple_Payment_Model_Sales_Order_Total_Invoice_Fee
 */
class PayItSimple_Payment_Model_Sales_Order_Total_Invoice_Fee extends Mage_Sales_Model_Order_Invoice_Total_Abstract {
    /**
     * Collect invoice total
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return PayItSimple_Payment_Model_Sales_Order_Total_Invoice_Fee
     */
    public function collect(Mage_Sales_Model_Order_Invoice $invoice) {
        $order = $invoice->getOrder();
        /*$feeAmountLeft     = $order->getFeeAmount() - $order->getFeeAmountInvoiced();
        $baseFeeAmountLeft = $order->getBaseFeeAmount() - $order->getBaseFeeAmountInvoiced();
        if (abs($baseFeeAmountLeft) < $invoice->getBaseGrandTotal()) {
            $invoice->setGrandTotal($invoice->getGrandTotal() + $feeAmountLeft);
            $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseFeeAmountLeft);
        } else {
            $feeAmountLeft     = $invoice->getGrandTotal() * -1;
            $baseFeeAmountLeft = $invoice->getBaseGrandTotal() * -1;
            $invoice->setGrandTotal(0);
            $invoice->setBaseGrandTotal(0);
        }
        $invoice->setFeeAmount($feeAmountLeft);
        $invoice->setBaseFeeAmount($baseFeeAmountLeft);*/
        $invoice->setFeeAmount(0);
        $invoice->setBaseFeeAmount(0);
        $order->setFeeAmountInvoiced(0);
        $order->setBaseFeeAmountInvoiced(0);
        $amount = $order->getFeeAmount();
        $invoice->setFeeAmount($amount);
        $order->setFeeAmountInvoiced($amount);
        $amount = $invoice->getOrder()->getBaseFeeAmount();
        $invoice->setBaseFeeAmount($amount);
        $order->setBaseFeeAmountInvoiced($amount);
        $invoice->setGrandTotal($invoice->getGrandTotal() + $invoice->getFeeAmount());
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $invoice->getBaseFeeAmount());

        return $this;
    }
}
