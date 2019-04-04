<?php

/**
 * Class PayItSimple_Payment_Model_Fee
 */
class PayItSimple_Payment_Model_Fee extends Mage_Core_Model_Abstract {
    /**
     * Total Code
     */
    const TOTAL_CODE = 'fee';

    static $storeId;
    /**
     * @var array
     */
    public $methodFee = NULL;

    /**
     * Constructor
     */
    public function __construct() {
        self::$storeId = Mage::app()->getStore()->getStoreId();
    }

    /**
     * Retrieve Payment Method Fees from Store Config
     * @return array
     */
    protected function _getMethodFee() {
        if (is_null($this->methodFee)) {
            $this->methodFee = Mage::helper('pis_payment')->getFee();
        }

        return $this->methodFee;
    }

    /**
     * Check if fee can be apply
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return bool
     */
    public function canApply(Mage_Sales_Model_Quote_Address $address) {
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = $address->getQuote();
        if ($method = $quote->getPayment()->getMethod()) {
            if (Mage::getStoreConfig('payment/'.$method.'/splitit_fees')) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Calculate Payment Fee
     * @param Mage_Sales_Model_Quote_Address $address
     * @return float|int
     */
    public function getFee(Mage_Sales_Model_Quote_Address $address) {
        /* @var $quote Mage_Sales_Model_Quote */
        $quote   = $address->getQuote();
        $method  = $quote->getPayment()->getMethod();
        // var_dump($method);die('ashjdgsajhdgas');
        if($method == 'pis_paymentform' || $method == 'pis_cc'){
            $fee     = Mage::getStoreConfig('payment/'.$method.'/splitit_fees_value');
            $feeType = Mage::getStoreConfig('payment/'.$method.'/splitit_fees_method');
            if ($feeType == PayItSimple_Payment_Model_Source_Feemethod::FIXED) {
                return $fee;
            } else {
                $totals = $quote->getTotals();
                $sum    = 0;
                foreach ($totals as $total) {
                    if ($total->getCode() != self::TOTAL_CODE) {
                        $sum += (float)$total->getValue();
                    }
                }

                return ($sum * ($fee / 100));
            }
        } else {
            return 0;
        }
    }

    /**
     * Retrieve Total Title from Store Config
     * @param string $method
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     */
    public function getTotalTitle($method = '', Mage_Sales_Model_Quote $quote = null) {
        return Mage::helper('pis_payment')->__('Splitit Fee');
    }
}