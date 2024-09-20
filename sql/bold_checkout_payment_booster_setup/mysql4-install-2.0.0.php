<?php

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$orderTableName = $installer->getTable(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
$quoteTableName = $installer->getTable(Bold_CheckoutPaymentBooster_Model_Quote::RESOURCE);

$createOrderTableSql = <<<SQL
CREATE TABLE `{$orderTableName}` (
    `entity_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Entity ID',
    `order_id` INT UNSIGNED COMMENT 'Magento Order ID',
    `public_id` VARCHAR(255) NOT NULL COMMENT 'Bold Order Public ID',
    `is_platform_capture` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is Capture Init with Invoicing',
    `is_platform_refund` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is Refund Init with Credit Memo',
    `is_platform_cancel` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is Cancel Init with Order Cancel',
    `is_platform_void` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is Payment Void Init with Order Void',
    PRIMARY KEY (`entity_id`),
    INDEX `IDX_BOLD_CHECKOUT_PAYMENT_BOOSTER_ORDER_ORDER_ID` (`order_id`),
    CONSTRAINT FOREIGN KEY (`order_id`) REFERENCES `{$installer->getTable('sales/order')}` (`entity_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Bold Order Data';
SQL;

$createQuoteTableSql = <<<SQL
CREATE TABLE `{$quoteTableName}` (
    `entity_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Entity ID',
    `quote_id` INT UNSIGNED COMMENT 'Magento Quote ID',
    `public_id` VARCHAR(255) NOT NULL COMMENT 'Bold Order Public ID',
    PRIMARY KEY (`entity_id`),
    INDEX `IDX_BOLD_CHECKOUT_PAYMENT_BOOSTER_ORDER_ORDER_ID` (`quote_id`),
    CONSTRAINT FOREIGN KEY (`quote_id`) REFERENCES `{$installer->getTable('sales/quote')}` (`entity_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Bold Quote Data';
SQL;

$installer->run($createOrderTableSql);
$installer->run($createQuoteTableSql);

$installer->endSetup();
