<?php
 
$installer = $this;
/* @var $installer Mage_Paypal_Model_Mysql4_Setup */

$installer->startSetup();

$installer->run("
CREATE TABLE IF NOT EXISTS `{$this->getTable('splitit_hosted_solution')}` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `installment_plan_number` varchar(100) DEFAULT NULL COMMENT 'Txn Id',
  `quote_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'quote Id',
  `order_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'order Id',
  `order_increment_id` varchar(50) DEFAULT NULL COMMENT 'order increment Id',
  `quote_item_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'quote item count',
  `customer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'customer Id if logged in user',
  `base_grand_total` Decimal (14,2),
  `order_created` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'check if order created',
  `additional_data` text COMMENT 'json data which passes to splitit when installment plan init request',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");



$installer->endSetup();