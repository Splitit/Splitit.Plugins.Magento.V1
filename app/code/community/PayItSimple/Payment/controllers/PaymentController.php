<?php

class PayItSimple_Payment_PaymentController extends Mage_Core_Controller_Front_Action
{
    public function helpAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function termsAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function apiLoginAction(){
        
        $storeId = Mage::app()->getStore()->getStoreId();
        $api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);
        
        $installmentsInDropdown = [];
        $response = [
                        "status" => false,
                        "error" => "",
                        "success"=>"",
                        "data" => "",
                        "installmentNum" => "1",

        ];
        /*if ($api->isLogin()){

            $result = Mage::getSingleton("pis_payment/pisMethod")->getValidNumberOfInstallments($api);
            $responseData = json_decode($result);
            if(!empty($responseData) && isset($responseData->ResponseHeader) && $responseData->ResponseHeader){

                $selectInstallmentSetup = Mage::getStoreConfig('payment/pis_cc/select_installment_setup');
                $totalAmount = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
                $options = $responseData->ValidNumberOfInstallments;
                //$options = Mage::getModel('pis_payment/source_installments')->toOptionArray();
                $installments = [];
                if($totalAmount < 100){
                    $installments[] = "Splitit only support amount more than 100";
                    $response["installmentNum"] = 0;
                }else{
                    if($selectInstallmentSetup == "fixed"){ // Select Fixed installment setup
                    
                        $fixedInstallments = Mage::getStoreConfig('payment/pis_cc/fixed_installment');
                        foreach (explode(',', $fixedInstallments) as $n) {

                            if(in_array($n, $options)){
                                $installments[$n] = $n.' Installments of '. Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol().round($totalAmount/$n,2);  
                                $installmentsInDropdown[$n] = round($totalAmount/$n,2);                           
                            }
                        }
                        
                    }else{ // Select Depanding on cart installment setup
                        
                        $depandingOnCartInstallments = Mage::getStoreConfig('payment/pis_cc/depanding_on_cart_total_values');
                        $depandingOnCartInstallmentsArr = json_decode($depandingOnCartInstallments);
                        $dataAsPerCurrency = [];
                        foreach($depandingOnCartInstallmentsArr as $data){
                            $dataAsPerCurrency[$data->doctv->currency][] = $data->doctv;
                        }
                        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
                        if(count($dataAsPerCurrency) && isset($dataAsPerCurrency[$currentCurrencyCode])){
                            
                            foreach($dataAsPerCurrency[$currentCurrencyCode] as $data){
                                if($totalAmount >= $data->from && !empty($data->to) && $totalAmount <= $data->to){ 
                                    foreach (explode(',', $data->installments) as $n) {

                                        if(in_array($n, $options)){
                                            $installments[$n] = $n.' Installments of '. Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol().round($totalAmount/$n,2);    
                                            $installmentsInDropdown[$n] = round($totalAmount/$n,2);                        
                                        }
                                    }
                                    break;
                                }else if($totalAmount >= $data->from && empty($data->to)){
                                    foreach (explode(',', $data->installments) as $n) {

                                        if(in_array($n, $options)){
                                            $installments[$n] = $n.' Installments of '. Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol().round($totalAmount/$n,2);   
                                            $installmentsInDropdown[$n] = round($totalAmount/$n,2);                         
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    }    
                }
                
                // set how much installments to be show in checkout page dropdown
                Mage::getSingleton('core/session')->setInstallmentsInDropdown($installmentsInDropdown);

                $response["data"] = $installments;
                $response["status"] = true;
            }else if(!empty($responseData) && isset($responseData->ResponseStatus)){
                $response["error"] = $responseData->ResponseStatus->Message;
            }     
            
        }else{
            $response["error"] = $api->getError();
        }*/
        if ($api->isLogin()){
            Mage::log('=========splitit logging start=========');
            $ipnForLogs = Mage::getSingleton('core/session')->getSplititSessionid();
            Mage::log('Splitit session Id : '.$ipnForLogs);
            $response["status"] = true;
            $paymentMode = Mage::helper('pis_payment')->getPaymentMode();
            // get plan number from session if already created
            //$planFromSession = Mage::getSingleton('core/session')->getSplititInstallmentPlanNumber();

            if($paymentMode == "hosted_solution"){

                $initResponse = Mage::getModel("pis_payment/pisMethod")->installmentplaninitForHostedSolution();
                $response["data"] = $initResponse["data"];
                if($initResponse["status"]){
                    $response["status"] = true;
                }
                if(isset($initResponse["emptyFields"]) && $initResponse["emptyFields"]){
                    $response["data"] = $result["data"];    
                }
                if(isset($initResponse["checkoutUrl"]) && $initResponse["checkoutUrl"] != ""){
                    $response["checkoutUrl"] = $initResponse["checkoutUrl"];        
                }
            }
        }else{
            foreach ($api->getError() as $key => $value) {
                $response["error"] .= $value." ";    
            }
            
        }
        echo $jsonData = Mage::helper('core')->jsonEncode($response);

