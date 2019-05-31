<?php

class PayItSimple_Payment_Helper_Data extends Mage_Core_Helper_Abstract {
	public function getTranslation($paymentMethodCode) {

		$storeId = Mage::app()->getStore()->getStoreId();
		$lvals = Mage::getStoreConfig('payment/' . $paymentMethodCode . '/translate_languages', $storeId);
		return $translatedJsonVal = json_decode($lvals, true);
	}

	public function getDefaultLanguage() {
		return "en_US";
	}

	public function getInstallmentPriceText($code) {
		$storeId = Mage::app()->getStore()->getStoreId();
		$storelang = Mage::getStoreConfig('general/locale/code', $storeId);
		$defaultLang = $this->getDefaultLanguage();
		$translation = $this->getTranslation($code);
		$logoSrc = Mage::getStoreConfig('payment/' . $code . '/logo_src');
		$logoLink = Mage::getStoreConfig('payment/' . $code . '/logo_background_href');
		$text = "";
		if (Mage::getStoreConfig('payment/' . $code . '/enable_installment_price') == 1 && Mage::getStoreConfig('payment/' . $code . '/active') == 1) {
			if (!empty($translation) && isset($translation[$storelang]["ecomm_no_interest"]["translatedData"]) && $translation[$storelang]["ecomm_no_interest"]["translatedData"] != "") {
				$text = $translation[$storelang]["ecomm_no_interest"]["translatedData"];
			} else if (!empty($translation) && isset($translation[$defaultLang]["ecomm_no_interest"]["translatedData"])) {
				$text = $translation[$defaultLang]["ecomm_no_interest"]["translatedData"];

			}
		}
		if ($logoLink && $logoSrc) {
			$replace = "<a href='" . $logoLink . "' target='_blank'><img class='logoWidthSrc' src='" . $logoSrc . "' alt='SPLITIT'/></a>";
			$text = str_replace('SPLITIT', $replace, $text);
		}
		return $text;
	}

	public function getPaymentInfoTitle($code) {
		$storeId = Mage::app()->getStore()->getStoreId();
		$storelang = Mage::getStoreConfig('general/locale/code', $storeId);
		$defaultLang = $this->getDefaultLanguage();
		$translation = $this->getTranslation($code);

		$text = "";
		if (Mage::getStoreConfig('payment/' . $code . '/faq_link_enabled') == 1 && Mage::getStoreConfig('payment/' . $code . '/active') == 1) {
			if (!empty($translation) && isset($translation[$storelang]["ecomm_tell_me_more"]["translatedData"]) && $translation[$storelang]["ecomm_tell_me_more"]["translatedData"] != "") {
				$text = $translation[$storelang]["ecomm_tell_me_more"]["translatedData"];
			} else if (!empty($translation) && isset($translation[$defaultLang]["ecomm_tell_me_more"]["translatedData"])) {
				$text = $translation[$defaultLang]["ecomm_tell_me_more"]["translatedData"];

			}
		}
		return $text;
	}

	public function getCreditCardFormTranslation($key) {
		$storeId = Mage::app()->getStore()->getStoreId();
		$storelang = Mage::getStoreConfig('general/locale/code', $storeId);
		$defaultLang = $this->getDefaultLanguage();
		$translation = $this->getTranslation('pis_cc');
		$text = "";
		if (Mage::getStoreConfig('payment/pis_cc/active') == 1) {
			if (!empty($translation) && isset($translation[$storelang][$key]["translatedData"]) && $translation[$storelang][$key]["translatedData"] != "") {
				$text = $translation[$storelang][$key]["translatedData"];
			} else if (!empty($translation) && isset($translation[$defaultLang][$key]["translatedData"])) {
				$text = $translation[$defaultLang][$key]["translatedData"];

			}
		}
		return $text;
	}

	public function getCreditCardFormTranslationPaymentForm($key) {
		$storeId = Mage::app()->getStore()->getStoreId();
		$storelang = Mage::getStoreConfig('general/locale/code', $storeId);
		$defaultLang = $this->getDefaultLanguage();
		$translation = $this->getTranslation('pis_paymentform');
		$text = "";
		if (Mage::getStoreConfig('payment/pis_paymentform/active') == 1) {
			if (!empty($translation) && isset($translation[$storelang][$key]["translatedData"]) && $translation[$storelang][$key]["translatedData"] != "") {
				$text = $translation[$storelang][$key]["translatedData"];
			} else if (!empty($translation) && isset($translation[$defaultLang][$key]["translatedData"])) {
				$text = $translation[$defaultLang][$key]["translatedData"];

			}
		}
		return $text;
	}

	public function getCultureName() {
		$storeId = Mage::app()->getStore()->getStoreId();
		$storelang = Mage::getStoreConfig('general/locale/code', $storeId);
		$splititSupportedCultures = $this->getSplititSupportedCultures('PisMethod');
		if (count($splititSupportedCultures) && in_array(str_replace('_', '-', $storelang), $splititSupportedCultures)) {
			return str_replace('_', '-', $storelang);
		} else {
			return Mage::getStoreConfig('payment/pis_cc/splitit_fallback_language');
		}
	}

	public function getCultureNameForPaymentForm() {
		$storeId = Mage::app()->getStore()->getStoreId();
		$storelang = Mage::getStoreConfig('general/locale/code', $storeId);
		$splititSupportedCultures = $this->getSplititSupportedCultures('PisPaymentFormMethod');
		if (count($splititSupportedCultures) && in_array(str_replace('_', '-', $storelang), $splititSupportedCultures)) {
			return str_replace('_', '-', $storelang);
		} else {
			return Mage::getStoreConfig('payment/pis_paymentform/splitit_fallback_language');
		}
	}

	public function getSplititSupportedCultures($model) {
		$apiUrl = Mage::getSingleton('pis_payment/' . $model)->getApiUrl();
		$getSplititSupportedCultures = Mage::getSingleton('pis_payment/api')->getSplititSupportedCultures($apiUrl . "api/Infrastructure/SupportedCultures");
		$decodedResult = Mage::helper('core')->jsonDecode($getSplititSupportedCultures);
		if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1 && count($decodedResult["SupportedCultures"])) {
			return $decodedResult["SupportedCultures"];
		}

		return array();
	}

	public function getResourcesFromSplitit() {
		/*$storeId = Mage::app()->getStore()->getStoreId();
			$storelang = Mage::getStoreConfig('general/locale/code', $storeId);
		*/
		$language = $this->getCultureName();
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
		return $finalResult;
	}

	public function getPaymentMode() {
		return Mage::getStoreConfig('payment/pis_cc/payment_mode');
	}

	public function getPaymentAction() {
		return Mage::getStoreConfig('payment/pis_paymentform/payment_action');
	}

}