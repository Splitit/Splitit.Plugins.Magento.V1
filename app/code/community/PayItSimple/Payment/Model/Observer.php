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
			if ($this->checkProductBasedAvailability()) {
                if ($_child->getProduct() && $_child->getProduct()->getId()) {
                    if ($this->isSplititTextVisibleOnProduct($_child->getProduct()->getId())) {
                        $_block->setTemplate('payitsimple/splitprice.phtml');
                    }
                } else {
                    $_block->setTemplate('payitsimple/splitprice.phtml');
                }
			}
		}
	}

	public function paymentMethodIsActive(Varien_Event_Observer $observer) {

		$event = $observer->getEvent();
		$method = $event->getMethodInstance();
		$result = $event->getResult();

		if ($method->getCode() == "pis_paymentform") {
			$result->isAvailable = $this->checkAvailableInstallments() && $this->checkProductBasedAvailability();
		}

	}

	private function checkAvailableInstallments() {
		$totalAmount = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
		$selectInstallmentSetup = Mage::getStoreConfig('payment/pis_paymentform/select_installment_setup');
		$options = Mage::getModel('pis_payment/source_installments')->toOptionArray();

        $installmentsText = "";
        $perMonthText = "";
		// check if splitit extension is disable from admin
		$isDisabled = Mage::getStoreConfig('payment/pis_paymentform/active');
		if (!$isDisabled) {
			return false;
		}

		/*for checking when merchant first time upgrade extension that time $selectInstallmentSetup will be empty*/
		if ($selectInstallmentSetup == "" || $selectInstallmentSetup == "fixed") {
			/*Select Fixed installment setup*/

			$fixedInstallments = Mage::getStoreConfig('payment/pis_paymentform/available_installments');
			$installmentsCount = $this->countForInstallment($fixedInstallments, $options, $installmentsText, $totalAmount, $perMonthText);

		} else {
			/*Select Depanding on cart installment setup*/
			$dataAsPerCurrency = $this->getdepandingOnCartInstallments();
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

	public function getdepandingOnCartInstallments() {
		$depandingOnCartInstallments = Mage::getStoreConfig('payment/pis_paymentform/depanding_on_cart_total_values');
		$depandingOnCartInstallmentsArr = json_decode($depandingOnCartInstallments);
		$dataAsPerCurrency = array();
		foreach ($depandingOnCartInstallmentsArr as $data) {
			$dataAsPerCurrency[$data->doctv->currency][] = $data->doctv;
		}
		return $dataAsPerCurrency;
	}

	public function checkProductBasedAvailability() {
		$check = TRUE;
		if (Mage::getStoreConfig('payment/pis_paymentform/splitit_per_product')) {
			$cart = Mage::getSingleton('checkout/session')->getQuote();
			/*get array of all items what can be display directly*/
			$itemsVisible = $cart->getAllVisibleItems();
			$allowedProducts = Mage::getStoreConfig('payment/pis_paymentform/splitit_product_skus');
			$allowedProducts = explode(',', $allowedProducts);
			if (Mage::getStoreConfig('payment/pis_paymentform/splitit_per_product') == 1) {
				$check = TRUE;
				foreach ($itemsVisible as $item) {
					if (!in_array($item->getProductId(), $allowedProducts)) {
						$check = FALSE;
						break;
					}
				}
			}
			if (Mage::getStoreConfig('payment/pis_paymentform/splitit_per_product') == 2) {
				$check = FALSE;
				foreach ($itemsVisible as $item) {
					if (in_array($item->getProductId(), $allowedProducts)) {
						$check = TRUE;
						break;
					}
				}
			}
		}

		return $check;
	}

    public function isSplititTextVisibleOnProduct($productId) {
        $show = TRUE;
        if (Mage::getStoreConfig('payment/pis_paymentform/splitit_per_product') != 0) {
            $show = FALSE;
            $allowedProducts = Mage::getStoreConfig('payment/pis_paymentform/splitit_product_skus');
            $allowedProducts = explode(',', $allowedProducts);
            if (in_array($productId, $allowedProducts)) {
                $show = TRUE;
            }
        }

        return $show;
    }

	public function orderCancelAfter(Varien_Event_Observer $observer) {
		$order = $observer->getEvent()->getOrder();

		$payment = $order->getPayment();
		if ($payment->getLastTransId() != "" && $payment->getMethod() == "pis_paymentform") {
            $storeId = Mage::app()->getStore()->getStoreId();
            $api = Mage::getSingleton("pis_payment/pisPaymentFormMethod")->_initApi($storeId);
            $installmentPlanNumber = $payment->getLastTransId();
            $cancelResponse = Mage::getModel("pis_payment/pisPaymentFormMethod")->cancelInstallmentPlan($api, $installmentPlanNumber);
            if (!$cancelResponse["status"]) {
                Mage::throwException(
                    Mage::helper('payment')->__($cancelResponse["data"])
                );
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
		} else if (strlen($billingAddress->getTelephone()) < 5 || strlen($billingAddress->getTelephone()) > 10) {

			$response["errorMsg"] = __("Splitit does not accept phone number less than 5 digits or greater than 10 digits.");
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
