<?php

class PayItSimple_Payment_Model_Source_Languages
{
    public function toOptionArray()
    {
     $storeCollection = Mage::getModel('core/store')->getCollection();

     $allLanguages = Mage::app()->getLocale()->getOptionLocales();
     $allLangArray = array();
     foreach($allLanguages as $lang){
        $allLangArray[$lang["value"]] = $lang["label"];
     }

     $storeLangArr = array();
     foreach ($storeCollection as $store) {
        $storelang = Mage::getStoreConfig('general/locale/code', $store->getId());
        
        if(!array_key_exists($storelang, $storeLangArr)){
            $storeLangArr[$storelang] = $allLangArray[$storelang];    
            $alllang[]= array('value' =>$storelang, 'label' => $allLangArray[$storelang]);
        }
     } 

     return $alllang;

    }
}