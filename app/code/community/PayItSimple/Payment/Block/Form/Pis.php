<?php

class PayItSimple_Payment_Block_Form_Pis extends Mage_Payment_Block_Form_Cc {
	protected function _construct() {
		parent::_construct();
		$this->setTemplate('payitsimple/form/pis.phtml');
	}

	public function getAvailableInstallments() {
		$method = $this->getMethod();
		$installments = array();
		$totalAmount = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
		$selectInstallmentSetup = Mage::getStoreConfig('payment/pis_cc/select_installment_setup');
		$options = Mage::getModel('pis_payment/source_installments')->toOptionArray();
		$installmentsText = Mage::helper('pis_payment')->getCreditCardFormTranslation('pd_installments');
		$perMonthText = Mage::helper('pis_payment')->getCreditCardFormTranslation('pd_per_month');
		$installmentsCount = array();

		$depandOnCart = 0;
		if ($selectInstallmentSetup == "" || $selectInstallmentSetup == "fixed") {
			// Select Fixed installment setup

			$fixedInstallments = Mage::getStoreConfig('payment/pis_cc/available_installments');
			$installmentsCount = $this->countForInstallment($fixedInstallments, $options, $installmentsText, $totalAmount, $perMonthText);

		} else {
			// Select Depanding on cart installment setup
			$depandOnCart = 1;
			$dataAsPerCurrency = $this->getdepandingOnCartInstallments();
			$currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
			if (count($dataAsPerCurrency) && isset($dataAsPerCurrency[$currentCurrencyCode])) {

				foreach ($dataAsPerCurrency[$currentCurrencyCode] as $data) {
					if (($totalAmount >= $data->from && !empty($data->to) && $totalAmount <= $data->to)) {
						$installmentsCount = $this->countForInstallment($data->installments, $options, $installmentsText, $totalAmount, $perMonthText);
						break;
					}
					if ($totalAmount >= $data->from && empty($data->to)) {
						$installmentsCount = $this->countForInstallment($data->installments, $options, $installmentsText, $totalAmount, $perMonthText);
						break;
					}
				}
			}
		}
		$installments = $installmentsCount['installments'];
		if (count($installments) == 0) {
			$installments[] = "Installments are not available.";
		}
		// set how much installments to be show in checkout page dropdown
		Mage::getSingleton('core/session')->setInstallmentsInDropdown($installmentsCount['installmentsInDropdown']);

		return $installments;
	}

	public function getdepandingOnCartInstallments() {
		$depandingOnCartInstallments = Mage::getStoreConfig('payment/pis_cc/depanding_on_cart_total_values');
		$depandingOnCartInstallmentsArr = json_decode($depandingOnCartInstallments);
		$dataAsPerCurrency = array();
		foreach ($depandingOnCartInstallmentsArr as $data) {
			$dataAsPerCurrency[$data->doctv->currency][] = $data->doctv;
		}
		return $dataAsPerCurrency;
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

	public function getMethodLabelAfterHtml() {
		$markFaq = Mage::getConfig()->getBlockClassName('core/template');
		$markFaq = new $markFaq;
		$markFaq->setTemplate('payitsimple/form/method_faq.phtml')
			->setPaymentInfoEnabled($this->getMethod()->getConfigData('faq_link_enabled'))
			->setPaymentInfoUrl($this->getMethod()->getConfigData('faq_link_title_url'))
			->setPaymentInfoTitle($this->getMethod()->getConfigData('faq_link_title'));
		return $markFaq->toHtml();
	}
}
