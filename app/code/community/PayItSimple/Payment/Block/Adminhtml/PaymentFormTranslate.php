<?php
class PayItSimple_Payment_Block_Adminhtml_PaymentFormTranslate extends Mage_Adminhtml_Block_System_Config_Form_Field
{
   protected $_addRowButtonHtml = array();
   protected $_removeRowButtonHtml = array();
   public $_storeId = "";
 public function __construct()
    {
         parent::_construct();
        
         // get store id in admin
         if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) // store level
          {
              $store_id = Mage::getModel('core/store')->load($code)->getId();
          }
          elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) // website level
          {
              $website_id = Mage::getModel('core/website')->load($code)->getId();
              $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
          }
          else // default level
          {
              $store_id = 0;
          }
          $this->_storeId = $store_id;

    }

    protected function _prepareLayout()
    {
       
    }
   /**
    * Returns html part of the setting
    *
    * @param Varien_Data_Form_Element_Abstract $element
    * @return string
    */
   protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
   {
      $this->setElement($element);
      $lvals = Mage::getStoreConfig('payment/pis_paymentform/translate_languages', $this->_storeId);
      $translatedJsonVal = json_decode($lvals,true);
      
      $html = "";
      if($lvals == ""){
        $html = $this->getTableLanguageWhenEmpty();

      }else{
       $html = $this->getTableLanguageWhenFilled($translatedJsonVal);
      }


       return $html; 
   }   

   protected function getTableLanguageWhenEmpty()
   {  
      $translationData = $this->getTranslationData();
      $allStoresLanguages = $this->getAllStoresLanguages();
      $selectedLanguage = Mage::getStoreConfig('payment/pis_paymentform/select_language', $this->_storeId);


      $html = "";
      $displayTable = "display:none;";
      $flag = 0;
      foreach($allStoresLanguages as $k => $v){
        $displayTable = "display:none;";
        if($selectedLanguage == "" && $flag == 0){
          $displayTable = "display:block;";
          $flag++;
        }
        if($selectedLanguage == $k){
          $displayTable = "display:block;";
        }
        $html .= '<table style="'.$displayTable.'" class="data border lantbl grid"  cellspacing="0" border="0" id="payment_form_languages_table_'.$k.'">       
             <colgroup>
                <col width="120">
                <col width="95">
                <col>
                <col width="1">
             </colgroup>
             <thead>
                <tr class="headings">               
                   <th>Key</th>
                   <th>English</th>
                   <th>Translation</th>               
                </tr>
             </thead>
             <tbody >';
             $i = 0;
             foreach($translationData as $key => $val) {          
                $html.='
                   <tr>
                   <td><input type="text" name="key[]" id="pf_key_'.$k.'_'.$i.'" value="'.$key.'" readonly/></td>
                   <td><input type="text" name="english[]" id="pf_english_'.$k.'_'.$i.'"  value="'.$val.'" readonly/></td>';
                   if($k == 'en_US'){// auto complete English text when load first time
                    $html.= '<td><input type="text" name="translation[]" id="pf_translation_'.$k.'_'.$i.'" value="'.$val.'" /><input type="hidden" name="edited[]" id="pf_edited_'.$k.'_'.$i.'" value="0"/></td>';
                   }else{
                   $html.= '<td><input type="text" name="translation[]" id="pf_translation_'.$k.'_'.$i.'" value="" /><input type="hidden" name="edited[]" id="pf_edited_'.$k.'_'.$i.'" value="0"/></td>';
                   }
                   $html.= '</tr>';
                   $i++;
                  }
             $html.='</tbody>
             <tfoot>
               
             </tfoot>
          </table>'; 
      }

        
         
      
      return $html;
   }

protected function getTableLanguageWhenFilled($translatedJsonVal)
   {  
           
    $translationData = $this->getTranslationData();
    $allStoresLanguages = $this->getAllStoresLanguages();
    $selectedLanguage = Mage::getStoreConfig('payment/pis_paymentform/select_language', $this->_storeId);


    $html = "";
    $displayTable = "display:none;";

    foreach($allStoresLanguages as $k => $v){
      $displayTable = "display:none;";
      if($selectedLanguage == "" || $selectedLanguage == $k){
        $displayTable = "display:block;";
      }
      $html .= '<table style="'.$displayTable.'" class="data border lantbl grid"  cellspacing="0" border="0" id="payment_form_languages_table_'.$k.'">          
           <colgroup>
              <col width="120">
              <col width="95">
              <col>
              <col width="1">
           </colgroup>
           <thead>
              <tr class="headings">               
                 <th>Key</th>
                 <th>English</th>
                 <th>Translation</th>               
              </tr>
           </thead>
           <tbody >';
           $i = 0;
           foreach($translationData as $key => $val) { 
              if(isset($translatedJsonVal[$k][$key])){ // check if value found in DB
                $html.='
                 <tr>
                 <td><input type="text" name="key[]" id="pf_key_'.$k.'_'.$i.'" value="'.$key.'" readonly/></td>
                 <td><input type="text" name="english[]" id="pf_english_'.$k.'_'.$i.'"  value="'.$val.'" readonly/></td>
                 <td><input type="text" name="translation[]" id="pf_translation_'.$k.'_'.$i.'" value="'.$translatedJsonVal[$k][$key]["translatedData"].'" /><input type="hidden" name="edited[]" id="pf_edited_'.$k.'_'.$i.'" value="'.$translatedJsonVal[$k][$key]["edited"].'"/></td>
                 </tr>';
                 
              }else{ // it will run when developer add new key in translation and not found in DB
                $html.='
                 <tr>
                 <td><input type="text" name="key[]" id="pf_key_'.$k.'_'.$i.'" value="'.$key.'" readonly/></td>
                 <td><input type="text" name="english[]" id="pf_english_'.$k.'_'.$i.'"  value="'.$val.'" readonly/></td>';
                 if($k == 'en_US'){// auto complete English text when load first time for new added key
                  $html.= '<td><input type="text" name="translation[]" id="pf_translation_'.$k.'_'.$i.'" value="'.$val.'" /><input type="hidden" name="edited[]" id="pf_edited_'.$k.'_'.$i.'" value="0"/></td>';
                 }else{
                 $html.= '<td><input type="text" name="translation[]" id="pf_translation_'.$k.'_'.$i.'" value="" /><input type="hidden" name="edited[]" id="pf_edited_'.$k.'_'.$i.'" value="0"/></td>';
                 }
                 $html.= '</tr>';
                 
                 
              }
              $i++;
            }
           $html.='</tbody>
           <tfoot>
             
           </tfoot>
        </table>'; 
    }

      
       
    
    return $html;       
}

   protected function getTranslationData(){
    return $translationData = array(
                          'ecomm_no_interest' => 'No interest',
                          'ecomm_tell_me_more' => 'Tell me more',
                          'ecomm_redirect_to_payment_form' => 'You will redirect to secured payment form'

        );
   }

   protected function getAllStoresLanguages(){
         
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
        }
     }

     return $storeLangArr;

   }


 
   
}