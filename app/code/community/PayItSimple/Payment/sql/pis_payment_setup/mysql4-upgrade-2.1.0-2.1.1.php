<?php

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$conn = Mage::getSingleton('core/resource')->getConnection('core_write');
$prefix = Mage::getConfig()->getTablePrefix();
$conn->query('DELETE FROM `' . $prefix . 'core_config_data` WHERE path like  "%/pis_cc/%"');

$installer->endSetup();