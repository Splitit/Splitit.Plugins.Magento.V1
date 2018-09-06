<?php
class PayItSimple_Payment_Model_Observer
{
    public function insertBlock($observer)
    {
        $_block = $observer->getBlock();     
        $_type = $_block->getType();
        $extensionEnabled = Mage::getStoreConfig('payment/pis_cc/active')||Mage::getStoreConfig('payment/pis_paymentform/active')?true:false;
        if(!$extensionEnabled)
            return;
        if (($_type == 'catalog/product_price' && $_block->getTemplate()=='catalog/product/price.phtml') or $_type == 'checkout/cart_totals') {
            $_child = clone $_block;
            $_child->setType('payitsimple/block');
            if($_type == 'checkout/cart_totals'){
                $_block->setChild('child', $_child);
            }
            else{
                $_block->setChild('child'.$_child->getProduct()->getId(), $_child);
            }
            if($this->checkProductBasedAvailability("pis_cc")||$this->checkProductBasedAvailability("pis_paymentform")){
                $_block->setTemplate('payitsimple/splitprice.phtml');
            }
        }
    }

    public function paymentMethodIsActive(Varien_Event_Observer $observer) {
        
        $event           = $observer->getEvent();//print_r($event->getData());die("---sdf");
        $method          = $event->getMethodInstance();
        $result          = $event->getResult();
        $currencyCode    = Mage::app()->getStore()->getCurrentCurrencyCode();


            if($method->getCode() == "pis_cc"){
                $result->isAvailable = $this->checkAvailableInstallments("pis_cc")&&$this->checkProductBasedAvailability("pis_cc");
            }else if($method->getCode() == "pis_paymentform"){
                $result->isAvailable = $this->checkAvailableInstallments("pis_paymentform")&&$this->checkProductBasedAvailability("pis_paymentform");
            }

    }

    private function checkAvailableInstallments($paymentMethod){
        $installments = array();
        $totalAmount = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
        $selectInstallmentSetup = Mage::getStoreConfig('payment/'.$paymentMethod.'/select_installment_setup');
        $installmentsInDropdown = array();
        $options = Mage::getModel('pis_payment/source_installments')->toOptionArray();
        
        $depandOnCart = 0;
        // check if splitit extension is disable from admin
        $isDisabled = Mage::getStoreConfig('payment/'.$paymentMethod.'/active');
        if(!$isDisabled){
            return false;
        }

        // $selectInstallmentSetup == "" for checking when merchant first time upgrade extension that time $selectInstallmentSetup will be empty
        if($selectInstallmentSetup == "" || $selectInstallmentSetup == "fixed"){ // Select Fixed installment setup
            
            $fixedInstallments = Mage::getStoreConfig('payment/'.$paymentMethod.'/available_installments');
            foreach (explode(',', $fixedInstallments) as $n) {
                
                if((array_key_exists($n, $options))){
                    $installments[$n] = $n.' Installments of '. Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol().round($totalAmount/$n,2); 
                    $installmentsInDropdown[$n] = round($totalAmount/$n,2); 
                }
            }
            
        }else{ // Select Depanding on cart installment setup
            $depandOnCart = 1;  
            $depandingOnCartInstallments = Mage::getStoreConfig('payment/'.$paymentMethod.'/depanding_on_cart_total_values');
            $depandingOnCartInstallmentsArr = json_decode($depandingOnCartInstallments);
            $dataAsPerCurrency = array();
            foreach($depandingOnCartInstallmentsArr as $data){
                $dataAsPerCurrency[$data->doctv->currency][] = $data->doctv;
            }
            $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
            if(count($dataAsPerCurrency) && isset($dataAsPerCurrency[$currentCurrencyCode])){
                
                foreach($dataAsPerCurrency[$currentCurrencyCode] as $data){
                    if($totalAmount >= $data->from && !empty($data->to) && $totalAmount <= $data->to){
                        foreach (explode(',', $data->installments) as $n) {

                            if((array_key_exists($n, $options))){
                                $installments[$n] = $n.' Installments of '. Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol().round($totalAmount/$n,2);    
                                $installmentsInDropdown[$n] = round($totalAmount/$n,2);
                                                        
                            }
                        }
                        break;
                    }else if($totalAmount >= $data->from && empty($data->to)){
                        foreach (explode(',', $data->installments) as $n) {

                            if((array_key_exists($n, $options))){
                                $installments[$n] = $n.' Installments of '. Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol().round($totalAmount/$n,2);  
                                $installmentsInDropdown[$n] = round($totalAmount/$n,2); 
                                                         
                            }
                        }
                        break;
                    }
                }
            }
        } 



        if(count($installments) == 0){
            return false;
        }else{
            return true;
        }
    }
    
    public function checkProductBasedAvailability($paymentMethod) {
        $check = TRUE;
        if (Mage::getStoreConfig('payment/'.$paymentMethod.'/splitit_per_product')) {
            $cart = Mage::getSingleton('checkout/session')->getQuote();
// get array of all items what can be display directly
            $itemsVisible = $cart->getAllVisibleItems();
            $allowedProducts = Mage::getStoreConfig('payment/'.$paymentMethod.'/splitit_product_skus');
            $allowedProducts = explode(',', $allowedProducts);
            if (Mage::getStoreConfig('payment/'.$paymentMethod.'/splitit_per_product') == 1) {
                $check = TRUE;
                foreach ($itemsVisible as $item) {
                    if (!in_array($item->getProductId(), $allowedProducts)) {
                        $check = FALSE;
                        break;
                    }
                }
            }
            if (Mage::getStoreConfig('payment/'.$paymentMethod.'/splitit_per_product') == 2) {
                $check = FALSE;
                foreach ($itemsVisible as $item) {
                    if (in_array($item->getProductId(), $allowedProducts)) {
                        $check = TRUE;
                        break;
                    }
                }
            }
        }
//        var_dump($check);
        return $check;
    }

    public function orderCancelAfter(Varien_Event_Observer $observer){
        $event = $observer->getEvent();
        
        $order = $observer->getEvent()->getOrder();

        $payment = $order->getPayment();
        if($payment->getLastTransId() != ""){
            if($payment->getMethod() == "pis_cc"){
                $storeId = Mage::app()->getStore()->getStoreId();
                $api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);
                $sessionId = Mage::getSingleton('core/session')->getSplititSessionid();
                $installmentPlanNumber = $payment->getLastTransId();
                $cancelResponse = Mage::getModel("pis_payment/pisMethod")->cancelInstallmentPlan($api, $installmentPlanNumber);
                if(!$cancelResponse["status"]){
                    Mage::throwException(
                        Mage::helper('payment')->__($cancelResponse["data"])
                    );
                }

            }

            if($payment->getMethod() == "pis_paymentform"){
                $storeId = Mage::app()->getStore()->getStoreId();
                $api = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->_initApi($storeId = null);
                $sessionId = Mage::getSingleton('core/session')->getSplititSessionid();
                $installmentPlanNumber = $payment->getLastTransId();
                $cancelResponse = Mage::getModel("pis_payment/pisPaymentFormMethod")->cancelInstallmentPlan($api, $installmentPlanNumber);
                if(!$cancelResponse["status"]){
                    Mage::throwException(
                        Mage::helper('payment')->__($cancelResponse["data"])
                    );
                }

            }
        }
        
        
        
    }
}
