<?php
class PayItSimple_Payment_Model_Observer {
	public function insertBlock($observer) {
		$_block = $observer->getBlock();
		$_type = $_block->getType();
		$extensionEnabled = Mage::getStoreConfig('payment/pis_cc/active') || Mage::getStoreConfig('payment/pis_paymentform/active') ? true : false;
		if (!$extensionEnabled) {
			return;
		}

		if (($_type == 'catalog/product_price' && $_block->getTemplate() == 'catalog/product/price.phtml') or $_type == 'checkout/cart_totals') {
			$_child = clone $_block;
			$_child->setType('payitsimple/block');
			if ($_type == 'checkout/cart_totals') {
				$_block->setChild('child', $_child);
			} else {
				$_block->setChild('child' . $_child->getProduct()->getId(), $_child);
			}
			if ($this->checkProductBasedAvailability("pis_cc") || $this->checkProductBasedAvailability("pis_paymentform")) {
				$_block->setTemplate('payitsimple/splitprice.phtml');
			}
		}
	}

	public function paymentMethodIsActive(Varien_Event_Observer $observer) {

		$event = $observer->getEvent(); //print_r($event->getData());die("---sdf");
		$method = $event->getMethodInstance();
		$result = $event->getResult();
		$currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

		if ($method->getCode() == "pis_cc") {
			$result->isAvailable = $this->checkAvailableInstallments("pis_cc") && $this->checkProductBasedAvailability("pis_cc");
		} else if ($method->getCode() == "pis_paymentform") {
			$result->isAvailable = $this->checkAvailableInstallments("pis_paymentform") && $this->checkProductBasedAvailability("pis_paymentform");
		}

	}

	private function checkAvailableInstallments($paymentMethod) {
		$installments = array();
		$totalAmount = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
		$selectInstallmentSetup = Mage::getStoreConfig('payment/' . $paymentMethod . '/select_installment_setup');
		$installmentsInDropdown = array();
		$options = Mage::getModel('pis_payment/source_installments')->toOptionArray();

		$depandOnCart = 0;
		// check if splitit extension is disable from admin
		$isDisabled = Mage::getStoreConfig('payment/' . $paymentMethod . '/active');
		if (!$isDisabled) {
			return false;
		}

		// $selectInstallmentSetup == "" for checking when merchant first time upgrade extension that time $selectInstallmentSetup will be empty
		if ($selectInstallmentSetup == "" || $selectInstallmentSetup == "fixed") {
			// Select Fixed installment setup

			$fixedInstallments = Mage::getStoreConfig('payment/' . $paymentMethod . '/available_installments');
			$installmentsCount = $this->countForInstallment($fixedInstallments, $options, $installmentsText, $totalAmount, $perMonthText);

		} else {
			// Select Depanding on cart installment setup
			$depandOnCart = 1;
			$dataAsPerCurrency = $this->getdepandingOnCartInstallments($paymentMethod);
			$currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
			if (count($dataAsPerCurrency) && isset($dataAsPerCurrency[$currentCurrencyCode])) {

				foreach ($dataAsPerCurrency[$currentCurrencyCode] as $data) {
					if (($totalAmount >= $data->from && !empty($data->to) && $totalAmount <= $data->to) || ($totalAmount >= $data->from && empty($data->to))) {
						$installmentsCount = $this->countForInstallment($data->installments, $options, $installmentsText, $totalAmount, $perMonthText);
						break;
					}
				}
			}
		}
		$installments = $installmentsCount['installments'];
		if (count($installments) == 0) {
			return false;
		} else {
			return true;
		}
	}

	public function countForInstallment($installmentsVar = null, $options, $installmentsText, $totalAmount, $perMonthText) {
		$installments = $installmentsInDropdown = array();
		foreach (explode(',', $installmentsVar) as $n) {

			if ((array_key_exists($n, $options))) {
				$installments[$n] = $n . ' ' . $installmentsText . ' ' . Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol() . round($totalAmount / $n, 2) . $perMonthText;
				$installmentsInDropdown[$n] = round($totalAmount / $n, 2);

			}
		}
		return array('installments' => $installments, 'installmentsInDropdown' => $installmentsInDropdown);
	}

	public function getdepandingOnCartInstallments($method = 'pis_cc') {
		$depandingOnCartInstallments = Mage::getStoreConfig('payment/' . $method . '/depanding_on_cart_total_values');
		$depandingOnCartInstallmentsArr = json_decode($depandingOnCartInstallments);
		$dataAsPerCurrency = array();
		foreach ($depandingOnCartInstallmentsArr as $data) {
			$dataAsPerCurrency[$data->doctv->currency][] = $data->doctv;
		}
		return $dataAsPerCurrency;
	}