        //echo $jsonData = json_encode($response);
        return ;
        
    }

    public function installmentplaninitAction(){

        $params = $this->getRequest()->getParams();
        $selectedInstallment = "";
        $response = [
                        "status" => false,
                        "error" => "",
                        "success"=>"",
                        "data" => "",
                        
        ];
        if(isset($params["selectedInstallment"])){
            $selectedInstallment = $params["selectedInstallment"];
        }
        if($selectedInstallment == ""){
            $response["data"] = "Please select Number of Installments";
            echo $jsonData = Mage::helper('core')->jsonEncode($response);
            return ;
        }
        $api = Mage::getSingleton("pis_payment/pisMethod");
        $splititSessionId = Mage::getSingleton('core/session')->getSplititSessionid();

        if ($splititSessionId != ""){
            $result = Mage::getSingleton("pis_payment/pisMethod")->installmentplaninit($api, $selectedInstallment);
            $response["data"] = $result["data"];
            if($result["status"]){
                $response["status"] = true;
            }
            if(isset($result["emptyFields"]) && $result["emptyFields"]){
                $response["data"] = $result["data"];    
            }
            
        }else{
            
            $response["data"] = "703 - Session is not valid";
        }
        

        
        echo $jsonData = Mage::helper('core')->jsonEncode($response);   
        return ;
    }

    public function testAction(){
        $params = "02741420601104472804";
        $tablePrefix = (string) Mage::getConfig()->getTablePrefix();
        $db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql1 = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "'.$params.'" and order_created = 1'; 
        $data = $db_read->fetchRow($sql1);
        print_r($data);

    }

    public function successExitAction(){
        
        $params = $this->getRequest()->getParams();
        // remove plan from session which were created when user click on radio button
        //Mage::getSingleton('core/session')->setSplititInstallmentPlanNumber("");
        Mage::getSingleton('core/session')->setInstallmentPlanNumber($params["InstallmentPlanNumber"]); 
        Mage::log('======= successExitAction :  =======InstallmentPlanNumber coming from splitit in url: '.$params["InstallmentPlanNumber"]);
        
        $tablePrefix = (string) Mage::getConfig()->getTablePrefix();
        $db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        // check if order already created with the installment plan number coming from in parameters via async call(async run before response). Get data from SPLITIT_HOSTED_SOLUTION table
        $sql1 = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "'.$params["InstallmentPlanNumber"].'" and order_created = 1'; 
        $data = $db_read->fetchRow($sql1);
        // check if order already created via Async etc.
        if(count($data) && $data["order_id"] != 0 && $data["order_increment_id"] != null){

            Mage::log('======= check if order already created via Async etc.   ======= ');
            Mage::getSingleton('core/session')->setOrderIncrementId($data["order_increment_id"]);
            Mage::getSingleton('core/session')->setOrderId($data["order_id"]); 
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/success")->sendResponse();
            return;
        }


        // get installmentplan details        
        $storeId = Mage::app()->getStore()->getStoreId();
        $api = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->_initApi($storeId = null);
        $planDetails = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->getInstallmentPlanDetails($api );
        
        Mage::log('======= get installmentplan details :  ======= ');
        Mage::log($planDetails);
        // get plan number info from database table SPLITIT_HOSTED_SOLUTION
        $sql = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "'.$params["InstallmentPlanNumber"].'"';
        //$data = $db_read->fetchAllRow($sql); // fetch All row in a table
        $data = $db_read->fetchRow($sql);
        
        $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        $orderIncrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $orderObj = Mage::getModel('sales/order')->load($orderId);
        $grandTotal = $orderObj->getGrandTotal();

        if(count($data) && $grandTotal == $planDetails["grandTotal"]){

            $payment = $orderObj->getPayment();
            $paymentAction = Mage::helper('pis_payment')->getPaymentAction();
            
            $payment->setTransactionId(Mage::getSingleton('core/session')->getInstallmentPlanNumber());
            $payment->setParentTransactionId(Mage::getSingleton('core/session')->getInstallmentPlanNumber());
            $payment->setInstallmentsNo($planDetails["numberOfInstallments"]);
            $payment->setIsTransactionClosed(0);
            $payment->setCurrencyCode($planDetails["currencyCode"]);
            $payment->setCcType($planDetails["cardBrand"]["Code"]);   
            $payment->setIsTransactionApproved(true);

            $payment->registerAuthorizationNotification($grandTotal);
            
            $orderObj->addStatusToHistory(
            $orderObj->getStatus(),
                'Payment InstallmentPlan was created with number ID: '
                . Mage::getSingleton('core/session')->getInstallmentPlanNumber(),
                false
            );
            $updateStatus = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->updateRefOrderNumber($api, $orderObj);        
            if($updateStatus["status"] == false){
                Mage::throwException(
                    Mage::helper('payment')->__($updateStatus["data"])
                );    
            }

            if($paymentAction == "authorize_capture"){

                $sessionId = Mage::getSingleton('core/session')->getSplititSessionid();
                $params = array('InstallmentPlanNumber' => Mage::getSingleton('core/session')->getInstallmentPlanNumber());
                $params = array_merge($params, array("RequestHeader"=> array('SessionId' => $sessionId)));
                $result = $api->startInstallment(Mage::getSingleton("pis_payment/pisPaymentFormMethod")->getApiUrl(), $params);
                $payment->setShouldCloseParentTransaction(true);
                $payment->setIsTransactionClosed(1);
                $payment->registerCaptureNotification($grandTotal);
                $orderObj->addStatusToHistory(
                    false,
                    'Payment NotifyOrderShipped was sent with number ID: '.Mage::getSingleton('core/session')->getInstallmentPlanNumber(), false
                );
            }
            //$orderObj->queueNewOrderEmail();
            $orderObj->sendNewOrderEmail();
            $orderObj->save();

            // update order_created in splitit_hosted_solution
            $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
            // get order id and increment number from session to update in splitit_hosted_solution table
            
            $updateQue = 'UPDATE `' . $tablePrefix . 'splitit_hosted_solution` SET order_created = 1, order_id = "'.$orderId.'", order_increment_id = "'.$orderIncrementId.'" WHERE installment_plan_number = "'.$params["InstallmentPlanNumber"].'"';
            $db_write->query($updateQue);
            Mage::log('====== Order Id =====:'.$orderId.'==== Order Increment Id ======:'.$orderIncrementId);


            //Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/success")->sendResponse();
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."checkout/onepage/success")->sendResponse();
        }else{

            Mage::log('====== Order cancel due to Grand total and Payment detail total coming from Api is not same. =====');
            $cancelResponse = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
            if($cancelResponse["status"]){
                Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/cancel")->sendResponse();
            }

        }

        
        
        
    }
    public function successAction(){
        $orderIncrementId = Mage::getSingleton('core/session')->getOrderIncrementId();
        $orderId = Mage::getSingleton('core/session')->getOrderId();
        // check if order created else redirect to cart page
        if($orderId != "" && $orderIncrementId != ""){
            $this->loadLayout();
            $this->renderLayout();
        }else{
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."checkout/cart")->sendResponse();
        }
        
        
    }

    public function cancelAction(){
        $this->loadLayout();
        $this->renderLayout();
    }

    public function successAsyncAction(){
        $params = $this->getRequest()->getParams();
        Mage::log('======= successAsyncAction :  =======InstallmentPlanNumber coming from splitit in url: '.$params["InstallmentPlanNumber"]);
        Mage::getSingleton('core/session')->setInstallmentPlanNumber($params["InstallmentPlanNumber"]); 
        $tablePrefix = (string) Mage::getConfig()->getTablePrefix();
        $db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        
        // get installmentplan details        
        $storeId = Mage::app()->getStore()->getStoreId();
        $api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);
        $planDetails = Mage::getSingleton("pis_payment/pisMethod")->getInstallmentPlanDetails($api );
        
        
        // get plan number info from database table SPLITIT_HOSTED_SOLUTION
        $sql = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "'.$params["InstallmentPlanNumber"].'"';
        //$data = $db_read->fetchAllRow($sql); // fetch All row in a table
        $data = $db_read->fetchRow($sql);
        $store = Mage::getSingleton('core/store')->load($storeId);
        $quote = Mage::getModel('sales/quote')->setStore($store)->load($data["quote_id"]);
        $grandTotal = $quote->getGrandTotal();
        if(count($data) && $grandTotal == $planDetails["grandTotal"]){
            
            Mage::log('======= if condition: ===========  Grand Total: '.$grandTotal);
            $quote->assignCustomer($quote->getCustomer());
            $quote->collectTotals()->getPayment()->setMethod('pis_cc');
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();

            $order = $service->getOrder();
            $order->save();
            $quote->delete();
            // update order_created in splitit_hosted_solution
            $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
            // get order id and increment number from session to update in splitit_hosted_solution table
            $orderIncrementId = Mage::getSingleton('core/session')->getOrderIncrementId();
            $orderId = Mage::getSingleton('core/session')->getOrderId();
            $updateQue = 'UPDATE `' . $tablePrefix . 'splitit_hosted_solution` SET order_created = 1, order_id = "'.$orderId.'", order_increment_id = "'.$orderIncrementId.'" WHERE installment_plan_number = "'.$params["InstallmentPlanNumber"].'"';
            $db_write->query($updateQue);
            return true;
        }else if($quote->getId() != ""){
            Mage::log('======= else condition: =========== call cancel api ');
            $cancelResponse = Mage::getSingleton("pis_payment/pisMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
            if($cancelResponse["status"]){
                Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/cancel")->sendResponse();
            }

        }
    }

    public function cancelExitAction(){

        /*$params = $this->getRequest()->getParams();
        $storeId = Mage::app()->getStore()->getStoreId();
        $api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);
        $cancelResponse = Mage::getSingleton("pis_payment/pisMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
        if($cancelResponse["status"]){
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/cancel")->sendResponse();
        }*/

        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getSplititQuoteId());
        
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
            Mage::helper('paypal/checkout')->restoreQuote();
            $order = Mage::getSingleton('checkout/session')->getLastRealOrder();
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)
                    ->setReservedOrderId(null)
                    ->save();
                Mage::getSingleton('checkout/session')
                    ->replaceQuote($quote)
                    ->unsLastRealOrderId();
                
            }
        }    
        Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."checkout/cart")->sendResponse();
    }

    public function redirectAction(){

        echo Mage::helper('pis_payment')->getCreditCardFormTranslation('ecomm_redirect_to_payment_form');


        $splititCheckoutUrl = Mage::getSingleton('checkout/session')->getSplititCheckoutUrl();
        $splititInstallmentPlanNumber = Mage::getSingleton('checkout/session')->getSplititInstallmentPlanNumber();
        if($splititCheckoutUrl != ""){
            $tablePrefix = (string) Mage::getConfig()->getTablePrefix();
            $db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql1 = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "'.$splititInstallmentPlanNumber.'"'; 
            $data = $db_read->fetchRow($sql1);
            // check if order already created via Async etc.
            if(count($data) && $data["order_id"] == 0 && $data["order_increment_id"] == null){
                $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
                $orderIncrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
                $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
                // get order id and increment number from session to update in splitit_hosted_solution table
                
                $updateQue = 'UPDATE `' . $tablePrefix . 'splitit_hosted_solution` SET order_id = "'.$orderId.'", order_increment_id = "'.$orderIncrementId.'" WHERE installment_plan_number = "'.$splititInstallmentPlanNumber.'"';
                $db_write->query($updateQue);    
                Mage::app()->getFrontController()->getResponse()->setRedirect($splititCheckoutUrl)->sendResponse();
                //return true;
            }
        }
    }
}