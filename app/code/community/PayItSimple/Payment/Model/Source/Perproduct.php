<?php

class PayItSimple_Payment_Model_Source_Perproduct
{
    public function toOptionArray()
    {
        return array(
            array('value' => '0', 'label' => __('Disabled')),
            array('value' => '1', 'label' => __('Enable Splitit just if the selected products from the list and only they are on the cart')),
            array('value' => '2', 'label' => __('Enable Splitit if 1 or more of the selected products from the list is on cart, and the cart includes also other products'))
        );

    }
}