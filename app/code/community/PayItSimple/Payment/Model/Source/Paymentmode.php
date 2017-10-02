<?php

class PayItSimple_Payment_Model_Source_Paymentmode
{
    public function toOptionArray()
    {
        return array(
            2 => array('value' => 'embedded_payment', 'label' => Mage::helper('pis_payment')->__('Embedded Payment')),
            array('value' => 'hosted_solution', 'label' => Mage::helper('pis_payment')->__('Hosted Solution')),
        );
    }
}