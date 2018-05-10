<?php

class PayItSimple_Payment_Model_Source_Productskus {

    public function toOptionArray() {
        $collection = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect('*');
        $collection->addFieldToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        $collection->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));
        $collection->addAttributeToSort('name');
//        $collection->setPageSize(3);
        $skus = array();
        foreach ($collection as $product) {
//            echo "<br/>";
//            print_r($product->getData());
            $skus[] = array('value' => $product->getId(), 'label' => __($product->getName() . ' - ' . $product->getSku()));
        }
//        exit;
//        array_multisort($skus, SORT_ASC);
        return $skus;
    }

}
