<?php

class PayItSimple_Payment_Adminhtml_PayitsimpleController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Return some checking result
     *
     * @return void
     */
    public function checkAction()
    {
        $storeId = $this->getRequest()->getParam('store_id', 0);
        $paymentMethod = Mage::getModel('pis_payment/pisMethod');
        $api = $paymentMethod->getApi();
        $params = array(
            'ApiKey' => $paymentMethod->getConfigData('api_terminal_key', $storeId),
            'UserName' => $paymentMethod->getConfigData('api_username'),
            'Password' => $paymentMethod->getConfigData('api_password'),
            'TouchPoint'=>array("Code" => "MagentoPlugin","Version" => "2.0")
        );
        $result = $api->login($paymentMethod->getApiUrl(), $params);
        $paymentMethod->debugData('REQUEST: ' . $api->getRequest());
        $paymentMethod->debugData('RESPONSE: ' . $api->getResponse());
        $message = ($paymentMethod->getConfigData('sandbox_flag'))?'[Sandbox Mode] ':'[Production mode] ';
        if ($result) {
            $message .= 'Successfully login! API available!';
        } else {
            $error = $api->getError();
            $message .= $error['code'] . ' - ERROR: '. $error['message'];
        }
        Mage::app()->getResponse()->setBody($message);
    }

    public function checkforupdatesAction(){
        $language = $this->getRequest()->getParam('language');
        $paymentMethod = Mage::getModel('pis_payment/pisMethod');
        $api = $paymentMethod->getApi();
        $params = array(
                    "SystemTextCategories" => ["Common","PaymentDetails","CardBrand","TermsAndConditions","EComm"],
                    "RequestContext" => ["CultureName" => $language]
        );
        $url = $paymentMethod->getApiUrl()."api/Infrastructure/GetResources";
        $result = $api->getResourcesFromSplitit($url, $params);
        $result = json_decode($result, true);
        $finalResult = array();
        if(isset($result["ResponseHeader"]["Succeeded"]) && $result["ResponseHeader"]["Succeeded"] == true){
            foreach($result["ResourcesGroupedByCategories"] as $key=>$value){
                foreach($value as $k => $v){
                    $finalResult[$k] = $v;    
                }
            }
        }
        echo json_encode($finalResult);
        return ;
    }
}