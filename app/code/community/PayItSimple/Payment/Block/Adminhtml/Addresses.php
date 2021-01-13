<?php
class PayItSimple_Payment_Block_Adminhtml_Addresses extends Mage_Adminhtml_Block_System_Config_Form_Field
{
   
public $_storeId = "";
 public function __construct()
    {
         parent::_construct();
         /*get store id in admin*/
         if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) /* store level*/
          {
              $store_id = Mage::getModel('core/store')->load($code)->getId();
          }
          elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) /* website level*/
          {
              $website_id = Mage::getModel('core/website')->load($code)->getId();
              $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
          }
          else /* default level */
          {
              $store_id = 0;
          }
          $this->_storeId = $store_id;

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
      $doctv = Mage::getStoreConfig('payment/pis_cc/depanding_on_cart_total_values', $this->_storeId); 
      $html = "";
      if($doctv == ""){
        $html = $this->getTableHtmlWhenEmpty();
      }else{
        $html = $this->getTableHtmlWhenNotEmpty($doctv);
      }


       return $html; 
   }

   /*return html when there is not prior configuration is set for Depending on cart total*/
   protected function getTableHtmlWhenEmpty()
   {
      $html = '<table class="data border splitit" id="tiers_table" cellspacing="0" border="1">
      <div class="tiers_table_overlay"></div>
         <colgroup>
            <col width="120">
            <col width="95">
            <col>
            <col width="1">
         </colgroup>
         <thead>
            <tr class="headings">
               <th style="display:none">Website</th>
               <th>Cart total</th>
               <th>#Installments</th>
               <th>Currency</th>
               <th class="last">Action</th>
            </tr>
         </thead>
         <tbody id="tier_price_container">
            <tr>
               <td>
                From<br><label>'. $this->_getFirstAvailableCurrencySymbol() .'</label> <input style="max-width:90%!important;" type="text" class="doctv_from" name="doctv_from" /><br>To<br><label>'. $this->_getFirstAvailableCurrencySymbol() .'<input type="text" style="max-width:90%!important;" name="doctv_to" class="doctv_to" />
               </td>
               <td>
                <select name="doctv_installments" class=" select multiselect doctv_installments" size="10" multiple="multiple">
                  <option value="1">1 Installment</option>
                  <option value="2">2 Installments</option>
                  <option value="3">3 Installments</option>
                  <option value="4">4 Installments</option>
                  <option value="5">5 Installments</option>
                  <option value="6">6 Installments</option>
                  <option value="7">7 Installments</option>
                  <option value="8">8 Installments</option>
                  <option value="9">9 Installments</option>
                  <option value="10">10 Installments</option>
                  <option value="11">11 Installments</option>
                  <option value="12">12 Installments</option>
                  </select>
               </td>
               <td>
                <select name="doctv_currency" class=" select doctv_currency">
                  '.$this->_getCurrencies().'
                 </select>
               </td>
               <td>
                <button title="Delete Tier" type="button" class="scalable delete icon-btn delete-product-option" onclick="deleteRow(this);"><span><span><span>Delete</span></span></span></button>
               </td>
            </tr>
         </tbody>
         <tfoot>
            <tr>
               <td style="display:none"></td>
               <td colspan="4" class="a-right"><button id="id_e33ad31ef8ac28bb6e4a4fd4d54f5f9e" title="Add Tier" type="button" class="scalable add" onclick="addRow();" style=""><span><span><span>Add Tier</span></span></span></button></td>
            </tr>
         </tfoot>
      </table>'; 
      return $html;
   }

   /* return html when there is prior configuration is set for Depending on cart total */
   protected function getTableHtmlWhenNotEmpty($doctv)
   {
      $doctv = json_decode($doctv);
      $currencySymbolsArray = json_decode($this->_getAvailableCurrencySymbolsArray(),true);

      $html = '<table class="data border splitit" id="tiers_table" cellspacing="0" border="1">
      <div class="tiers_table_overlay"></div>
         <colgroup>
            <col width="120">
            <col width="95">
            <col>
            <col width="1">
         </colgroup>
         <thead>
            <tr class="headings">
               <th style="display:none">Website</th>
               <th>Cart total</th>
               <th>#Installments</th>
               <th>Currency</th>
               <th class="last">Action</th>
            </tr>
         </thead>
         <tbody id="tier_price_container">';
      $rowHtml = "";   
      foreach ($doctv as $key => $value) {
        $rowHtml .= '<tr>';
        $rowHtml .= '<td> From<br><label>'. $currencySymbolsArray[$value->doctv->currency] .'</label> <input type="text" style="max-width:90%!important;" class="doctv_from" name="doctv_from" value="'.$value->doctv->from.'" /><br>To<br><label>'. $currencySymbolsArray[$value->doctv->currency] .'</label> <input type="text" style="max-width:90%!important;" name="doctv_to" class="doctv_to" value="'.$value->doctv->to.'"/> </td>';
        $rowHtml .= '<td>
                <select name="doctv_installments" class=" select multiselect doctv_installments" size="10" multiple="multiple">';
        $i = 2;
        $installments = explode(",", $value->doctv->installments);
        $selected = "";
        for($i=2; $i<=12; $i++){
          if(in_array($i, $installments)){
            $selected = 'selected="selected"';
          }
          $rowHtml .= '<option value="'.$i.'" '.$selected.'>'.$i.' Installments</option>';  
          $selected = "";
          
        } 
        $rowHtml .= '</select></td>';          
                  
               
        $rowHtml .= '<td>
                <select name="doctv_currency" class=" select doctv_currency">
                  '.$this->_getSelectedCurrency($value->doctv->currency).'
                </select>  
               </td>';
        $rowHtml .= '<td>
                <button title="Delete Tier" type="button" class="scalable delete icon-btn delete-product-option" onclick="deleteRow(this);"><span><span><span>Delete</span></span></span></button>
               </td>
            </tr>';

      }  

      $html .= $rowHtml;
      $html .= '</tbody>
         <tfoot>
         <tr>
               <td style="display:none"></td>
               <td colspan="4" class="a-right"><button id="id_e33ad31ef8ac28bb6e4a4fd4d54f5f9e" title="Add Tier" type="button" class="scalable add" onclick="addRow();" style=""><span><span><span>Add Tier</span></span></span></button></td>
            </tr>
         </tfoot>
      </table>';
            
      return $html;
   }

   /*get active currencies in the store and show dropdown in table*/
   protected function _getCurrencies() 
   {
      $currencies = array();
      /*get allowed currencies from all websites/store ( core_config_data )*/
      $codes = $this->_getAllAllowedCurrencies();
      /* $codes = Mage::app()->getStore()->getAvailableCurrencyCodes(true);//print_r($codes);die; */
      $currenyOptions = "";
      if (is_array($codes) && count($codes) > 0) {
          $rates = Mage::getModel('directory/currency')->getCurrencyRates(Mage::app()->getStore()->getBaseCurrency(),$codes);

          foreach ($codes as $code) {
              if (isset($rates[$code])) {
                  $currencies[$code] = Mage::app()->getLocale()
                  ->getTranslation($code, 'nametocurrency');
                  $currenyOptions .='<option value="'.$code.'">'.$code.'</option>';
              }
          }
      }
      return $currenyOptions;
  
   }
  
  /*get active currencies and make them selected in dropdown in table*/
    protected function _getSelectedCurrency($currency)
   {
      $currencies = array();
      /*get allowed currencies from all websites/store ( core_config_data )*/
      $codes = $this->_getAllAllowedCurrencies();
      /*$codes = Mage::app()->getStore()->getAvailableCurrencyCodes(true);//print_r($codes);die;*/
      $currenyOptions = "";
      if (is_array($codes) && count($codes) > 0) {
          $rates = Mage::getModel('directory/currency')->getCurrencyRates(Mage::app()->getStore()->getBaseCurrency(), $codes);
          $selected = '';
          foreach ($codes as $code) {
              if (isset($rates[$code])) {
                  $currencies[$code] = Mage::app()->getLocale()
                  ->getTranslation($code, 'nametocurrency');
                  if($code == $currency){
                    $selected = "selected=selected";  
                  }
                  
                  $currenyOptions .='<option value="'.$code.'" '.$selected.'>'.$code.'</option>';
                  $selected = '';
              }
          }
      }
      return $currenyOptions;
  
   }

   protected function _getAllAllowedCurrencies(){
      $currencyCode = Mage::getModel('core/config_data')
                      ->getCollection()
                      ->addFieldToFilter('path','currency/options/allow')
                      ->getData();
      /*get unique currency*/
      foreach ($currencyCode as $key => $value) {
        foreach(explode(",", $value["value"]) as $k =>$v){
          $codes[] = $v;    
        }
        
      }
      return $codes = array_unique($codes);
   }

   protected function _getBaseCurrency(){
    $currentCurrency = Mage::app()->getStore()->getBaseCurrencyCode();
    return $currencyLabel = '<input disabled class="doctv_currency" value="'.$currentCurrency.'"/>';
   }

   protected function _getCurrencySymbol(){
    return Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol();
   }

   protected function _getAvailableCurrencySymbolsArray(){
      /* $codes = Mage::app()->getStore()->getAvailableCurrencyCodes(true); */
      $codes = $this->_getAllAllowedCurrencies(); 
      $currencySymbolsArray = array();
      foreach ($codes as $key => $value) {
        $currencySymbolsArray[$value] = Mage::app()->getLocale()->currency($value)->getSymbol();
      }
      return json_encode($currencySymbolsArray);
   }

   protected function _getFirstAvailableCurrencySymbol(){
      $codes = Mage::app()->getStore()->getAvailableCurrencyCodes(true);
      $firstCurrencySymbol = array();
      foreach ($codes as $key => $value) {
        $firstCurrencySymbol = Mage::app()->getLocale()->currency($value)->getSymbol();
        break;
      }
      return $firstCurrencySymbol;
   }

   /*code for translation*/

   /**
     * Return ajax url for button
     *
     * @return string
     */
    public function getAjaxProdListUrl()
    {
        if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) /* store level*/
        {
            $store_id = Mage::getModel('core/store')->load($code)->getId();
        }
        elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) /* website level*/
        {
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        }
        else /* default level*/
        {
            $store_id = 0;
        }
        return Mage::helper('adminhtml')->getUrl('adminhtml/adminhtml_payitsimple/prodlist', array('store_id' => $store_id));
    } 
   
}