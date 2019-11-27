<?php

/**
 * Class PayItSimple_Payment_Model_Sales_Quote_Address_Total_Fee
 */
class PayItSimple_Payment_Model_Sales_Quote_Address_Total_Fee extends Mage_Sales_Model_Quote_Address_Total_Abstract {
    /**
     * @var string
     */
    protected $_code = 'fee';

    /**
     * Collect fee address amount
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return PayItSimple_Payment_Model_Sales_Quote_Address_Total_Fee
     */
    public function collect(Mage_Sales_Model_Quote_Address $address) {
        parent::collect($address);
        $this->_setAmount(0);
        $this->_setBaseAmount(0);
        $items = $this->_getAddressItems($address);
        if (!count($items)) {
            return $this;
        }
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = $address->getQuote();
        /* @var $feeModel PayItSimple_Payment_Model_Fee */
        $feeModel = Mage::getModel('pis_payment/fee');
        if ($feeModel->canApply($address)) {
            $exist_amount = $quote->getFeeAmount();
            $fee          = $feeModel->getFee($address);
            $balance      = floatval($fee) - floatval($exist_amount);
            $address->setFeeAmount($balance);
            $address->setBaseFeeAmount($balance);
            $quote->setFeeAmount($balance);
            $address->setGrandTotal($address->getGrandTotal() + $address->getFeeAmount());
            $address->setBaseGrandTotal($address->getBaseGrandTotal() + $address->getBaseFeeAmount());
        }

        return $this;
    }

    /**
     * Add fee information to address
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return PayItSimple_Payment_Model_Sales_Quote_Address_Total_Fee
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address) {
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = $address->getQuote();
        /* @var $feeModel PayItSimple_Payment_Model_Fee */
        $feeModel = Mage::getModel('pis_payment/fee');
        $amount = $quote->getFeeAmount();
        $paymentMethod = $address->getQuote()->getPayment();

        if ($amount != 0 && $address->getAddressType() == 'shipping' && is_object($paymentMethod)) {    // billing & shipping address
            $title = $feeModel->getTotalTitle(null, $address->getQuote());

            try {
                $methodCode = $paymentMethod->getMethodInstance()->getCode();
            } catch(\Exception $e) {
                return $this;
            }

            $address->addTotal(
                array(
                    'code' => $this->getCode(),
                    'title' => $title,
                    'value' => $amount
                )
            );
            return $this;
        }
    }
}
