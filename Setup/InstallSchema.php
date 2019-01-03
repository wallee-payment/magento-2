<?php
/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace Wallee\Payment\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Installs the database schema.
 */
class InstallSchema implements InstallSchemaInterface
{

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $this->updateQuoteTable($installer);
        $this->updateOrderTable($installer);
        $this->updateQuotePaymentTable($installer);
        $this->updateInvoiceTable($installer);
        $this->updateCreditmemoTable($installer);
        $this->createTransactionInfoTable($installer);
        $this->createPaymentMethodConfigurationTable($installer);
        $this->createRefundJobTable($installer);
        $this->createTokenInfoTable($installer);

        $installer->endSetup();
    }

    private function updateQuoteTable(SchemaSetupInterface $installer)
    {
        $installer->getConnection()->addColumn($installer->getTable('quote'), 'wallee_space_id',
            [
                'type' => Table::TYPE_BIGINT,
                'unsigned' => true,
                'comment' => 'wallee Payment Space Id'
            ]);

        $installer->getConnection()->addColumn($installer->getTable('quote'), 'wallee_transaction_id',
            [
                'type' => Table::TYPE_BIGINT,
                'unsigned' => true,
                'comment' => 'wallee Payment Transaction Id'
            ]);

        $installer->getConnection()->addIndex($installer->getTable('quote'),
            $installer->getIdxName('quote',
                [
                    'wallee_space_id',
                    'wallee_transaction_id'
                ]), [
                'wallee_space_id',
                'wallee_transaction_id'
            ]);
    }

    private function updateOrderTable(SchemaSetupInterface $installer)
    {
        $installer->getConnection()->addColumn($installer->getTable('sales_order'), 'wallee_space_id',
            [
                'type' => Table::TYPE_BIGINT,
                'unsigned' => true,
                'comment' => 'wallee Payment Space Id'
            ]);

        $installer->getConnection()->addColumn($installer->getTable('sales_order'),
            'wallee_transaction_id',
            [
                'type' => Table::TYPE_BIGINT,
                'unsigned' => true,
                'comment' => 'wallee Payment Transaction Id'
            ]);

        $installer->getConnection()->addColumn($installer->getTable('sales_order'), 'wallee_authorized',
            [
                'type' => Table::TYPE_BOOLEAN,
                'default' => false,
                'comment' => 'wallee Payment Authorized'
            ]);

        $installer->getConnection()->addColumn($installer->getTable('sales_order'),
            'wallee_security_token',
            [
                'type' => Table::TYPE_TEXT,
                'length' => 32,
                'comment' => 'wallee Payment Security Token'
            ]);

        $installer->getConnection()->addColumn($installer->getTable('sales_order'), 'wallee_lock',
            [
                'type' => Table::TYPE_TIMESTAMP,
                'nullable' => true,
                'comment' => 'wallee Payment Lock'
            ]);

        $installer->getConnection()->addIndex($installer->getTable('sales_order'),
            $installer->getIdxName('sales_order',
                [
                    'wallee_space_id',
                    'wallee_transaction_id'
                ]), [
                'wallee_space_id',
                'wallee_transaction_id'
            ]);
    }

    private function updateQuotePaymentTable(SchemaSetupInterface $installer)
    {
        $installer->getConnection()->addColumn($installer->getTable('quote_payment'), 'wallee_token',
            [
                'type' => Table::TYPE_INTEGER,
                'length' => 10,
                'unsigned' => true,
                'comment' => 'wallee Payment Token'
            ]);
    }

    private function updateInvoiceTable(SchemaSetupInterface $installer)
    {
        $installer->getConnection()->addColumn($installer->getTable('sales_invoice'),
            'wallee_capture_pending',
            [
                'type' => Table::TYPE_BOOLEAN,
                'default' => false,
                'comment' => 'wallee Payment Capture Pending'
            ]);
    }

    private function updateCreditmemoTable(SchemaSetupInterface $installer)
    {
        $installer->getConnection()->addColumn($installer->getTable('sales_creditmemo'),
            'wallee_external_id',
            [
                'type' => Table::TYPE_TEXT,
                'length' => 100,
                'nullable' => true,
                'comment' => 'wallee Payment External Id'
            ]);
    }

    private function createTransactionInfoTable(SchemaSetupInterface $installer)
    {
        if (! $installer->getConnection()->isTableExists(
            $installer->getTable('wallee_payment_transaction_info'))) {}
        {
            $table = $installer->getConnection()
                ->newTable($installer->getTable('wallee_payment_transaction_info'))
                ->addColumn('entity_id', Table::TYPE_INTEGER, null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true
                ], 'Entity ID')
                ->addColumn('transaction_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Transaction ID')
                ->addColumn('state', Table::TYPE_TEXT, null, [
                'nullable' => false
            ], 'State')
                ->addColumn('space_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Space ID')
                ->addColumn('space_view_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Space View ID')
                ->addColumn('language', Table::TYPE_TEXT, null, [
                'nullable' => false
            ], 'Language')
                ->addColumn('currency', Table::TYPE_TEXT, null, [
                'nullable' => false
            ], 'Currency')
                ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
                'default' => Table::TIMESTAMP_INIT
            ], 'Created At')
                ->addColumn('authorization_amount', Table::TYPE_NUMERIC, '19,8', [
                'nullable' => false
            ], 'Authorization Amount')
                ->addColumn('image', Table::TYPE_TEXT, 512, [
                'nullable' => true
            ], 'Image')
                ->addColumn('labels', Table::TYPE_TEXT, Table::MAX_TEXT_SIZE, [], 'Labels')
                ->addColumn('failure_reason', Table::TYPE_TEXT, Table::MAX_TEXT_SIZE, [
                'nullable' => true
            ], 'Failure Reason')
                ->addColumn('payment_method_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => true
            ], 'Payment Method ID')
                ->addColumn('connector_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => true
            ], 'Connector ID')
                ->addColumn('order_id', Table::TYPE_INTEGER, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Order ID')
                ->addIndex(
                $installer->getIdxName('wallee_payment_transaction_info',
                    [
                        'space_id',
                        'transaction_id'
                    ], AdapterInterface::INDEX_TYPE_UNIQUE), [
                    'space_id',
                    'transaction_id'
                ], [
                    'type' => AdapterInterface::INDEX_TYPE_UNIQUE
                ])
                ->addIndex(
                $installer->getIdxName('wallee_payment_transaction_info', [
                    'order_id'
                ], AdapterInterface::INDEX_TYPE_UNIQUE), [
                    'order_id'
                ], [
                    'type' => AdapterInterface::INDEX_TYPE_UNIQUE
                ])
                ->setComment('wallee Payment Transaction Info');
            $installer->getConnection()->createTable($table);
        }
    }

    private function createPaymentMethodConfigurationTable(SchemaSetupInterface $installer)
    {
        if (! $installer->getConnection()->isTableExists(
            $installer->getTable('wallee_payment_method_configuration'))) {}
        {
            $table = $installer->getConnection()
                ->newTable($installer->getTable('wallee_payment_method_configuration'))
                ->addColumn('entity_id', Table::TYPE_INTEGER, null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true
                ], 'Entity ID')
                ->addColumn('state', Table::TYPE_INTEGER, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'State')
                ->addColumn('space_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Space ID')
                ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
                'default' => Table::TIMESTAMP_INIT
            ], 'Created At')
                ->addColumn('updated_at', Table::TYPE_TIMESTAMP, null, [
                'default' => Table::TIMESTAMP_UPDATE
            ], 'Updated At')
                ->addColumn('configuration_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Configuration ID')
                ->addColumn('configuration_name', Table::TYPE_TEXT, 150, [
                'nullable' => false
            ], 'Configuration Name')
                ->addColumn('title', Table::TYPE_TEXT, Table::MAX_TEXT_SIZE, [
                'nullable' => true
            ], 'Title')
                ->addColumn('description', Table::TYPE_TEXT, Table::MAX_TEXT_SIZE, [
                'nullable' => true
            ], 'Description')
                ->addColumn('image', Table::TYPE_TEXT, 512, [
                'nullable' => true
            ], 'Image')
                ->addColumn('sort_order', Table::TYPE_INTEGER, null, [
                'nullable' => false
            ], 'Sort Order')
                ->addIndex(
                $installer->getIdxName('wallee_payment_method_configuration', [
                    'space_id'
                ]), [
                    'space_id'
                ])
                ->addIndex(
                $installer->getIdxName('wallee_payment_method_configuration', [
                    'configuration_id'
                ]), [
                    'configuration_id'
                ])
                ->addIndex(
                $installer->getIdxName('wallee_payment_method_configuration',
                    [
                        'space_id',
                        'configuration_id'
                    ], AdapterInterface::INDEX_TYPE_UNIQUE), [
                    'space_id',
                    'configuration_id'
                ], [
                    'type' => AdapterInterface::INDEX_TYPE_UNIQUE
                ])
                ->setComment('wallee Payment Method Configuration');
            $installer->getConnection()->createTable($table);
        }
    }

    private function createRefundJobTable(SchemaSetupInterface $installer)
    {
        if (! $installer->getConnection()->isTableExists(
            $installer->getTable('wallee_payment_refund_job'))) {}
        {
            $table = $installer->getConnection()
                ->newTable($installer->getTable('wallee_payment_refund_job'))
                ->addColumn('entity_id', Table::TYPE_INTEGER, null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true
                ], 'Entity ID')
                ->addColumn('order_id', Table::TYPE_INTEGER, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Order Id')
                ->addColumn('invoice_id', Table::TYPE_INTEGER, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Invoice Id')
                ->addColumn('space_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Space ID')
                ->addColumn('external_id', Table::TYPE_TEXT, 100, [
                'nullable' => false
            ], 'External ID')
                ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
                'default' => Table::TIMESTAMP_INIT
            ], 'Created At')
                ->addColumn('refund', Table::TYPE_TEXT, Table::MAX_TEXT_SIZE, [
                'nullable' => true
            ], 'Description')
                ->addIndex($installer->getIdxName('wallee_payment_refund_job', [
                'space_id'
            ]), [
                'space_id'
            ])
                ->addIndex(
                $installer->getIdxName('wallee_payment_refund_job', [
                    'order_id'
                ], AdapterInterface::INDEX_TYPE_UNIQUE), [
                    'order_id'
                ], [
                    'type' => AdapterInterface::INDEX_TYPE_UNIQUE
                ])
                ->setComment('wallee Payment Refund Job');
            $installer->getConnection()->createTable($table);
        }
    }

    private function createTokenInfoTable(SchemaSetupInterface $installer)
    {
        if (! $installer->getConnection()->isTableExists(
            $installer->getTable('wallee_payment_token_info'))) {}
        {
            $table = $installer->getConnection()
                ->newTable($installer->getTable('wallee_payment_token_info'))
                ->addColumn('entity_id', Table::TYPE_INTEGER, null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true
                ], 'Entity ID')
                ->addColumn('token_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Token Id')
                ->addColumn('state', Table::TYPE_TEXT, null, [
                'nullable' => false
            ], 'State')
                ->addColumn('space_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Space ID')
                ->addColumn('name', Table::TYPE_TEXT, null, [
                'nullable' => false
            ], 'Name')
                ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
                'default' => Table::TIMESTAMP_INIT
            ], 'Created At')
                ->addColumn('customer_id', Table::TYPE_INTEGER, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Customer ID')
                ->addColumn('payment_method_id', Table::TYPE_INTEGER, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Payment Method ID')
                ->addColumn('connector_id', Table::TYPE_BIGINT, null, [
                'unsigned' => true,
                'nullable' => false
            ], 'Connector ID')
                ->addIndex($installer->getIdxName('wallee_payment_token_info', [
                'customer_id'
            ]), [
                'customer_id'
            ])
                ->addIndex(
                $installer->getIdxName('wallee_payment_token_info', [
                    'payment_method_id'
                ]), [
                    'payment_method_id'
                ])
                ->addIndex($installer->getIdxName('wallee_payment_token_info', [
                'connector_id'
            ]), [
                'connector_id'
            ])
                ->addIndex(
                $installer->getIdxName('wallee_payment_token_info', [
                    'space_id',
                    'token_id'
                ], AdapterInterface::INDEX_TYPE_UNIQUE), [
                    'space_id',
                    'token_id'
                ], [
                    'type' => AdapterInterface::INDEX_TYPE_UNIQUE
                ])
                ->setComment('wallee Payment Token Info');
            $installer->getConnection()->createTable($table);
        }
    }
}