<?php
class PayItSimple_Payment_Model_Observer
{
    public function insertBlock($observer)
    {
        $_block = $observer->getBlock();     
        $_type = $_block->getType();
        if (($_type == 'catalog/product_price' && $_block->getTemplate()=='catalog/product/price.phtml') or $_type == 'checkout/cart_totals') {
            $_child = clone $_block;
            $_child->setType('payitsimple/block');
            if($_type == 'checkout/cart_totals'){
                $_block->setChild('child', $_child);
            }
            else{
                $_block->setChild('child'.$_child->getProduct()->getId(), $_child);
            }
            $_block->setTemplate('payitsimple/splitprice.phtml');
        }
    }
}
