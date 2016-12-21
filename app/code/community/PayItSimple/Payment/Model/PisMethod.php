<?php
class PayItSimple_Payment_Model_PisMethod extends Mage_Payment_Model_Method_Cc
{
    protected $_code = 'pis_cc';
    protected $_canSaveCc   = true;
    protected $_formBlockType = 'pis_payment/form_pis';
    protected $_infoBlockType = 'pis_payment/info_pis';
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = false;
    protected $_canCaptureOnce              = false;
    protected $_canRefund                   = false;
    protected $_canRefundInvoicePartial     = false;
    protected $_canVoid                     = false;
    protected $_canUseInternal              = false;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = false;

    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $info->setInstallmentsNo($data->getInstallmentsNo());
        $info->setAdditionalInformation('terms',$data->getTerms());
        return parent::assignData($data);
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     */
    public function validate()
    {
        $info = $this->getInfoInstance();
        $no = $info->getInstallmentsNo();
        $terms= $info->getAdditionalInformation('terms');
        $errorMsg = '';
        if (empty($no)) {
            $errorMsg = $this->_getHelper()->__('Installments are required fields');
        }
        /*if (empty($terms)) {
            $errorMsg = $this->_getHelper()->__('You should accept terms and conditions');
        }*/
        if ($errorMsg) {
            Mage::throwException($errorMsg);
        }
        return parent::validate();
    }

    /**
     * Authorize payment abstract method
     *
     * @param Varien_Object $payment fgfgf
     * @param float         $amount  fgfgfgfg
     *
     * @return $this
     */
    public function authorize(Varien_Object $payment, $amount)
    { 
        if (!$this->canAuthorize()) {
            Mage::throwException(
                Mage::helper('payment')->__('Authorize action is not available.')
            );
        }
        //$api = $this->_initApi($this->getStore());
        $api = $this->getApi();
        $result = $this->createInstallmentPlan($api, $payment, $amount);
        $result = Mage::helper('core')->jsonDecode($result);
        // show error if there is any error from spliti it when click on place order
        if(!$result["ResponseHeader"]["Succeeded"]){
            $errorMsg = "";
            foreach ($result["ResponseHeader"]["Errors"] as $key => $value) {
                $errorMsg .= $value["ErrorCode"]." : ".$value["Message"];
            }
            Mage::throwException(
                Mage::helper('payment')->__($errorMsg)
            );     
        }
        
        $payment->setTransactionId($result['InstallmentPlan']['InstallmentPlanNumber']);
        $payment->setIsTransactionClosed(0);
        $payment->setIsTransactionApproved(true);
        foreach (
            array(
                'ConsumerFullName',
                'Email',
                'Amount',
                'InstallmentNumber'
            ) as $param) {

            unset($result[$param]);

        }
        $st = $api->getInstallmentPlanStatusList();
        $result['InstallmentPlanStatus'] = $st[$result['InstallmentPlan']['InstallmentPlanStatus']['Id']];
       
        $payment->setTransactionAdditionalInfo(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
            $result
        );
        $order = $payment->getOrder();
        $order->addStatusToHistory(
            $order->getStatus(),
            'Payment InstallmentPlan was created with number ID: '
            . $result['InstallmentPlan']['InstallmentPlanNumber'],
            false
        );
        // call InstallmentPlan-UpdatePlan-Params for update "RefOrderNumber" after order creation
        $updateStatus = $this->updateRefOrderNumber($api, $order);        
        if($updateStatus["status"] == false){
            Mage::throwException(
                Mage::helper('payment')->__($updateStatus["data"])
            );    
        }
        //$order->save();

        return $this;
    }

    public function splititCapture($payment, $sessionId, $transactionId){
        $api = $this->getApi();
        //$authNumber = $payment->getAuthorizationTransaction()->getTxnId();
        $params = array(
                    "RequestHeader" => ["SessionId" => $sessionId],
                    "InstallmentPlanNumber" => $transactionId
            );
        $result = $api->startInstallment($this->getApiUrl(), $params);
         if (!$result){
            $e = $api->getError();
            Mage::throwException($e['code'].' '.$e['message']);
        }
        $payment->setIsTransactionClosed(1);
        $order = $payment->getOrder();

        $order->addStatusToHistory(
            false,
            'Payment NotifyOrderShipped was sent with number ID: '.$authNumber, false
        );
        $order->save();
        return $result;
    }

