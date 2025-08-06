<?php

namespace Wallee\Payment\Setup\Patch\Schema;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

class RemoveLock implements SchemaPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $schemaSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->schemaSetup = $moduleDataSetup;
    }

    /**
     * Drops wallee_lock column from sales_order table
     * @return $this|RemoveLock
     */
    public function apply(): self
    {
        $setup = $this->schemaSetup;
        $setup->getConnection()->startSetup();

        $table = $setup->getTable('sales_order');

        $setup->getConnection()->dropColumn($table, 'wallee_lock');
        $setup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }
}
