<?php

class PayItSimple_Payment_Model_Pispayment extends Mage_Core_Model_Abstract
{
    protected function _construct(){

       $this->_init("pis_payment/pispayment");

    }

    public function sayhello(){
    	$this->load("53342166015563671044",'installment_plan_number');
    	print_r($this->getData());
    	die("askgasdga hello");
    }

}
	 