    /**
     * Capture payment abstract method
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount=null)
    { 
        if (!$this->canCapture()) {
            Mage::throwException(
                Mage::helper('payment')->__('Capture action is not available.')
            );
        }
        if (!$payment->getAuthorizationTransaction()) {
            $this->authorize($payment, $amount);
            $authNumber = $payment->getTransactionId();
        } else {
            $authNumber = $payment->getAuthorizationTransaction()->getTxnId();
        }
        
        $paymentAction = Mage::getStoreConfig('payment/pis_cc/payment_action');  
        $params = array('InstallmentPlanNumber' => $authNumber);
        if($paymentAction == "authorize_capture"){
            $api = $this->getApi();
            $sessionId = Mage::getSingleton('core/session')->getSplititSessionid();
        }else{
            $api = $this->_initApi($this->getStore());
            $sessionId = Mage::getSingleton('core/session')->getSplititSessionid();
        }
        $params = array_merge($params, array("RequestHeader"=> array('SessionId' => $sessionId)));
        $result = $api->startInstallment($this->getApiUrl(), $params);
        
        $this->debugData('REQUEST: ' . $api->getRequest());
        $this->debugData('RESPONSE: ' . $api->getResponse());
        if (!$result) {
            $e = $api->getError();
            Mage::throwException($e['code'].' '.$e['message']);
        }

        $payment->setIsTransactionClosed(1);
        $order = $payment->getOrder();

        $order->addStatusToHistory(
            false,
            'Payment NotifyOrderShipped was sent with number ID: '.$authNumber, false
        );
        $order->save();

        return $this;
    }



    /**
     * @param $api     PayItSimple_Payment_Model_Api
     * @param $payment Mage_Sales_Model_Order_Payment
     *
     * @return array|bool
     * @throws Mage_Payment_Exception
     */
    protected function createInstallmentPlan1($api, $payment, $amount)
    {
        $order = $payment->getOrder();
        $billingaddress = $order->getBillingAddress();
        $address = $billingaddress->getData('street') . ' '
            . $billingaddress->getData('city') . ' '
            . $billingaddress->getData('region');
        $ccTypes = $api->getCcTypesAvailable();
        $params = array(
            'ConsumerFullName' => $order->getCustomerName(),
            'Email' => $order->getCustomerEmail(),
            'AvsAddress' => $address,
            'AvsZip' => $billingaddress->getData('postcode'),
            'CountryId' => $this->getCountryCodePIS($billingaddress->getCountryId()),
            'AmountBeforeFees' => $amount,
            'CardHolder' => $billingaddress->getData('firstname')
                . ' ' .  $billingaddress->getData('lastname'),
            'CardTypeId' => $ccTypes[$payment->getCcType()],
            'CardNumber' => $payment->getCcNumber(),
            'CardExpMonth' => $payment->getCcExpMonth(),
            'CardExpYear' => $payment->getCcExpYear(),
            'CardCvv' => $payment->getCcCid(),
            'InstallmentNumber' => $payment->getInstallmentsNo(),
            'ParamX' => $order->getIncrementId(),
            'CurrencyName' => Mage::app()->getStore()->getCurrentCurrencyCode(),
        );
        $result = $api->createInstallmentPlan($params);
        $this->debugData('REQUEST: ' . $api->getRequest());
        $this->debugData('RESPONSE: ' . $api->getResponse());
        if (!$result){
            $e = $api->getError();
            Mage::throwException($e['code'].' '.$e['message']);
        }
        return $result;
    }

    protected function createInstallmentPlan($api, $payment, $amount)
    {
        $params = [
            "RequestHeader" => [
                "SessionId" => Mage::getSingleton('core/session')->getSplititSessionid(),
                "ApiKey"    => $this->getConfigData('api_terminal_key', $storeId),
            ],
            "InstallmentPlanNumber" => Mage::getSingleton('core/session')->getInstallmentPlanNumber(),
            "CreditCardDetails" => [
                "CardCvv" => $payment->getCcCid(),
                "CardNumber" => $payment->getCcNumber(),
                "CardExpYear" => $payment->getCcExpYear(),
                "CardExpMonth" => $payment->getCcExpMonth(),
            ],
            "PlanApprovalEvidence" => [
                "AreTermsAndConditionsApproved" => "True"
            ],
        ];
        $result = $api->createInstallmentPlan($this->getApiUrl(),$params);  
        if (!$result){
            $e = $api->getError();
            Mage::throwException($e['code'].' '.$e['message']);
        }
        return $result;
    }

