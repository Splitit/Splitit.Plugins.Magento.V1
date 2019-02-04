<?php

class PayItSimple_Payment_PaymentController extends Mage_Core_Controller_Front_Action {

    public function helpAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function termsAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function prodlistAction(){
        $params = $this->getRequest()->getParams();
        $result = array();
        if(isset($params['isAjax'])&&$params['isAjax']){
            $Productlist = Mage::getSingleton('pis_payment/source_productskus');
            if((isset($params['term'])&&$params['term'])||(isset($params['prodIds'])&&$params['prodIds'])){
                $result = $Productlist->toOptionArray($params);
            }
        }
        echo json_encode($result);
        return;
    }

    public function apiLoginAction() {

        $storeId = Mage::app()->getStore()->getStoreId();
        $api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);

        $installmentsInDropdown = array();
        $response = array(
            "status" => false,
            "error" => "",
            "success" => "",
            "data" => "",
            "installmentNum" => "1",
        );

        if ($api->isLogin()) {
            Mage::log('=========splitit logging start=========');
            $ipnForLogs = Mage::getSingleton('core/session')->getSplititSessionid();
            Mage::log('Splitit session Id : ' . $ipnForLogs);
            $response["status"] = true;
            /*$paymentMode = Mage::helper('pis_payment')->getPaymentMode();*/
            // get plan number from session if already created
            //$planFromSession = Mage::getSingleton('core/session')->getSplititInstallmentPlanNumber();

            /*if ($paymentMode == "hosted_solution") {

                $initResponse = Mage::getModel("pis_payment/pisMethod")->installmentplaninitForHostedSolution();
                $response["data"] = $initResponse["data"];
                if ($initResponse["status"]) {
                    $response["status"] = true;
                }
                if (isset($initResponse["emptyFields"]) && $initResponse["emptyFields"]) {
                    $response["data"] = $result["data"];
                }
                if (isset($initResponse["checkoutUrl"]) && $initResponse["checkoutUrl"] != "") {
                    $response["checkoutUrl"] = $initResponse["checkoutUrl"];
                }
            }*/
        } else {
            foreach ($api->getError() as $key => $value) {
                $response["error"] .= $value . " ";
            }
        }
        Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        //echo $jsonData = json_encode($response);
        return;
    }

    public function installmentplaninitAction() {
        Mage::log('=========splitit : InstallmentPlan Init for Embedded =========');
        $params = $this->getRequest()->getParams();
        $selectedInstallment = "";
        $response = array(
            "status" => false,
            "error" => "",
            "success" => "",
            "data" => "",
            "attempt3DSecure" => false
        );
        if (isset($params["selectedInstallment"])) {
            $selectedInstallment = $params["selectedInstallment"];
        }
        if ($selectedInstallment == "") {
            $response["data"] = "Please select Number of Installments";
            Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
            //echo $jsonData = Mage::helper('core')->jsonEncode($response);
            return;
        }
        $api = Mage::getSingleton("pis_payment/pisMethod");
        $splititSessionId = Mage::getSingleton('core/session')->getSplititSessionid();

        if ($splititSessionId != "") {
            $result = Mage::getSingleton("pis_payment/pisMethod")->installmentplaninit($api, $selectedInstallment);
            $response['attempt3DSecure']=$result['attempt3DSecure'];
            $response["data"] = $result["data"];
            if ($result["status"]) {
                $response["status"] = true;
            }
            if (isset($result["emptyFields"]) && $result["emptyFields"]) {
                $response["data"] = $result["data"];
            }
        } else {

            $response["data"] = "703 - Session is not valid";
        }

        Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));

        //echo $jsonData = Mage::helper('core')->jsonEncode($response);   
        return;
    }
    
    public function cipAction() {
        Mage::log('=========splitit : CreateInstallmentPlan for Embedded =========');
        $params = $this->getRequest()->getParams();
        $response = array(
            "status" => false,
            "error" => "",
            "msg" => "",
            "urlData" => "",
            "placeOrder" => false
        );
        /*print_r($params);exit;*/
        try{
            if(!isset($params['ccDetails'])||!$params['ccDetails']||!isset($params['ccDetails']['cc_number'])||!$params['ccDetails']['cc_number']){
                 Mage::throwException(
                                Mage::helper('payment')->__("Please fill required card details")
                            );
            }
            $splititSessionId = Mage::getSingleton('core/session')->getSplititSessionid();

            if ($splititSessionId != "") {
                    /*$api = Mage::getSingleton("pis_payment/pisMethod")->getApi();*/
                    /*echo '<pre>GRAND TOTAL===';
                    print_r(Mage::getModel('checkout/session')->getQuote()->getGrandTotal());
                    echo 'CC Number===';
                    print_r(Mage::getModel('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getInfoInstance()->getCcNumber());
                    die;*/
                    $result = Mage::getSingleton("pis_payment/pisMethod")->createInstallmentPlanAfterInit($params['ccDetails']);
                    Mage::getSingleton('checkout/session')->setSecureThreeData($result);
                    $result = Mage::helper('core')->jsonDecode($result);
                    /*$result["ResponseHeader"]["Errors"]=array();*/
                    /*print_r($result);die;*/
                    // show error if there is any error from spliti it when click on place order
                    if(!$result["ResponseHeader"]["Succeeded"]){
                        $errorMsg = "";
                        if(isset($result["serverError"])){
                            $errorMsg = $result["serverError"];
                            Mage::log('=========splitit : create api have serverError for Embedded =========');
                            Mage::log($errorMsg);
                            Mage::throwException(
                                Mage::helper('payment')->__($errorMsg)
                            ); 
                             
                        }else{
                            if(isset($result["ResponseHeader"]["Errors"])&&isset($result["ResponseHeader"]["Errors"][0])&&isset($result["ResponseHeader"]["Errors"][0]['ErrorCode'])&&($result["ResponseHeader"]["Errors"][0]['ErrorCode']=='641')){
                                $response['status']=true;
                                $response['msg']=$result["ResponseHeader"]["Errors"][0]['Message'];
                                $secure3Ddata = Mage::getSingleton("pis_payment/pisMethod")->get3DSecureParameters();
                                $secure3Ddata = Mage::helper('core')->jsonDecode($secure3Ddata);
                                // print_r($secure3Ddata);die;
                                // show error if there is any error from splitit
                                if(!$secure3Ddata["ResponseHeader"]["Succeeded"]){
                                    $errorMsg = "";
                                    if(isset($secure3Ddata["serverError"])){
                                        $errorMsg = $secure3Ddata["serverError"];
                                        Mage::log('=========splitit : Get3DSecureParameters api have serverError for Embedded =========');
                                        Mage::log($errorMsg);
                                        Mage::throwException(
                                            Mage::helper('payment')->__($errorMsg)
                                        ); 
                                         
                                    }else{
                                        foreach ($secure3Ddata["ResponseHeader"]["Errors"] as $key => $value) {
                                        $errorMsg .= $value["ErrorCode"]." : ".$value["Message"];
                                        }
                                        Mage::log('=========splitit : Get3DSecureParameters api have ResponseHeader Error for Embedded =========');
                                        Mage::log($errorMsg);
                                        Mage::throwException(
                                            Mage::helper('payment')->__($errorMsg)
                                        );         
                                    }                                    
                                }
                                unset($secure3Ddata['ResponseHeader']);
                                $response['urlData'] = $secure3Ddata;
                            } else {
                                if(empty($result["ResponseHeader"]["Errors"])){
                                    $response['placeOrder']=true;
                                    $response['placeOrderUrl']=Mage::getBaseUrl()."payitsimple/payment/secure3DSuccess";
                                }
                                foreach ($result["ResponseHeader"]["Errors"] as $key => $value) {
                                $errorMsg .= $value["ErrorCode"]." : ".$value["Message"];
                                }
                                Mage::log('=========splitit : create api have ResponseHeader Error for Embedded =========');
                                Mage::log($errorMsg);
                                Mage::throwException(
                                    Mage::helper('payment')->__($errorMsg)
                                );         
                            }
                        }
                    }
                    /*else {
                        $response['placeOrder']=true;
                        $response['placeOrderUrl']=Mage::getBaseUrl()."payitsimple/payment/secure3DSuccess";
                    }*/
            } else {
                $errorMsg = "703 - Session is not valid";
                Mage::log('=========splitit : create api have serverError for Embedded =========');
                Mage::log($errorMsg);
                Mage::throwException(
                    Mage::helper('payment')->__($errorMsg)
                );
            }
        } catch(Exception $e){
            $response["error"] = $e->getMessage();
        }

        Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        return;
    }

    public function testAction() {
        $params = "02741420601104472804";
        $tablePrefix = (string) Mage::getConfig()->getTablePrefix();
        $db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql1 = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "' . $params . '" and order_created = 1';
        $data = $db_read->fetchRow($sql1);
        //print_r($data);
    }

    public function secure3DFailureAction(){
        $params = $this->getRequest()->getParams();
        Mage::getSingleton('checkout/session')->addError("3D secure validation failed. Please contact Bank.");
        Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/cart")->sendResponse();
    }

    public function secure3DSuccessAction(){
        $params = $this->getRequest()->getParams();
        try{
            $orderId = Mage::getSingleton("pis_payment/pisMethod")->place3DSecureOrder();
            if($orderId){
                Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/onepage/success")->sendResponse();
            } else {
                Mage::throwException(
                    Mage::helper('payment')->__("Unable to place order.")
                );
            }
        } catch(Exception $e){
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/cart")->sendResponse();
        }
    }

    public function successExitAction() {

        $params = $this->getRequest()->getParams();
        // remove plan from session which were created when user click on radio button
        //Mage::getSingleton('core/session')->setSplititInstallmentPlanNumber("");
        Mage::getSingleton('core/session')->setInstallmentPlanNumber($params["InstallmentPlanNumber"]);
        Mage::log('======= successExitAction :  =======InstallmentPlanNumber coming from splitit in url: ' . $params["InstallmentPlanNumber"]);

        $tablePrefix = (string) Mage::getConfig()->getTablePrefix();
        $db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        // check if order already created with the installment plan number coming from in parameters via async call(async run before response). Get data from SPLITIT_HOSTED_SOLUTION table
        $sql1 = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "' . $params["InstallmentPlanNumber"] . '" and order_created = 1';
        $data = $db_read->fetchRow($sql1);
        // check if order already created via Async etc.
        if (count($data) && $data["order_id"] != 0 && $data["order_increment_id"] != null) {

            Mage::log('======= check if order already created via Async etc.   ======= ');
            Mage::getSingleton('core/session')->setOrderIncrementId($data["order_increment_id"]);
            Mage::getSingleton('core/session')->setOrderId($data["order_id"]);
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "payitsimple/payment/success")->sendResponse();
            return;
        }


        // get installmentplan details        
        $storeId = Mage::app()->getStore()->getStoreId();
        $api = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->_initApi($storeId = null);
        $planDetails = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->getInstallmentPlanDetails($api);

        Mage::log('======= get installmentplan details :  ======= ');
        Mage::log($planDetails);
        // get plan number info from database table SPLITIT_HOSTED_SOLUTION
        $sql = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "' . $params["InstallmentPlanNumber"] . '"';
        //$data = $db_read->fetchAllRow($sql); // fetch All row in a table
        $data = $db_read->fetchRow($sql);

        $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        $orderIncrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $orderObj = Mage::getModel('sales/order')->load($orderId);
        $grandTotal = number_format((float) $orderObj->getGrandTotal(), 2, '.', '');
        $planDetails["grandTotal"] = number_format((float) $planDetails["grandTotal"], 2, '.', '');
        Mage::log('======= grandTotal(orderObj):' . $grandTotal . ', grandTotal(planDetails):' . $planDetails["grandTotal"] . '   ======= ');
        if (count($data) && $grandTotal == $planDetails["grandTotal"] && ($planDetails["planStatus"] == "PendingMerchantShipmentNotice" || $planDetails["planStatus"] == "InProgress")) {

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
                    $orderObj->getStatus(), 'Payment InstallmentPlan was created with number ID: '
                    . Mage::getSingleton('core/session')->getInstallmentPlanNumber(), false
            );
            Mage::log('========== splitit update ref order number ==============');
            $updateStatus = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->updateRefOrderNumber($api, $orderObj);
            if ($updateStatus["status"] == false) {
                Mage::throwException(
                        Mage::helper('payment')->__($updateStatus["data"])
                );
            }

            if ($paymentAction == "authorize_capture") {

                // comment start installment for autocapture true.
                /* $sessionId = Mage::getSingleton('core/session')->getSplititSessionid();
                  $params = array('InstallmentPlanNumber' => Mage::getSingleton('core/session')->getInstallmentPlanNumber());
                  $params = array_merge($params, array("RequestHeader"=> array('SessionId' => $sessionId)));
                  $result = $api->startInstallment(Mage::getSingleton("pis_payment/pisPaymentFormMethod")->getApiUrl(), $params); */
                $payment->setShouldCloseParentTransaction(true);
                $payment->setIsTransactionClosed(1);
                $payment->registerCaptureNotification($grandTotal);
                $orderObj->addStatusToHistory(
                        false, 'Payment NotifyOrderShipped was sent with number ID: ' . Mage::getSingleton('core/session')->getInstallmentPlanNumber(), false
                );
            }
            //$orderObj->queueNewOrderEmail();
            $orderObj->sendNewOrderEmail();
            $orderObj->save();

            // update order_created in splitit_hosted_solution
            $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
            // get order id and increment number from session to update in splitit_hosted_solution table

            $updateQue = 'UPDATE `' . $tablePrefix . 'splitit_hosted_solution` SET order_created = 1, order_id = "' . $orderId . '", order_increment_id = "' . $orderIncrementId . '" WHERE installment_plan_number = "' . $params["InstallmentPlanNumber"] . '"';
            $db_write->query($updateQue);
            Mage::log('====== Order Id =====:' . $orderId . '==== Order Increment Id ======:' . $orderIncrementId);


            //Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/success")->sendResponse();
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/onepage/success")->sendResponse();
        } else {

            Mage::log('====== Order cancel due to Grand total and Payment detail total coming from Api is not same. =====');
            $cancelResponse = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
            if ($cancelResponse["status"]) {
                Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "payitsimple/payment/cancel")->sendResponse();
            }
        }
    }

    public function successAction() {
        $orderIncrementId = Mage::getSingleton('core/session')->getOrderIncrementId();
        $orderId = Mage::getSingleton('core/session')->getOrderId();
        // check if order created else redirect to cart page
        if ($orderId != "" && $orderIncrementId != "") {
            $this->loadLayout();
            $this->renderLayout();
        } else {
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/cart")->sendResponse();
        }
    }

    public function cancelAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function successAsyncAction() {
        $params = $this->getRequest()->getParams();
        // remove plan from session which were created when user click on radio button
        //Mage::getSingleton('core/session')->setSplititInstallmentPlanNumber("");
        Mage::getSingleton('core/session')->setInstallmentPlanNumber($params["InstallmentPlanNumber"]);
        Mage::log('======= successAsyncAction :  =======InstallmentPlanNumber coming from splitit in url: ' . $params["InstallmentPlanNumber"]);

        $tablePrefix = (string) Mage::getConfig()->getTablePrefix();
        $db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        // check if order already created with the installment plan number coming from in parameters via async call(async run before response). Get data from SPLITIT_HOSTED_SOLUTION table
        $sql1 = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "' . $params["InstallmentPlanNumber"] . '" and order_created = 1';
        $data = $db_read->fetchRow($sql1);
        // check if order already created via Async etc.
        if (count($data) && $data["order_id"] != 0 && $data["order_increment_id"] != null) {

            return true;
        }


        // get installmentplan details        
        $storeId = Mage::app()->getStore()->getStoreId();
        $api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);
        $planDetails = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->getInstallmentPlanDetails($api);

        Mage::log('======= get installmentplan details :  ======= ');
        Mage::log($planDetails);
        // get plan number info from database table SPLITIT_HOSTED_SOLUTION
        $sql = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "' . $params["InstallmentPlanNumber"] . '"';
        //$data = $db_read->fetchAllRow($sql); // fetch All row in a table
        $data = $db_read->fetchRow($sql);

        $orderId = $data["order_id"];
        $orderIncrementId = $data["order_increment_id"];
        $orderObj = Mage::getModel('sales/order')->load($orderId);
        $grandTotal = number_format((float) $orderObj->getGrandTotal(), 2, '.', '');
        $planDetails["grandTotal"] = number_format((float) $planDetails["grandTotal"], 2, '.', '');
        Mage::log('======= grandTotal(orderObj):' . $grandTotal . ', grandTotal(planDetails):' . $planDetails["grandTotal"] . '   ======= ');

        if (count($data) && $grandTotal == $planDetails["grandTotal"] && ($planDetails["planStatus"] == "PendingMerchantShipmentNotice" || $planDetails["planStatus"] == "InProgress")) {

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
                    $orderObj->getStatus(), 'Payment InstallmentPlan was created with number ID: '
                    . Mage::getSingleton('core/session')->getInstallmentPlanNumber(), false
            );
            Mage::log('========== splitit update ref order number ==============');
            $updateStatus = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->updateRefOrderNumber($api, $orderObj);
            if ($updateStatus["status"] == false) {
                Mage::throwException(
                        Mage::helper('payment')->__($updateStatus["data"])
                );
            }

            if ($paymentAction == "authorize_capture") {

                // comment start installment for autocapture true.
                /* $sessionId = Mage::getSingleton('core/session')->getSplititSessionid();
                  $params = array('InstallmentPlanNumber' => Mage::getSingleton('core/session')->getInstallmentPlanNumber());
                  $params = array_merge($params, array("RequestHeader"=> array('SessionId' => $sessionId)));
                  $result = $api->startInstallment(Mage::getSingleton("pis_payment/pisMethod")->getApiUrl(), $params); */
                $payment->setShouldCloseParentTransaction(true);
                $payment->setIsTransactionClosed(1);
                $payment->registerCaptureNotification($grandTotal);
                $orderObj->addStatusToHistory(
                        false, 'Payment NotifyOrderShipped was sent with number ID: ' . Mage::getSingleton('core/session')->getInstallmentPlanNumber(), false
                );
            }
            $orderObj->queueNewOrderEmail();
            $orderObj->save();

            // update order_created in splitit_hosted_solution
            $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
            // get order id and increment number from session to update in splitit_hosted_solution table

            $updateQue = 'UPDATE `' . $tablePrefix . 'splitit_hosted_solution` SET order_created = 1, order_id = "' . $orderId . '", order_increment_id = "' . $orderIncrementId . '" WHERE installment_plan_number = "' . $params["InstallmentPlanNumber"] . '"';
            $db_write->query($updateQue);
            Mage::log('====== Order Id =====:' . $orderId . '==== Order Increment Id ======:' . $orderIncrementId);

            return true;
        } else {

            Mage::log('====== Order Grand total and Payment detail total coming from Api is not same. =====');
            Mage::log('Grand Total : ' . $grandTotal);
            Mage::log('Plan Details Total : ' . $planDetails["grandTotal"]);

            /* Mage::log('====== Order cancel due to Grand total and Payment detail total coming from Api is not same. =====');
              $cancelResponse = Mage::getSingleton("pis_payment/pisMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
              if($cancelResponse["status"]){

              if ($orderObj->getId()) {
              $orderObj->cancel()->save();
              }

              //Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/cancel")->sendResponse();
              } */
        }
    }

    public function cancelExitAction() {

        /* $params = $this->getRequest()->getParams();
          $storeId = Mage::app()->getStore()->getStoreId();
          $api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);
          $cancelResponse = Mage::getSingleton("pis_payment/pisMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
          if($cancelResponse["status"]){
          Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/cancel")->sendResponse();
          } */

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
        Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/cart")->sendResponse();
    }

    public function errorExitAction() {

        /* $params = $this->getRequest()->getParams();
          $storeId = Mage::app()->getStore()->getStoreId();
          $api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);
          $cancelResponse = Mage::getSingleton("pis_payment/pisMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
          if($cancelResponse["status"]){
          Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/cancel")->sendResponse();
          } */

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
        Mage::getSingleton('core/session')->addError($this->__('The payment has been denied.'));
        Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/cart")->sendResponse();
    }

    public function redirectAction() {

        Mage::helper('pis_payment')->getCreditCardFormTranslation('ecomm_redirect_to_payment_form');


        $splititCheckoutUrl = Mage::getSingleton('checkout/session')->getSplititCheckoutUrl();
        $splititInstallmentPlanNumber = Mage::getSingleton('checkout/session')->getSplititInstallmentPlanNumber();
        if ($splititCheckoutUrl != "") {
            $tablePrefix = (string) Mage::getConfig()->getTablePrefix();
            $db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql1 = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "' . $splititInstallmentPlanNumber . '"';
            $data = $db_read->fetchRow($sql1);
            // check if order already created via Async etc.
            if (count($data) && $data["order_id"] == 0 && $data["order_increment_id"] == null) {
                $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
                $orderIncrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
                $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
                // get order id and increment number from session to update in splitit_hosted_solution table

                $updateQue = 'UPDATE `' . $tablePrefix . 'splitit_hosted_solution` SET order_id = "' . $orderId . '", order_increment_id = "' . $orderIncrementId . '" WHERE installment_plan_number = "' . $splititInstallmentPlanNumber . '"';
                $db_write->query($updateQue);
                
                Mage::app()->getFrontController()->getResponse()->setRedirect($splititCheckoutUrl)->sendResponse();
                //return true;
            }
        }
    }

}
