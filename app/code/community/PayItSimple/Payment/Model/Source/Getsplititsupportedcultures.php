<?php

class PayItSimple_Payment_Model_Source_Getsplititsupportedcultures
{
    public function toOptionArray()
    {
        $apiUrl = Mage::getSingleton('pis_payment/pisPaymentFormMethod')->getApiUrl();
        $getSplititSupportedCultures = Mage::getSingleton('pis_payment/api')->getSplititSupportedCultures($apiUrl."api/Infrastructure/SupportedCultures");
        $decodedResult = Mage::helper('core')->jsonDecode($getSplititSupportedCultures);
        if(isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1 && count($decodedResult["Cultures"])){
            foreach ($decodedResult["Cultures"] as $key => $value) {
                $allCulture[]= array('value' =>$value["CultureName"], 'label' => $value["DisplayName"]);
            }
        }

        return $allCulture;

     
    }
}