    /**
     * @param $storeId int
     *
     * @return PayItSimple_Payment_Model_Api
     * @throws Mage_Payment_Exception
     */
    public function _initApi($storeId = null){
        if (is_null($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }
        $api = $this->getApi();
        if ($api->isLogin()) {
            return $api;
        }
        $result = $api->login(
            $this->getApiUrl(),
            array(
                //'ApiKey' => $this->getConfigData('api_terminal_key', $storeId),
                'UserName' => $this->getConfigData('api_username'),
                'Password' => $this->getConfigData('api_password'),
                'TouchPoint'=>array("Code" => "MagentoPlugin","Version" => "2.0")
            )
        );
        $this->debugData('REQUEST: ' . $api->getRequest());
        $this->debugData('RESPONSE: ' . $api->getResponse());
        if (!$result || !$api->isLogin()){
            $e = $api->getError();
            Mage::throwException($e['code'].' '.$e['message']);
        }
        return $api;
    }

    public function getApi(){
        return Mage::getSingleton('pis_payment/api');
    }

    public function getApiUrl() {
        if ($this->getConfigData('sandbox_flag')) {
            return $this->getConfigData('api_url_sandbox');
        }
        return $this->getConfigData('api_url');
    }

    public function getCountryCodePIS($countryCode)
    {
        $countryIds = array(
            'AF' => 4,'AX' => 248,'AL' => 8,'DZ' => 12,'AS' => 16,'AD' => 20,'AO' => 24,'AI' => 660,'AQ' => 10,'AG' => 28,'AR' => 32,
            'AM' => 51,'AW' => 533,'AU' => 36,'AT' => 40,'AZ' => 31,'BS' => 44,'BH' => 48,'BD' => 50,'BB' => 52,'BY' => 112,'BE' => 56,
            'BZ' => 84,'BJ' => 204,'BM' => 60,'BT' => 64,'BO' => 68,'BA' => 70,'BW' => 72,'BV' => 74,'BR' => 76,'IO' => 86,'VG' => 92,
            'BN' => 96,'BG' => 100,'BF' => 854,'BI' => 108,'KH' => 116,'CM' => 120,'CA' => 124,'CV' => 132,'KY' => 136,'CF' => 140,
            'TD' => 148,'CL' => 152,'CN' => 156,'CX' => 162,'CC' => 166,'CO' => 170,'KM' => 174,'CG' => 180,'CD' => 178,'CK' => 184,
            'CR' => 188,'CI' => 384,'HR' => 191,'CU' => 192,'CY' => 196,'CZ' => 203,'DK' => 208,'DJ' => 262,'DM' => 212,'DO' => 214,
            'EC' => 218,'EG' => 818,'SV' => 222,'GQ' => 226,'ER' => 232,'EE' => 233,'ET' => 231,'FK' => 238,'FO' => 234,'FJ' => 242,
            'FI' => 246,'FR' => 250,'GF' => 254,'PF' => 258,'TF' => 260,'GA' => 266,'GM' => 270,'GE' => 268,'DE' => 276,'GH' => 288,
            'GI' => 292,'GR' => 300,'GL' => 304,'GD' => 308,'GP' => 312,'GU' => 316,'GT' => 320,'GG' => 831,'GN' => 324,'GW' => 624,
            'GY' => 328,'HT' => 332,'HM' => 334,'HN' => 340,'HK' => 344,'HU' => 348,'IS' => 352,'IN' => 356,'ID' => 360,'IR' => 364,
            'IQ' => 368,'IE' => 372,'IM' => 833,'IL' => 376,'IT' => 380,'JM' => 388,'JP' => 392,'JE' => 832,'JO' => 400,'KZ' => 398,
            'KE' => 404,'KI' => 296,'KW' => 414,'KG' => 417,'LA' => 418,'LV' => 428,'LB' => 422,'LS' => 426,'LR' => 430,'LY' => 434,
            'LI' => 438,'LT' => 440,'LU' => 442,'MO' => 446,'MK' => 807,'MG' => 450,'MW' => 454,'MY' => 458,'MV' => 462,'ML' => 466,
            'MT' => 470,'MH' => 584,'MQ' => 474,'MR' => 478,'MU' => 480,'YT' => 175,'MX' => 484,'FM' => 583,'MD' => 498,'MC' => 492,
            'MN' => 496,'ME' => 499,'MS' => 500,'MA' => 504,'MZ' => 508,'MM' => 104,'NA' => 516,'NR' => 520,'NP' => 524,'NL' => 528,
            'AN' => 530,'NC' => 540,'NZ' => 554,'NI' => 558,'NE' => 562,'NG' => 566,'NU' => 570,'NF' => 574,'MP' => 580,'KP' => 408,
            'NO' => 578,'OM' => 512,'PK' => 586,'PW' => 585,'PS' => 275,'PA' => 591,'PG' => 598,'PY' => 600,'PE' => 604,'PH' => 608,
            'PN' => 612,'PL' => 616,'PT' => 620,'PR' => 630,'QA' => 634,'RE' => 638,'RO' => 642,'RU' => 643,'RW' => 646,'BL' => '',
            'SH' => 654,'KN' => 659,'LC' => 662,'MF' => '','PM' => 666,'WS' => 882,'SM' => 674,'ST' => 678,'SA' => 682,'SN' => 686,
            'RS' => 688,'SC' => 690,'SL' => 694,'SG' => 702,'SK' => 703,'SI' => 705,'SB' => 90,'SO' => 706,'ZA' => 710,'GS' => 239,
            'KR' => 410,'ES' => 724,'LK' => 144,'VC' => 670,'SD' => 736,'SR' => 740,'SJ' => 744,'SZ' => 748,'SE' => 754,'CH' => 756,
            'SY' => 760,'TW' => 158,'TJ' => 762,'TZ' => 834,'TH' => 764,'TL' => 626,'TG' => 768,'TK' => 772,'TO' => 776,'TT' => 780,
            'TN' => 788,'TR' => 792,'TM' => 795,'TC' => 796,'TV' => 798,'UG' => 800,'UA' => 804,'AE' => 784,'GB' => 826,'US' => 840,
            'UY' => 858,'UM' => 581,'VI' => 850,'UZ' => 860,'VU' => 548,'VA' => 336,'VE' => 862,'VN' => 704,'WF' => 876,'EH' => 732,
            'YE' => 887,'ZM' => 894,'ZW' => 716,
        );
        return ($countryIds[$countryCode]) ? $countryIds[$countryCode] : 0;
    }

    public function getValidNumberOfInstallments($api){
        return $result = $api->getValidNumberOfInstallments();
        
    }

    public function updateRefOrderNumber($api, $order){
        $params = [
            "RequestHeader" => [
                "SessionId" => Mage::getSingleton('core/session')->getSplititSessionid(),
            ],
            "InstallmentPlanNumber" => Mage::getSingleton('core/session')->getInstallmentPlanNumber(),
            "PlanData" => [
                "ExtendedParams" => [
                    "CreateAck" => "Received",
                ],
                "RefOrderNumber" => $order->getIncrementId(),
            ],
        ];
        $response = ["status"=>false, "data" => ""];
        $result = $api->updateRefOrderNumber($this->getApiUrl(), $params);
        $decodedResult = Mage::helper('core')->jsonDecode($result);
        if(isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1){
            $response["status"] = true;
        }else if(isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])){
            $errorMsg = "";
            $i = 1;
            foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
                $errorMsg .= "Code : ".$value["ErrorCode"]." - ".$value["Message"];
                if($i < count($decodedResult["ResponseHeader"]["Errors"])){
                    $errorMsg .= ", ";
                }
                $i++;
            }

