<?php

class PayItSimple_Payment_Model_Source_Languages
{
    public function toOptionArray()
    {
         
     $Stores=array();
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
 
 


 /*foreach($storeCollection as $store)
 {

    $storelang= Mage::getStoreConfig('general/locale/code', $store->getId());
    if(!isset($Stores[$storelang])){
            $Stores[$storelang]=array();
            $Stores[$storelang]['store']=array();
         }
         $Stores[$storelang]['lang'][]=$storelang;
         $Stores[$storelang]['store'][]=$store->getData();
    } //die("--sdfsdf");
    
     foreach($Stores as $key => $eachstore)
     {  
        $stcode=$eachstore['store'][0]['code'];
        $stname=$eachstore['store'][0]['name'];
        $langcode=$eachstore['lang'][0];
        $_alllang[]= array('value' =>$langcode, 'label' => Mage::helper('pis_payment')->__($stname));
     // echo '<pre>';
     // print_r($eachstore);
     // echo '<pre>';
     }

return $_alllang; */

    }
}