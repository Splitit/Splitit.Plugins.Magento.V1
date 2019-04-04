<?php

class PayItSimple_Payment_Model_Source_Feemethod
{
    const PERCENTAGE_FROM_TOTAL_AMOUNT = 1;
    const FIXED = 2;
    public function toOptionArray()
    {
        return array(
            array('value' => self::PERCENTAGE_FROM_TOTAL_AMOUNT, 'label' => Mage::helper('pis_payment')->__('Percentage from Total Amount')),
            array('value' => self::FIXED, 'label' => Mage::helper('pis_payment')->__('Fixed'))
        );
    }
}