<?php

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

/**
 * Create 'bold_checkout_payment_booster_order' table
 */
$tableName = Bold_CheckoutPaymentBooster_Model_Order::RESOURCE;
$table = $installer->getConnection()
    ->newTable($installer->getTable($tableName))
    ->addColumn(
        'entity_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ],
        'Entity ID'
    )
    ->addColumn(
        'quote_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        [
            'unsigned' => true,
            'nullable' => false,
        ],
        'Magento Quote ID'
    )
    ->addColumn(
        'order_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        [
            'unsigned' => true,
            'nullable' => true,
        ],
        'Magento Order ID'
    )
    ->addColumn(
        'public_id',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        '255',
        [
            'nullable' => false,
        ],
        'Bold Order Public ID'
    )
    ->addIndex(
        $installer->getIdxName(
            $tableName,
            [
                'quote_id',
                'order_id',
            ]
        ),
        [
            'quote_id',
            'order_id',
        ]
    )
    ->addForeignKey(
        $installer->getFkName(
            $tableName,
            'quote_id',
            'sales/quote',
            'entity_id'
        ),
        'quote_id',
        $installer->getTable('sales/quote'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->addForeignKey(
        $installer->getFkName(
            $tableName,
            'order_id',
            'sales/order',
            'entity_id'
        ),
        'order_id',
        $installer->getTable('sales/order'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('Bold Order Data');

$installer->getConnection()->createTable($table);
$installer->endSetup();