            $response["data"] = $errorMsg;
        }
        return $response;
        
    }

    public function installmentplaninit($api, $selectedInstallment){
        $storeId = Mage::app()->getStore()->getId();
        $session = Mage::getSingleton('checkout/session');
        $quote_id = $session->getQuoteId();
        $firstInstallmentAmount = $this->getFirstInstallmentAmount($selectedInstallment);
        $checkout = Mage::getSingleton('checkout/session')->getQuote();
        $billAddress = $checkout->getBillingAddress();
        $BillingAddressArr = $billAddress->getData();
        $customerInfo = Mage::getSingleton('customer/session')->getCustomer()->getData();
        if(!isset($customerInfo["firstname"])){
            $customerInfo["firstname"] = $billAddress->getFirstname();
            $customerInfo["lastname"] = $billAddress->getLastname();
            $customerInfo["email"] = $billAddress->getEmail();
        }
        $params = [
            "RequestHeader" => [
                "SessionId" => Mage::getSingleton('core/session')->getSplititSessionid(),
                "ApiKey"    => $this->getConfigData('api_terminal_key', $storeId),
            ],
            "PlanData"      => [
                "Amount"    => [
                    "Value" => round(Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal(), 2),
                    "CurrencyCode" => Mage::app()->getStore()->getCurrentCurrencyCode(),
                ],
                "NumberOfInstallments" => $selectedInstallment,
                "PurchaseMethod" => "ECommerce",
                //"RefOrderNumber" => $quote_id,
                "FirstInstallmentAmount" => [
                    "Value" => $firstInstallmentAmount,
                    "CurrencyCode" => Mage::app()->getStore()->getCurrentCurrencyCode(),
                ],
                "AutoCapture" => "false",
                "ExtendedParams" => [
                    "CreateAck" => "NotReceived"
                ],
            ],
            "BillingAddress" => [
                "AddressLine" => $billAddress->getStreet()[0], 
                "AddressLine2" => $billAddress->getStreet()[1],
                "City" => $billAddress->getCity(),
                "State" => $billAddress->getRegion(),
                "Country" => Mage::app()->getLocale()->getCountryTranslation($billAddress->getCountry()),
                "Zip" => $billAddress->getPostcode(),
            ],
            "ConsumerData" => [
                "FullName" => $customerInfo["firstname"]." ".$customerInfo["lastname"],
                "Email" => $customerInfo["email"],
                "PhoneNumber" => $billAddress->getTelephone()
            ],
        ];
        //$api = Mage::getSingleton("pis_payment/pisMethod");
        try{
                $response = ["status"=>false, "data" => ""];
                $result = Mage::getSingleton("pis_payment/api")->installmentplaninit($this->getApiUrl(), $params);
                // check for approval URL from response
                $decodedResult = Mage::helper('core')->jsonDecode($result);

                if(isset($decodedResult) && isset($decodedResult["ApprovalUrl"]) && $decodedResult["ApprovalUrl"] != ""){
                    $intallmentPlan = $decodedResult["InstallmentPlan"]["InstallmentPlanNumber"];
                    // set Installment plan number into session
                    Mage::getSingleton('core/session')->setInstallmentPlanNumber($intallmentPlan);
                    $approvalUrlResponse = Mage::getSingleton("pis_payment/api")->getApprovalUrlResponse($decodedResult["ApprovalUrl"]);
                    $approvalUrlRes = Mage::helper('core')->jsonDecode($approvalUrlResponse);
                    if(isset($approvalUrlRes["Global"]["ResponseResult"]["Errors"]) && count($approvalUrlRes["Global"]["ResponseResult"]["Errors"])){
                        $i = 1;
                        $errorMsg = "";
                        foreach ($approvalUrlRes["Global"]["ResponseResult"]["Errors"] as $key => $value) {
                            $errorMsg .= "Code : ".$value["ErrorCode"]." - ".$value["Message"];
                            if($i < count($approvalUrlRes["Global"]["ResponseResult"]["Errors"])){
                                $errorMsg .= ", ";
                            }
                            $i++;
                        }
                        $response["data"] = $errorMsg;
                    }else{
                        $popupHtml = $this->createPopupHtml($approvalUrlResponse);
                        $response["status"] = true;
                        $response["data"] = $popupHtml;
                    }
                    
                    
                    //print_r($approvalUrlResponse);die("---approvalUrlResponse");
                }else if(isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])){
                    $errorMsg = "";
                    $i = 1;
                    foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
                        $errorMsg .= "Code : ".$value["ErrorCode"]." - ".$value["Message"];
                        if($i < count($decodedResult["ResponseHeader"]["Errors"])){
                            $errorMsg .= ", ";
                        }
                        $i++;
                    }

                    $response["data"] = $errorMsg;
                }
                
        }catch(Exception $e){
            $response["data"] = $e->getMessage();
        }
        return $response;
        //return $result;
    }

    public function getFirstInstallmentAmount($selectedInstallment){
        $firstPayment = Mage::getStoreConfig('payment/pis_cc/first_payment');
        $percentageOfOrder = Mage::getStoreConfig('payment/pis_cc/percentage_of_order');
        $installmentsInDropdownArr = Mage::getSingleton('core/session')->getInstallmentsInDropdown();

        $firstInstallmentAmount = 0;
        if($firstPayment == "equal"){
            $firstInstallmentAmount = $installmentsInDropdownArr[$selectedInstallment];
        }else if($firstPayment == "shipping_taxes"){
            $shippingAmount = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingAmount();
            $taxAmount = Mage::helper('checkout')->getQuote()->getShippingAddress()->getData('tax_amount');
            $firstInstallmentAmount = $installmentsInDropdownArr[$selectedInstallment]+$shippingAmount+$taxAmount;
        }else if($firstPayment == "shipping"){
            $shippingAmount = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingAmount();
            $firstInstallmentAmount = $installmentsInDropdownArr[$selectedInstallment]+$shippingAmount;
        }else if($firstPayment == "tax"){
            $taxAmount = Mage::helper('checkout')->getQuote()->getShippingAddress()->getData('tax_amount');
            $firstInstallmentAmount = $installmentsInDropdownArr[$selectedInstallment]+$taxAmount;
        }else if($firstPayment == "percentage"){
            if($percentageOfOrder > 50){
                $percentageOfOrder = 50;
            }
            $firstInstallmentAmount = ((Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal()*$percentageOfOrder)/100);
        }

        return round($firstInstallmentAmount,2);
    }

    public function createPopupHtml($approvalUrlResponse){
        $approvalUrlResponseArr = Mage::helper('core')->jsonDecode($approvalUrlResponse);
        $html = '';
        if(!empty($approvalUrlResponseArr) && isset($approvalUrlResponseArr["Global"]["ResponseResult"]) && isset($approvalUrlResponseArr["Global"]["ResponseResult"]["Succeeded"]) && $approvalUrlResponseArr["Global"]["ResponseResult"]["Succeeded"] == 1){

            $currencySymbol = $approvalUrlResponseArr["Global"]["Currency"]["Symbol"];
            $totalAmount = $approvalUrlResponseArr["HeaderSection"]["InstallmentPlanTotalAmount"]["Amount"];
            $totalText = $approvalUrlResponseArr["HeaderSection"]["InstallmentPlanTotalAmount"]["Text"];

            $scheduleChargedDateText = $approvalUrlResponseArr["ScheduledPaymentSection"]["ChargedDateText"];
            $scheduleChargedAmountText = $approvalUrlResponseArr["ScheduledPaymentSection"]["ChargedAmountText"];
            $scheduleRequiredAvailableCreditText = $approvalUrlResponseArr["ScheduledPaymentSection"]["RequiredAvailableCreditText"];

            $termsConditionsText = $approvalUrlResponseArr["ImportantNotesSection"]["AcknowledgeLink"]["Text"];
            $termsConditionsLink = $approvalUrlResponseArr["ImportantNotesSection"]["AcknowledgeLink"]["Link"];
            $servicesText = $approvalUrlResponseArr["LinksSection"]["PrivacyPolicy"]["Text"];
            $servicesLink = $approvalUrlResponseArr["LinksSection"]["PrivacyPolicy"]["Link"];

          $html .= '<div class="approval-popup_ovelay" style=""></div>';

          $html .= '<div id="approval-popup" style="">';
          
          $html .= '<div id="main">';
          $html .= '<div class="_popup_overlay"></div>';
           $html .= '<!-- Start small inner popup -->';

          // Start Term and Condition Popup
          $html .= '<div id="termAndConditionpopup" style=" ">
                    <div class="popup-block">
                    <div class="popup-content" style="">'.$this->getTermnConditionText().'

                    </div>';
          $html .= '<div class="popup-footer" style="">';
          $html .= '<div id="payment-schedule-close-btn" class="popup-btn"  style="">';
          $html .= '<div class="popup-btn-area" style=""><span id="termAndConditionpopupCloseBtn" class="popup-btn-icon" style="">Close</span></div>';
          $html .= '</div>';
          $html .= '</div>';
          $html .= '</div>';
          $html .= '</div>';
          // Close Term and Condition Popup
          $html .= '<div id="payment-schedule" style=" ">';
          $html .= '<div class="popup-block">';
          $html .= '<div class="popup-content" style="">';
          $html .= '<table class="popupContentTable" style="">';
          $html .= '<thead>';
          $html .= '<tr>';
          $html .= '<th style="width: 1em;"></th>';
          $html .= '<th style="text-align:center;">'.$scheduleChargedDateText.'</th>';
          $html .= '<th style="text-align:center;">'.$scheduleChargedAmountText.'</th>';
          $html .= '<th style="text-align:center;">'.$scheduleRequiredAvailableCreditText.'</th>';
          $html .= '</tr>';
          $html .= '</thead>';
          $html .= '<tbody>';
          $schedulePayment = ""; //echo $value["DateOfCharge"];//substr($value["DateOfCharge"], 0, strpos($value["DateOfCharge"], "To"));
            if(isset($approvalUrlResponseArr["ScheduledPaymentSection"]["ScheduleItems"])){
                
                foreach ($approvalUrlResponseArr["ScheduledPaymentSection"]["ScheduleItems"] as $key => $value) {
                    $dateOfChargeTemp = (string)$value["DateOfCharge"];
                    $dataOfCharge = substr($dateOfChargeTemp, 0, strpos($dateOfChargeTemp, "T"));
                    $date=date_create($dataOfCharge);
                    
                    $schedulePayment .= '<tr>';
                    $schedulePayment .= '<td style="text-align: left;">'.$value["InstallmentNumber"].'.</td>';
                    $schedulePayment .= '<td>'.date_format($date,"m/d/Y").'</td>';
                    $schedulePayment .= '<td>'.$currencySymbol.$value["ChargeAmount"].'</td>';
                    $schedulePayment .= '<td>'.$currencySymbol.$value["RequiredAvailableCredit"].'</td>';
                    $schedulePayment .= '</tr>';
                }
            }
          $html .= $schedulePayment;    
          $html .= '</tbody>';
          $html .= '</table>';
          $html .= '</div>';
          $html .= '<div class="popup-footer" style="">';
          $html .= '<div id="payment-schedule-close-btn" class="popup-btn"  style="">';
          $html .= '<div class="popup-btn-area" style=""><span id="complete-payment-schedule-close" class="popup-btn-icon" style="">Close</span></div>';
          $html .= '</div>';
          $html .= '</div>';
          $html .= '</div>';
          $html .= '</div>';
          $html .= '<!-- End small inner popup -->';


          $html .= '<div class="mainHeader">';
          $html .= '<span class="closeapprovalpopup_btn" style="" onclick="closeApprovalPopup();"><img style="width:100%;" src="'. Mage::getBaseUrl( Mage_Core_Model_Store::URL_TYPE_WEB, true ).'js/payitsimple/payitsimplepament/approval-popup-close.png"></span>'; 
          $html .= '<table id="wiz-header" width="100%;">';
          $html .= '<tbody>';
          $html .= '<tr>';
          $html .= '<td class="wiz-header-side wiz-header-left" style=""></td>';
          $html .= '<td class="wiz-header-center" style="">';
          $html .= '<div>TOTAL PURCHASE:</div>';
          $html .= '<div class="currencySymbolIcon" style="">'.$currencySymbol.$totalAmount.'</div></td><td class="wiz-header-side wiz-header-right" style="">';
          $html .= '</td>';
          $html .= '</tr>';
          $html .= '</tbody>';
          $html .= '</table>';
          $html .= '</div>';
          $html .= '<div style="margin-top: auto;">';
         
          $html .= '<div class="form-block" style="">';
          $html .= '<div class="form-block-area" style="">';
          $html .= '<div class="spacer15" style=""></div>';
          $html .= '<div class="tableResponsive"><table class="tablePage2" style="" cellspacing="0" cellpadding="0">';
          $html .= '<tbody>';

          $planDataSection = '';
          $planDataSectionHtml = '';
          $planDataSection = $approvalUrlResponseArr["PlanDataSection"];
          if(isset($approvalUrlResponseArr["PlanDataSection"])){
            $planDataSectionHtml .= '<tr class="tablePage2TD"  style="">';
            $planDataSectionHtml .= '<td>'.$planDataSection["NumberOfInstallments"]["Text"].'</td>';
            $planDataSectionHtml .= '<td class="text-right" style="">';
            $planDataSectionHtml .= '<span>'.$planDataSection["NumberOfInstallments"]["NumOfInstallments"].'</span>';
            $planDataSectionHtml .= '</td></tr>';

            $planDataSectionHtml .= '<tr class="tablePage2TD" style="">';
            $planDataSectionHtml .= '<td>'.$planDataSection["FirstInstallmentAmount"]["Text"].'</td>';
            $planDataSectionHtml .= '<td class="text-right" style="">';
            $planDataSectionHtml .= '<span>'.$currencySymbol.$planDataSection["FirstInstallmentAmount"]["Amount"].'</span>';
            $planDataSectionHtml .= '</td></tr>';

            $planDataSectionHtml .= '<tr class="tablePage2TD" style="">';
            $planDataSectionHtml .= '<td>'.$planDataSection["SubsequentInstallmentAmount"]["Text"].'</td>';
            $planDataSectionHtml .= '<td class="text-right">';
            $planDataSectionHtml .= '<span>'.$currencySymbol.$planDataSection["SubsequentInstallmentAmount"]["Amount"].'</span>';
            $planDataSectionHtml .= '</td></tr>';

            $planDataSectionHtml .= '<tr class="tablePage2TD" style="">';
            $planDataSectionHtml .= '<td>'.$planDataSection["RequiredAvailableCredit"]["Text"].'</td>';
            $planDataSectionHtml .= '<td class="text-right" style="">';
            $planDataSectionHtml .= '<span>'.$currencySymbol.$planDataSection["RequiredAvailableCredit"]["Amount"].'</span>';
            $planDataSectionHtml .= '</td></tr>';

          }

          $html .= $planDataSectionHtml;
          $html .= '</tbody>';
          $html .= '</table></div>';
          $html .= '<a id="payment-schedule-link" style="">See Complete Payment Schedule</a>';
          $html .= '</div>';
          $html .= '</div>';
          $html .= '<div class="form-block right" style="">';
          $html .= '<div class="form-block-area">';
          $html .= '<div>';
          $html .= '<div class="important_note_sec" style="">'.$approvalUrlResponseArr["ImportantNotesSection"]["ImportantNotesHeader"]["Text"].':</div>';
          $html .= '<div class="pnlEula" style="">'.$approvalUrlResponseArr["ImportantNotesSection"]["ImportantNotesBody"]["Text"].'</div>';
          $html .= '<div id="i_acknowledge_area"><input type="checkbox" id="i_acknowledge" class="i_acknowledge" name="i_acknowledge" value="" />';
          $html .= '<label for="i_acknowledge" class="i_acknowledge_lbl">';
          $html .= 'I acknowledge that I have read and agree to the <a href="#" id="i_acknowledge_content_show" > terms and conditions </a> </label><div style="display:none" class="i_ack_err"> Please select I acknowledge.</div></div>' ;


            
          $html .= '</div>';
          $html .= '</div>';
          $html .= '</div>';
          $html .= '</div>';
          $html .= '<div class="iAgreeBtn" style=""><input id="iagree" type="button" onclick="paymentSave();" value="I agree" style=" ">  </div>';
          $html .= '<div class="termAndConditionBtn" style=""> <a href="'.$termsConditionsLink.'" target="_blank" style="">'.$termsConditionsText.'</a> | <a href="'.$servicesLink.'" style="" target="_blank">'.$servicesText.'</a>

</div>';
          
          $html .= '</div>';
                    
                
            
        
        }

        return $html;  
    }

    public function getTermnConditionText(){
        $str = '<p style="text-align: left;">1. &nbsp;Buyer, whose name appears below ("Buyer", "You", or "Your"), promises to pay the full amount of the Total Authorized Purchase Price in the number of installment payments set forth in the Recurring Installment Payment Authorization ("Authorization") to Seller ("Seller", "We" or "Us") by authorizing Seller to charge Buyer’s credit card in equal monthly installments as set forth in the Authorization (each an "Installment") each month until paid in full.</p>
<p style="text-align: left;">2. &nbsp;Buyer agrees that Seller will obtain authorization on Buyer’s credit card for the full amount of the Purchase at the time of sale, and Seller will obtain authorizations on Buyer’s credit card each month for the Installment and the entire remaining balance of the Purchase. Buyer understands that this authorization will remain in effect until Buyer cancels it in writing.</p>
<p style="text-align: left;">3. &nbsp;Buyer acknowledges that Seller obtaining initial authorization for the Purchase, along with monthly authorization for each Installment and the outstanding balance, may adversely impact Buyer’s available credit on Buyer’s credit card. Buyer agrees to hold Seller harmless for any adverse consequences to Buyer.</p>
<p style="text-align: left;">4. &nbsp;Buyer agrees to notify Seller in writing via Buyer’s user account at <a title="consumer.Splitit.com" href="http://consumer.Splitit.com" target="_blank">consumer.splitit.com</a> of any changes to Buyer’s credit card account information or termination of this authorization. We will update such information and process such requests within 30 days after our receipt of such request. Buyer understands that the Installment payments may be authorized and charged on the next business day. Buyer further understands that because these are electronic transactions, any authorizations and charges may be posted to Your account as soon as the Installment payment dates.</p>
<p style="text-align: left;">5. &nbsp;Any Installment amounts due under this contract that have been charged to Buyer’s credit card and not paid when due, pursuant to Your agreement with Your credit card issuer ("Issuer"), will be charged interest at the Annual Percentage Rate stated in Your Issuer’s Federal Truth-in-Lending Disclosure statement until the Installments are fully paid. So long as You timely pay each Installment to Your Issuer when due, Issuer will not charge Buyer interest on such Installment. Issuer may charge Buyer interest on any other balance You may have on Your credit card in excess of the Installment amount.</p>
<p style="text-align: left;">6. &nbsp;In the case of an authorization being rejected for any reason, Buyer understands that Seller may, in its discretion, attempt to process the charge again within seven&nbsp;(7) days.</p>
<p style="text-align: left;">7. &nbsp;In the event that Buyer’s Issuer fails to pay an Installment for any reason, Seller, at its discretion, may charge Buyer’s credit card at any time for the full outstanding amount due.</p>
<p style="text-align: left;">8. &nbsp;In consideration for services provided by Splitit&nbsp;USA, Inc. ("Splitit") to Seller, Buyer agrees that Splitit will have the right to communicate with and solicit Buyer via e-mail (or other means). This provision is operational for not less than five (5) years from the date of the initial authorization.<br>
9. &nbsp;Buyer understands that Splitit is not a party to this Agreement, which is solely between Buyer and Seller.</p>
<p style="text-align: left;">10. &nbsp;Buyer understands and agrees that Splitit is not responsible for the delivery and quality of goods purchased in this transaction.</p>
<p style="text-align: left;">11. &nbsp;Buyer acknowledges that the origination of any authorized transactions to the Buyer’s account must comply with the provisions of U.S. law. Buyer certifies that Buyer is an authorized user of the credit card utilized for this transaction and the Installments and will not dispute these transactions with Buyer’s credit card company, so long as the authorizations correspond to the terms indicated in the authorization form.</p>
<p style="text-align: left;">12. &nbsp;Buyer agrees that if delivery of the goods or services are not made at the time of execution of this contract, the description of the goods or services and the due date of the first Installment may be inserted by Seller in Seller’s counterpart of the contract after it has been signed by Buyer.</p>
<p style="text-align: left;">13. &nbsp;If any provision of this contract is determined to be invalid, it shall not affect the remaining provisions hereof.</p>
<p style="text-align: left;">14. &nbsp;PRIVACY POLICY. Buyer’s privacy is important to us. You may obtain a copy of Splitit’s Privacy Policy by visiting their website at <a title="consumer.Splitit.com" href="http://consumer.Splitit.com" target="_blank">consumer.splitit.com</a>. As permitted by law, Seller and Splitit may share information about our transactions and experiences with Buyer with other affiliated companies and unaffiliated third parties, including consumer reporting agencies and other creditors. However, except as permitted by law, neither Seller nor Splitit may share information which was obtained from credit applications, consumer reports, and any third parties with companies affiliated with us if Buyer instructs us not to share this information. If Buyer does not want us to share this information, Buyer shall notify us in writing via Buyer’s user account at <a title="consumer.Splitit.com" href="http://consumer.Splitit.com" target="_blank">consumer.splitit.com</a> using the password Buyer was provided with for such notification and for accessing information on Splitit’s website. Buyer shall include Buyer’s name, address, account number and the last four digits of Buyer’s credit card number used in this transaction so such request can be honored. Seller may report about Your account to consumer reporting agencies. Late payments, missed payments, or other defaults on Your credit card account may be reflected by Your Issuer in Your credit report.</p>
<p style="text-align: left;">15. &nbsp;ARBITRATION. Any claim, dispute or controversy ("Claim") arising from or connected with this Agreement, including the enforceability, validity or scope of this arbitration clause or this Agreement, shall be governed by this provision. Upon the election of Buyer or Seller by written notice to the other party, any Claim shall be resolved by arbitration before a single arbitrator, on an individual basis, without resort to any form of class action ("Class Action Waiver"), pursuant to this arbitration provision and the applicable rules of the American Arbitration Association ("AAA") in effect at the time the Claim is filed. Any arbitration hearing shall take place within the State of New York, County of New York. At the written request of Buyer, any filing and administrative fees charged or assessed by the AAA which are required to be paid by Buyer and that are in excess of any filing fee Buyer would have been required to pay to file a Claim in state court in New York shall be advanced and paid for by Seller. The arbitrator may not award punitive or exemplary damages against any party. IF ANY PARTY COMMENCES ARBITRATION WITH RESPECT TO A CLAIM, NEITHER BUYER OR SELLER WILL HAVE THE RIGHT TO LITIGATE THAT CLAIM IN COURT OR HAVE A JURY TRIAL ON THAT CLAIM, OR TO ENGAGE IN PRE-ARBITRATION DISCOVERY, EXCEPT AS PROVIDED FOR IN THE APPLICABLE ARBITRATION RULES. FURTHER, BUYER WILL NOT HAVE THE RIGHT TO PARTICIPATE AS A REPRESENTATIVE OR MEMBER OF ANY CLASS OF CLAIMANTS PERTAINING TO THAT CLAIM, AND BUYER WILL HAVE ONLY THOSE RIGHTS THAT ARE AVAILABLE IN AN INDIVIDUAL ARBITRATION. THE ARBITRATOR’S DECISION WILL BE FINAL AND BINDING ON ALL PARTIES, EXCEPT AS PROVIDED IN THE FEDERAL ARBITRATION ACT ("the FAA"). This Arbitration Provision shall be governed by the FAA, and, if and where applicable, the internal laws of the State of New York. If any portion of this Arbitration provision is deemed invalid or unenforceable, it shall not invalidate the remaining portions of this Arbitration provision or the Agreement, provided however, if the Class Action Waiver is deemed invalid or unenforceable, then this entire Arbitration provision shall be null and void and of no force or effect, but the remaining terms of this Agreement shall remain in full force and effect. Any appropriate court having jurisdiction may enter judgment on any award.</p>';

    return $str;
    }
}