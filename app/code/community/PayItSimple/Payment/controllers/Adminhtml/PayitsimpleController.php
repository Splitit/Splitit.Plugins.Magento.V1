<?php

class PayItSimple_Payment_Adminhtml_PayitsimpleController extends Mage_Adminhtml_Controller_Action {
	protected function _isAllowed() {
		return Mage::getSingleton('admin/session')->isAllowed('system/config');
	}
	/**
	 * Return some checking result
	 *
	 * @return void
	 */
	public function checkAction() {
		$storeId = $this->getRequest()->getParam('store_id', 0);
		$paymentMethod = Mage::getModel('pis_payment/pisMethod');

		if (!$paymentMethod->getConfigData('api_terminal_key', $storeId) || !$paymentMethod->getConfigData('api_username') || !$paymentMethod->getConfigData('api_password')) {
			$message = 'Please enter the credentials and save configuration';
		} else {
			$api = $paymentMethod->getApi();
			$params = array(
				'ApiKey' => $paymentMethod->getConfigData('api_terminal_key', $storeId),
				'UserName' => $paymentMethod->getConfigData('api_username'),
				'Password' => $paymentMethod->getConfigData('api_password'),
				'TouchPoint' => array("Code" => "MagentoPlugin", "Version" => "2.0"),
			);
			$result = $api->login($paymentMethod->getApiUrl(), $params);
			$paymentMethod->debugData('REQUEST: ' . $api->getRequest());
			$paymentMethod->debugData('RESPONSE: ' . $api->getResponse());
			$message = ($paymentMethod->getConfigData('sandbox_flag')) ? '[Sandbox Mode] ' : '[Production mode] ';
			if ($result) {
				$message .= 'Successfully login! API available!';
			} else {
				$error = $api->getError();
				$message .= $error['code'] . ' - ERROR: ' . $error['message'];
			}
		}

		Mage::app()->getResponse()->setBody($message);
	}

	public function checkforupdatesAction() {
		$language = $this->getRequest()->getParam('language');
		$paymentMethod = Mage::getModel('pis_payment/pisMethod');
		$api = $paymentMethod->getApi();
		$params = array(
			"SystemTextCategories" => array("Common", "PaymentDetails", "CardBrand", "TermsAndConditions", "EComm"),
			"RequestContext" => array("CultureName" => $language),
		);
		$url = $paymentMethod->getApiUrl() . "api/Infrastructure/GetResources";
		$result = $api->getResourcesFromSplitit($url, $params);
		$result = json_decode($result, true);
		$finalResult = array();
		if (isset($result["ResponseHeader"]["Succeeded"]) && $result["ResponseHeader"]["Succeeded"] == true) {
			foreach ($result["ResourcesGroupedByCategories"] as $key => $value) {
				foreach ($value as $k => $v) {
					$finalResult[$k] = $v;
				}
			}
		}
		Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($finalResult));
		return true;
	}

	/*functions for payment form*/
	public function checkPaymentFormAction() {
		$storeId = $this->getRequest()->getParam('store_id', 0);
		$paymentMethod = Mage::getModel('pis_payment/pisPaymentFormMethod');

		if (!$paymentMethod->getConfigData('api_terminal_key', $storeId) || !$paymentMethod->getConfigData('api_username') || !$paymentMethod->getConfigData('api_password')) {
			$message = 'Please enter the credentials and save configuration';
		} else {
			$api = $paymentMethod->getApi();
			$params = array(
				'ApiKey' => $paymentMethod->getConfigData('api_terminal_key', $storeId),
				'UserName' => $paymentMethod->getConfigData('api_username'),
				'Password' => $paymentMethod->getConfigData('api_password'),
				'TouchPoint' => array("Code" => "MagentoPlugin", "Version" => "2.0"),
			);
			$result = $api->login($paymentMethod->getApiUrl(), $params);
			$paymentMethod->debugData('REQUEST: ' . $api->getRequest());
			$paymentMethod->debugData('RESPONSE: ' . $api->getResponse());
			$message = ($paymentMethod->getConfigData('sandbox_flag')) ? '[Sandbox Mode] ' : '[Production mode] ';
			if ($result) {
				$message .= 'Successfully login! API available!';
			} else {
				$error = $api->getError();
				$message .= $error['code'] . ' - ERROR: ' . $error['message'];
			}
		}

		Mage::app()->getResponse()->setBody($message);
	}

	public function checkforupdatesPaymentFormAction() {

		$language = $this->getRequest()->getParam('language');
		$paymentMethod = Mage::getModel('pis_payment/pisPaymentFormMethod');
		$api = $paymentMethod->getApi();
		$params = array(
			"SystemTextCategories" => array("Common", "PaymentDetails", "CardBrand", "TermsAndConditions", "EComm"),
			"RequestContext" => array("CultureName" => $language),
		);
		$url = $paymentMethod->getApiUrl() . "api/Infrastructure/GetResources";
		$result = $api->getResourcesFromSplitit($url, $params);
		$result = json_decode($result, true);
		$finalResult = array();
		if (isset($result["ResponseHeader"]["Succeeded"]) && $result["ResponseHeader"]["Succeeded"] == true) {
			foreach ($result["ResourcesGroupedByCategories"] as $key => $value) {
				foreach ($value as $k => $v) {
					$finalResult[$k] = $v;
				}
			}
		}
		
		Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($finalResult));
		return true;
	}

	public function prodlistAction() {
		$params = $this->getRequest()->getParams();
		$result = array();
		if (isset($params['isAjax']) && $params['isAjax']) {
			$Productlist = Mage::getSingleton('pis_payment/source_productskus');
			if ((isset($params['term']) && $params['term']) || (isset($params['prodIds']) && $params['prodIds'])) {
				$result = $Productlist->toOptionArray($params);
			}
		}
		Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
		return true;
	}
}