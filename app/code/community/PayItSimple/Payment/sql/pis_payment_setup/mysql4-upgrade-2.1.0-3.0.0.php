<?php

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();
$installer->setConfigData('payment/pis_cc/active', '0');
$installer->endSetup();
Mage::getConfig()->saveConfig('payment/pis_cc/active', '0');