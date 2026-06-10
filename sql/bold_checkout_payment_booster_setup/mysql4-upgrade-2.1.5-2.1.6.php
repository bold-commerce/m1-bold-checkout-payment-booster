<?php

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$orderTableName = $installer->getTable(Bold_CheckoutPaymentBooster_Model_Order::RESOURCE);
$connection = $installer->getConnection();

$indexName = 'UNQ_BOLD_CHECKOUT_PAYMENT_BOOSTER_ORDER_PUBLIC_ID';
$indexes = $connection->getIndexList($orderTableName);

if (!isset($indexes[strtoupper($indexName)])) {
    $installer->run(
        "ALTER TABLE `{$orderTableName}` ADD UNIQUE INDEX `{$indexName}` (`public_id`)"
    );
}

$installer->endSetup();
