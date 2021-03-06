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

	public function prodlistAction()
	{
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

	public function apiLoginAction()
	{

		$api = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->_initApi($storeId = null);
		$params = $this->getRequest()->getParams();

		$response = array(
			"status" => false,
			"error" => "",
			"success" => "",
			"data" => "",
			"installmentNum" => "1",
		);

		$forterToken = Mage::getStoreConfig('payment/pis_cc/fortertoken');
		if ($forterToken) {
			Mage::getSingleton('core/session')->setSplititForterToken(isset($params['ForterToken']) ? $params['ForterToken'] : null);
		}

		if ($api->isLogin()) {
			Mage::log('=========splitit logging start=========');
			$ipnForLogs = Mage::getSingleton('core/session')->getSplititSessionid();
			Mage::log('Splitit session Id : ' . $ipnForLogs);
			$response["status"] = true;
		} else {
			foreach ($api->getError() as $key => $value) {
				$response["error"] .= $value . " ";
			}
		}
		Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		return true;
	}

	public function installmentplaninitAction() {
	    $storeId = Mage::app()->getStore()->getId();
        $api = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->_initApi($storeId);
		Mage::log('=========splitit : InstallmentPlan Init for Embedded =========');
		$response = array(
			"status" => false,
			"error" => "",
			"success" => "",
			"data" => "",
		);
		$splititSessionId = Mage::getSingleton('core/session')->getSplititSessionid();
		if ($splititSessionId != "") {
			$result = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->installmentplaninitForHostedSolution();
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

		return true;
	}

	public function testAction()
	{
		$params = "53342166015563671044";
		$paymentFormCollection = Mage::getModel('pis_payment/pispayment')->getCollection()->addFieldToFilter('installment_plan_number', $params)->addFieldToFilter('order_created', 1);
		$paymentFormData = $paymentFormCollection->getFirstItem();

		return $paymentFormData->getData();
		/*$tablePrefix = (string) Mage::getConfig()->getTablePrefix();
			$db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
			$sql1 = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "' . $params . '" and order_created = 1';
		*/
	}

	public function successExitAction()
	{
		$params = $this->getRequest()->getParams();
		Mage::getSingleton('core/session')->setInstallmentPlanNumber($params["InstallmentPlanNumber"]);
		Mage::log('======= successExitAction :  =======InstallmentPlanNumber coming from splitit in url: ' . $params["InstallmentPlanNumber"]);

		/*fetch details from Splitit db table*/

		$paymentFormCollection = Mage::getModel('pis_payment/pispayment')->getCollection()->addFieldToFilter('installment_plan_number', $params["InstallmentPlanNumber"])->addFieldToFilter('order_created', 0);
		$data = $paymentFormCollection->getFirstItem();
		$data = $data->getData();

		/*check if order already created via Async etc.*/
		if (count($data) && $data["order_id"] != 0 && $data["order_increment_id"] != null) {

			Mage::log('======= check if order already created via Async etc.   ======= ');
			Mage::getSingleton('core/session')->setOrderIncrementId($data["order_increment_id"]);
			Mage::getSingleton('core/session')->setOrderId($data["order_id"]);
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "payitsimple/payment/success")->sendResponse();
			return;
		}

		/*check grand total of quote and IPN
		get installmentplan details*/
		$storeId = Mage::app()->getStore()->getStoreId();
		$api = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->_initApi($storeId = null);
		$planDetails = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->getInstallmentPlanDetails($api);

		Mage::log('======= get installmentplan details :  ======= ');
		Mage::log($planDetails);

		$quote = Mage::getModel('checkout/session')->getQuote();
		$quoteGrandTotal = number_format((float) $quote->getGrandTotal(), 2, '.', '');

		$apiURL = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->getApiUrl();
		$verifyResult = Mage::getSingleton("pis_payment/api")->verifyPayment($apiURL, $params["InstallmentPlanNumber"]);
		Mage::log('======= verify result :  ======= ');
		Mage::log($verifyResult);

		if (
			isset($verifyResult['errorMsg']) ||
			!isset($verifyResult['IsPaid']) ||
			!isset($verifyResult['OriginalAmountPaid']) && $verifyResult['OriginalAmountPaid'] != $quoteGrandTotal
		) {
			Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__('Something went wrong during the payment. Please try again.'));
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/cart")->sendResponse();
			return;
		}

		if (count($data) && $quote->getId() == $data["quote_id"] && $quoteGrandTotal == $planDetails["grandTotal"] && (($planDetails["planStatus"] == "PendingMerchantShipmentNotice" || $planDetails["planStatus"] == "InProgress") || ($planDetails["numberOfInstallments"] == 1 && $planDetails["planStatus"] == "Cleared"))) {
			/* create order */
			$convertQuote = Mage::getModel('sales/quote')->load($quote->getId());
			$convertQuote->collectTotals();
			$service = Mage::getModel('sales/service_quote', $convertQuote);
			$service->submitAll();
			Mage::getSingleton('checkout/session')->setLastQuoteId($convertQuote->getId())
				->setLastSuccessQuoteId($convertQuote->getId())
				->clearHelperData();
			$order = $service->getOrder();
			if ($order) {
				Mage::getSingleton('checkout/session')->setLastOrderId($order->getId())
					->setLastRealOrderId($order->getIncrementId());
			}
			$quote->setIsActive(false)->save();
			$orderObj = Mage::getModel('sales/order')->load($order->getId());
			$grandTotal = number_format((float) $orderObj->getGrandTotal(), 2, '.', '');
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
			Mage::log('========== splitit update ref order number ==============');
			$updateStatus = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->updateRefOrderNumber($api, $orderObj);
			if ($updateStatus["status"] == false) {
				Mage::throwException(
					Mage::helper('payment')->__($updateStatus["data"])
				);
			}

			if ($paymentAction == "authorize_capture") {

				$payment->setShouldCloseParentTransaction(true);
				$payment->setIsTransactionClosed(1);
				$payment->registerCaptureNotification($grandTotal);
				$orderObj->addStatusToHistory(
					false,
					'Payment NotifyOrderShipped was sent with number ID: ' . Mage::getSingleton('core/session')->getInstallmentPlanNumber(),
					false
				);
			}
			/*$orderObj->queueNewOrderEmail();*/
			$orderObj->sendNewOrderEmail();
			$orderObj->save();
			$orderId = $orderObj->getId();
			$orderIncrementId = $orderObj->getIncrementId();

			/*update order_created in splitit_hosted_solution*/
			$db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
			$updateQue = Mage::getModel('pis_payment/pispayment')->load($params["InstallmentPlanNumber"], 'installment_plan_number');
			$updateQue->setOrderCreated(1);
			$updateQue->setOrderId($orderObj->getId());
			$updateQue->setOrderIncrementId($orderObj->getIncrementId());
			$updateQue->save();
			/*$db_write->query($updateQue);*/
			Mage::log('====== Order Id =====:' . $orderId . '==== Order Increment Id ======:' . $orderIncrementId);
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/onepage/success")->sendResponse();
		} else {
			Mage::log('====== Order cancel due to Grand total and Payment detail total coming from Api is not same. =====');
			$cancelResponse = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
			if ($cancelResponse["status"]) {
				Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "payitsimple/payment/cancelExit")->sendResponse();
			}
		}
	}

	public function successAction()
	{
		$orderIncrementId = Mage::getSingleton('core/session')->getOrderIncrementId();
		$orderId = Mage::getSingleton('core/session')->getOrderId();
		/*check if order created else redirect to cart page*/
		if ($orderId != "" && $orderIncrementId != "") {
			$this->loadLayout();
			$this->renderLayout();
		} else {
			Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/cart")->sendResponse();
		}
	}

	public function cancelAction()
	{
		$this->loadLayout();
		$this->renderLayout();
	}

	public function successAsyncAction()
	{
		$params = $this->getRequest()->getParams();
		/*remove plan from session which were created when user click on radio button*/
		/*Mage::getSingleton('core/session')->setSplititInstallmentPlanNumber("");*/
		Mage::getSingleton('core/session')->setInstallmentPlanNumber($params["InstallmentPlanNumber"]);
		Mage::log('======= successAsyncAction :  =======InstallmentPlanNumber coming from splitit in url: ' . $params["InstallmentPlanNumber"]);

		/*$tablePrefix = (string) Mage::getConfig()->getTablePrefix();
		$db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
		check if order already created with the installment plan number coming from in parameters via async call(async run before response). Get data from SPLITIT_HOSTED_SOLUTION table
		$sql1 = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "' . $params["InstallmentPlanNumber"] . '" and order_created = 1';
		$data = $db_read->fetchRow($sql1);*/
		$paymentFormCollection = Mage::getModel('pis_payment/pispayment')->getCollection()->addFieldToFilter('installment_plan_number', $params["InstallmentPlanNumber"])->addFieldToFilter('order_created', 1);
		$data = $paymentFormCollection->getFirstItem();
		$data = $data->getData();
		Mage::log('======= paymentFormData========= ');
		Mage::log($data);
		/*check if order already created via Async etc.*/
		if (count($data) && $data["order_id"] != 0 && $data["order_increment_id"] != null) {
			return true;
		}

		/*check grand total of quote and IPN
		get installmentplan details*/
		$storeId = Mage::app()->getStore()->getStoreId();
		$api = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->_initApi($storeId = null);
		Mage::log('======= apiData======');
		Mage::log($api);
		$planDetails = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->getInstallmentPlanDetails($api);

		Mage::log('======= get installmentplan details :  ======= ');
		Mage::log($planDetails);

		$quote = Mage::getModel('sales/quote')->load($data["quote_id"]);
		Mage::log('=====quoteData=====');
		Mage::log($quote->getData());
		$quoteGrandTotal = number_format((float) $quote->getGrandTotal(), 2, '.', '');
		//echo ;die;

		$apiURL = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->getApiUrl();
		$verifyResult = Mage::getSingleton("pis_payment/api")->verifyPayment($apiURL, $params["InstallmentPlanNumber"]);
		Mage::log('======= verify result :  ======= ');
		Mage::log($verifyResult);

		if (
			isset($verifyResult['errorMsg']) ||
			!isset($verifyResult['IsPaid']) ||
			!isset($verifyResult['OriginalAmountPaid']) && $verifyResult['OriginalAmountPaid'] != $quoteGrandTotal
		) {
			return false;
		}

		if (count($data) && $quote->getId() == $data["quote_id"] && $quoteGrandTotal == $planDetails["grandTotal"] && (($planDetails["planStatus"] == "PendingMerchantShipmentNotice" || $planDetails["planStatus"] == "InProgress") || ($planDetails["numberOfInstallments"] == 1 && $planDetails["planStatus"] == "Cleared"))) {
			/* create order */
			$convertQuote = Mage::getModel('sales/quote')->load($quote->getId());
			$convertQuote->collectTotals();
			$service = Mage::getModel('sales/service_quote', $convertQuote);
			$service->submitAll();
			Mage::getSingleton('checkout/session')->setLastQuoteId($convertQuote->getId())
				->setLastSuccessQuoteId($convertQuote->getId())
				->clearHelperData();
			$order = $service->getOrder();
			Mage::log('======orderData======');
			Mage::log($order->getData());
			if ($order) {
				Mage::getSingleton('checkout/session')->setLastOrderId($order->getId())
					->setLastRealOrderId($order->getIncrementId());
			}
			$quote->setIsActive(false)->save();
			$orderObj = Mage::getModel('sales/order')->load($order->getId());
			$grandTotal = number_format((float) $orderObj->getGrandTotal(), 2, '.', '');
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
			Mage::log('======update order status history======');
			$orderObj->addStatusToHistory(
				$orderObj->getStatus(),
				'Payment InstallmentPlan was created with number ID: '
					. Mage::getSingleton('core/session')->getInstallmentPlanNumber(),
				false
			);
			Mage::log('========== splitit update ref order number ==============');
			$updateStatus = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->updateRefOrderNumber($api, $orderObj);
			if ($updateStatus["status"] == false) {
				Mage::throwException(
					Mage::helper('payment')->__($updateStatus["data"])
				);
			}
			Mage::log('======check authorize_capture======');
			if ($paymentAction == "authorize_capture") {

				$payment->setShouldCloseParentTransaction(true);
				$payment->setIsTransactionClosed(1);
				$payment->registerCaptureNotification($grandTotal);
				$orderObj->addStatusToHistory(
					false,
					'Payment NotifyOrderShipped was sent with number ID: ' . Mage::getSingleton('core/session')->getInstallmentPlanNumber(),
					false
				);
			}
			Mage::log('======send order email======');
			/*$orderObj->queueNewOrderEmail();*/
			$orderObj->sendNewOrderEmail();
			$orderObj->save();

			/*update order_created in splitit_hosted_solution*/
			$db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
			$updateQue = Mage::getModel('pis_payment/pispayment')->load($params["InstallmentPlanNumber"], 'installment_plan_number');
			$updateQue->setOrderCreated(1);
			$updateQue->setOrderId($orderId);
			$updateQue->setOrderIncrementId($orderIncrementId);
			$updateQue->save();
			/*$db_write->query($updateQue);*/
			Mage::log('====== Order Id =====:' . $orderId . '==== Order Increment Id ======:' . $orderIncrementId);
			/*Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "checkout/onepage/success")->sendResponse();*/
			return true;
		} else {
			Mage::log('====== Order cancel due to Grand total and Payment detail total coming from Api is not same. =====');
			$cancelResponse = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
			if ($cancelResponse["status"]) {
				/*Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . "payitsimple/payment/cancel")->sendResponse();*/
				return true;
			}
		}
		return false;
	}

	public function cancelAsyncAction()
	{
		$params = $this->getRequest()->getParams();
		/*Mage::getSingleton('core/session')->setInstallmentPlanNumber($params["InstallmentPlanNumber"]);*/
		Mage::log('======= cancelAsyncAction :  =======InstallmentPlanNumber coming from splitit in url: ' . $params["InstallmentPlanNumber"]);

		/*get plan number info from database table SPLITIT_HOSTED_SOLUTION*/
		$sqlLoad = Mage::getModel('pis_payment/pispayment')->load($params["InstallmentPlanNumber"], 'installment_plan_number');
		$data = $sqlLoad->getData();
		/*print_r($data);exit;*/

		if (count($data) && $data["order_id"] == 0 && $data["order_increment_id"] == null) {
			return json_encode(array('success' => false, 'error' => 'Order does not exist for this IPN.'));
		}

		/*get installmentplan details*/
		$storeId = Mage::app()->getStore()->getStoreId();
		$api = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->_initApi($storeId = null);
		$planDetails = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->getInstallmentPlanDetails($api);
		/*print_r($planDetails);exit;*/
		Mage::log('======= get installmentplan details :  ======= ');
		Mage::log($planDetails);

		$orderId = $data["order_id"];
		$orderIncrementId = $data["order_increment_id"];
		$orderObj = Mage::getModel('sales/order')->load($orderId);
		$grandTotal = number_format((float) $orderObj->getGrandTotal(), 2, '.', '');
		$planDetails["grandTotal"] = number_format((float) $planDetails["grandTotal"], 2, '.', '');
		Mage::log('======= grandTotal(orderObj):' . $grandTotal . ', grandTotal(planDetails):' . $planDetails["grandTotal"] . '   ======= ');

		if (count($data) && $grandTotal == $planDetails["grandTotal"] && ($planDetails["planStatus"] == "PendingMerchantShipmentNotice" || $planDetails["planStatus"] == "Pending")) {

			if ($orderObj->canCancel()) {
				try {
					$orderObj->cancel();

					/*remove status history set in _setState*/
					$orderObj->getStatusHistoryCollection(true);

					$orderObj->save();
					return json_encode(array('success' => true, 'msg' => 'Order Cancelled.'));
				} catch (Exception $e) {
					Mage::logException($e);
					return json_encode(array('success' => false, 'error' => $e->getMessage()));
				}
			}
		} else {

			Mage::log('====== Order Grand total and Payment detail total coming from Api is not same. =====');
			Mage::log('Grand Total : ' . $grandTotal);
			Mage::log('Plan Details Total : ' . $planDetails["grandTotal"]);
			return json_encode(array('success' => false, 'error' => 'Order total do not match.'));
		}
	}

	public function cancelExitAction()
	{

		/* $params = $this->getRequest()->getParams();
			          $storeId = Mage::app()->getStore()->getStoreId();
			          $api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);
			          $cancelResponse = Mage::getSingleton("pis_payment/pisMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
			          if($cancelResponse["status"]){
			          Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/cancel")->sendResponse();
		*/

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

	public function errorExitAction()
	{

		/* $params = $this->getRequest()->getParams();
			          $storeId = Mage::app()->getStore()->getStoreId();
			          $api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);
			          $cancelResponse = Mage::getSingleton("pis_payment/pisMethod")->cancelInstallmentPlan($api, $params["InstallmentPlanNumber"]);
			          if($cancelResponse["status"]){
			          Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl()."payitsimple/payment/cancel")->sendResponse();
		*/

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

	public function redirectAction()
	{

		Mage::helper('pis_payment')->getCreditCardFormTranslation('ecomm_redirect_to_payment_form');

		$splititCheckoutUrl = Mage::getSingleton('checkout/session')->getSplititCheckoutUrl();
		$splititInstallmentPlanNumber = Mage::getSingleton('checkout/session')->getSplititInstallmentPlanNumber();
		if ($splititCheckoutUrl != "") {
			/*$tablePrefix = (string) Mage::getConfig()->getTablePrefix();
				$db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
				$sql1 = 'SELECT * FROM `' . $tablePrefix . 'splitit_hosted_solution` where installment_plan_number = "' . $splititInstallmentPlanNumber . '"';
			*/
			$sqlLoad = Mage::getModel('pis_payment/pispayment')->load($splititInstallmentPlanNumber, 'installment_plan_number');
			$data = $sqlLoad->getData();
			/*check if order already created via Async etc.*/
			if (count($data) && $data["order_id"] == 0 && $data["order_increment_id"] == null) {
				$orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
				$lastRecordByOrder = Mage::getModel('pis_payment/pispayment')->load($orderId, 'order_id');
				$orderIncrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
				if (
					!($orderId && !$lastRecordByOrder->getId()) &&      // if there's no such order for splitit payment
					!$lastRecordByOrder->getId() ||
					($lastRecordByOrder->getId() && !$lastRecordByOrder->getOrderCreated())
				) {
					$updateQue = Mage::getModel('pis_payment/pispayment')->load($splititInstallmentPlanNumber, 'installment_plan_number');
					$updateQue->setOrderId($orderId);
					$updateQue->setOrderIncrementId($orderIncrementId);
					$updateQue->save();
				}
				Mage::app()->getFrontController()->getResponse()->setRedirect($splititCheckoutUrl)->sendResponse();
			}
		}
	}
}
