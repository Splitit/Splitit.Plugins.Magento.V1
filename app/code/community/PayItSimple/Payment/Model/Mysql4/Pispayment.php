<?php
class PayItSimple_Payment_Model_Mysql4_Pispayment extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init("pis_payment/pispayment", "id");
    }
}