	public function checkProductBasedAvailability($paymentMethod) {
		$check = TRUE;
		if (Mage::getStoreConfig('payment/' . $paymentMethod . '/splitit_per_product')) {
			$cart = Mage::getSingleton('checkout/session')->getQuote();
// get array of all items what can be display directly
			$itemsVisible = $cart->getAllVisibleItems();
			$allowedProducts = Mage::getStoreConfig('payment/' . $paymentMethod . '/splitit_product_skus');
			$allowedProducts = explode(',', $allowedProducts);
			if (Mage::getStoreConfig('payment/' . $paymentMethod . '/splitit_per_product') == 1) {
				$check = TRUE;
				foreach ($itemsVisible as $item) {
					if (!in_array($item->getProductId(), $allowedProducts)) {
						$check = FALSE;
						break;
					}
				}
			}
			if (Mage::getStoreConfig('payment/' . $paymentMethod . '/splitit_per_product') == 2) {
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

	public function orderCancelAfter(Varien_Event_Observer $observer) {
		$event = $observer->getEvent();

		$order = $observer->getEvent()->getOrder();

		$payment = $order->getPayment();
		if ($payment->getLastTransId() != "") {
			if ($payment->getMethod() == "pis_cc") {
				$storeId = Mage::app()->getStore()->getStoreId();
				$api = Mage::getSingleton("pis_payment/pisMethod")->_initApi($storeId = null);
				$sessionId = Mage::getSingleton('core/session')->getSplititSessionid();
				$installmentPlanNumber = $payment->getLastTransId();
				$cancelResponse = Mage::getModel("pis_payment/pisMethod")->cancelInstallmentPlan($api, $installmentPlanNumber);
				if (!$cancelResponse["status"]) {
					Mage::throwException(
						Mage::helper('payment')->__($cancelResponse["data"])
					);
				}

			}

			if ($payment->getMethod() == "pis_paymentform") {
				$storeId = Mage::app()->getStore()->getStoreId();
				$api = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->_initApi($storeId = null);
				$sessionId = Mage::getSingleton('core/session')->getSplititSessionid();
				$installmentPlanNumber = $payment->getLastTransId();
				$cancelResponse = Mage::getModel("pis_payment/pisPaymentFormMethod")->cancelInstallmentPlan($api, $installmentPlanNumber);
				if (!$cancelResponse["status"]) {
					Mage::throwException(
						Mage::helper('payment')->__($cancelResponse["data"])
					);
				}

			}
		}
	}

	public function validateAddress(Varien_Event_Observer $observer) {
		$_code = 'pis_paymentform';
		$response["status"] = false;
		$order = $observer->getOrder();
		$quote = $observer->getQuote();
		$payment = $order->getPayment();
		if ($payment->getMethodInstance()->getCode() != $_code) {
			return;
		}
		$billingAddress = $quote->getBillingAddress();
		if ($billingAddress->getStreet()[0] == "" || $billingAddress->getCity() == "" || $billingAddress->getPostcode() == "" || $billingAddress->getFirstname() == "" || $billingAddress->getLastname() == "" || $billingAddress->getEmail() == "" || $billingAddress->getTelephone() == "") {
			$response["errorMsg"] = "Please fill required fields.";
		} else if (strlen($billingAddress->getTelephone()) < 5 || strlen($billingAddress->getTelephone()) > 14) {

			$response["errorMsg"] = __("Splitit does not accept phone number less than 5 digits or greater than 14 digits.");
		} elseif (!$billingAddress->getCity()) {
			$response["errorMsg"] = __("Splitit does not accept empty city field.");
		} elseif (!$billingAddress->getCountry()) {
			$response["errorMsg"] = ("Splitit does not accept empty country field.");
		} elseif (!$billingAddress->getPostcode()) {
			$response["errorMsg"] = ("Splitit does not accept empty postcode field.");
		} elseif (!$billingAddress->getFirstname()) {
			$response["errorMsg"] = ("Splitit does not accept empty customer name field.");
		} elseif (strlen($billingAddress->getFirstname() . ' ' . $billingAddress->getLastname()) < 3) {
			$response["errorMsg"] = ("Splitit does not accept less than 3 characters customer name field.");
		} elseif (!filter_var($billingAddress->getEmail(), FILTER_VALIDATE_EMAIL)) {
			$response["errorMsg"] = ("Splitit does not accept invalid customer email field.");
		} else {
			$response["status"] = true;
		}

		if (!$response['status']) {
			Mage::throwException($response['errorMsg']);
		}

	}
}
