<?php

class PayItSimple_Payment_Model_Source_Productskus {

    public $skus;

    public function toOptionArray($params) {
        $this->skus=array();
        $collection = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect('*');
        $collection->addFieldToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        $collection->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));
        $collection->addAttributeToSort('name');
        if(isset($params['term'])&&$params['term']){
            $collection->addAttributeToFilter(array(
                array('attribute'=>'name','like'=>'%'.$params['term'].'%'),
                array('attribute'=>'sku','like'=>'%'.$params['term'].'%')
            ));            
        }
        if(isset($params['prodIds'])&&$params['prodIds']){
            $collection->addAttributeToFilter('entity_id', array('in' => explode(',', $params['prodIds'])));
        }
        /*$collection->setPageSize(3);*/
        $iterator = Mage::getSingleton('core/resource_iterator');
        /*echo $collection->getSelect();exit;*/
        $iterator->walk($collection->getSelect(),array(array($this,'callBackProd')));
        return $this->skus;
    }

    public function callBackProd($args){
        $this->skus[]=array('value'=>$args['row']['entity_id'], 'label' => __($args['row']['name'].'-'.$args['row']['sku']));
    }

}
