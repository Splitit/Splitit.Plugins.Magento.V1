<?php

class PayItSimple_Payment_Model_Source_Perproduct
{
    public function toOptionArray()
    {
        return array(
            array('value' => '0', 'label' => __('Disabled')),
            array('value' => '1', 'label' => __('Enable Splitit if the cart consists only of products from the list below')),
            array('value' => '2', 'label' => __('Enable Splitit if the cart consists of at least one of the products from the list below'))
        );

    }
}