<?php 

class PayItSimple_Payment_Model_Source_Firstpaymentpf
{
	public function toOptionArray()
	{
		return array(
            array('value' => 'equal', 'label' => Mage::helper('pis_payment')->__('Equal to Monthly Payment')),
            array('value' => 'shipping', 'label' => Mage::helper('pis_payment')->__('Only Shipping')),
            array('value' => 'percentage', 'label' => Mage::helper('pis_payment')->__('Equal to percentage of the order [X]')),
        );
	}
}