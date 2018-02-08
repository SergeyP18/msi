<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Integration\GetStockItemData;

use Magento\Inventory\Model\GetStockItemDataInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ManageConfigTest extends TestCase
{
    /**
     * @var GetStockItemDataInterface
     */
    private $getStockItemData;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->getStockItemData = Bootstrap::getObjectManager()->get(GetStockItemDataInterface::class);
    }

    /**
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/products.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/sources.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stocks.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/source_items.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryIndexer/Test/_files/reindex_inventory.php
     * @magentoConfigFixture default_store cataloginventory/item_options/manage_stock 0
     *
     * @param int $stockId
     * @param array $expectedQty
     * @param array $expectedIsSalable
     *
     * @return void
     * @dataProvider executeWithManageStockFalseDataProvider
     */
    public function testExecuteWithManageStockFalse(int $stockId, array $expectedQty, array $expectedIsSalable)
    {
        foreach (['SKU-1', 'SKU-2', 'SKU-3'] as $key => $sku) {
            $stockItemData = $this->getStockItemData->execute($sku, $stockId);
            self::assertEquals($expectedQty[$key], $stockItemData[IndexStructure::QUANTITY] ?? null);
            self::assertEquals($expectedIsSalable[$key], $stockItemData[IndexStructure::IS_SALABLE] ?? null);
        }
    }

    /**
     * @return array
     */
    public function executeWithManageStockFalseDataProvider(): array
    {
        return [
            ['10', [8.5, null, 0], [1, null, 1]],
            ['20', [null, 5, null], [null, 1, null]],
            ['30', [8.5, 5, 0], [1, 1, 1]],
        ];
    }
}
