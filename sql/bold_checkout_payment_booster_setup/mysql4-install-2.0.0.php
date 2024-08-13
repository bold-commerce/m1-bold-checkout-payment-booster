<?php

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$tableName = $installer->getTable(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);

$sql = <<<SQL
CREATE TABLE `{$tableName}` (
    `entity_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Entity ID',
    `order_id` INT UNSIGNED COMMENT 'Magento Order ID',
    `public_id` VARCHAR(255) NOT NULL COMMENT 'Bold Order Public ID',
    `is_delayed_capture` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is Delayed Capture',
    PRIMARY KEY (`entity_id`),
    INDEX `IDX_BOLD_CHECKOUT_PAYMENT_BOOSTER_ORDER_ORDER_ID` (`order_id`),
    CONSTRAINT FOREIGN KEY (`order_id`) REFERENCES `{$installer->getTable('sales/order')}` (`entity_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Bold Order Data';
SQL;

$installer->run($sql);

$installer->endSetup();
