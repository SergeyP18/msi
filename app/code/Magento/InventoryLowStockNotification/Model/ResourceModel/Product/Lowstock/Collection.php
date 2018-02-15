<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryLowStockNotification\Model\ResourceModel\Product\Lowstock;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Inventory\Model\ResourceModel\SourceItem\Collection as SourceItemCollection;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryLowStockNotification\Setup\Operation\CreateSourceConfigurationTable;
use Magento\InventoryLowStockNotificationApi\Api\Data\SourceItemConfigurationInterface;
use Psr\Log\LoggerInterface;

class Collection extends SourceItemCollection
{
    /**
     * @var StockConfigurationInterface
     */
    private $stockConfiguration;

    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        AttributeRepositoryInterface $attributeRepository,
        StockConfigurationInterface $stockConfiguration,
        AdapterInterface $connection = null,
        AbstractDb $resource = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $connection,
            $resource
        );
        $this->attributeRepository = $attributeRepository;
        // TODO: use stock configuration from InventoryConfiguration when the logic is ready
        $this->stockConfiguration = $stockConfiguration;
    }

    /**
     * {@inheritdoc}
     */
    protected function _initSelect()
    {
        $this->addFilterToMap('inventory_source_code', 'main_table.source_code');
        $this->addFilterToMap('source_item_sku', 'main_table.sku');
        $this->addFilterToMap('product_name', 'catalog_product_entity_varchar.value');

        return parent::_initSelect();
    }

    /**
     * Join tables with product information
     *
     * @return Collection
     */
    public function joinCatalogProduct(): Collection
    {
        $productEntityTable = $this->getTable('catalog_product_entity');
        $productEavVarcharTable = $this->getTable('catalog_product_entity_varchar');
        $nameAttribute = $this->attributeRepository->get('catalog_product', 'name');

        $this->getSelect()->joinLeft(
            $productEntityTable,
            sprintf(
                'main_table.%s = %s.' . SourceItemInterface::SKU,
                SourceItemInterface::SKU,
                $productEntityTable
            ),
            []
        );

        /* Join product name */
        $joinExpression = sprintf(
            $productEavVarcharTable . '.entity_id = %s.entity_id',
            $productEntityTable
        );

        $joinExpression .= sprintf(
            ' AND ' . $productEavVarcharTable . '.attribute_id = %s',
            $nameAttribute->getAttributeId()
        );

        $this->getSelect()->joinLeft(
            $productEavVarcharTable,
            $joinExpression,
            ['value as name']
        );

        return $this;
    }

    /**
     * Join inventory configuration table
     *
     * @return Collection
     */
    private function joinInventoryConfiguration(): Collection
    {
        $sourceItemConfigurationTable = $this->getTable(
            CreateSourceConfigurationTable::TABLE_NAME_SOURCE_ITEM_CONFIGURATION
        );

        $this->getSelect()->joinLeft(
            $sourceItemConfigurationTable,
            sprintf(
                'main_table.%s = %s.%s AND main_table.%s = %s.%s',
                SourceItemInterface::SKU,
                $sourceItemConfigurationTable,
                SourceItemConfigurationInterface::SKU,
                SourceItemInterface::SOURCE_CODE,
                $sourceItemConfigurationTable,
                SourceItemConfigurationInterface::SOURCE_CODE
            ),
            []
        );

        return $this;
    }

    /**
     * Add filter by product type(s)
     *
     * @param array|string $typeFilter
     * @throws LocalizedException
     * @return Collection
     */
    public function filterByProductType($typeFilter): Collection
    {
        if (!is_string($typeFilter) && !is_array($typeFilter)) {
            throw new LocalizedException(__('The product type filter specified is incorrect.'));
        }
        $this->addFieldToFilter('type_id', $typeFilter);

        return $this;
    }

    /**
     * Add filter by product types from config - only types which have QTY parameter
     *
     * @return Collection
     */
    public function filterByIsQtyProductTypes(): Collection
    {
        $this->filterByProductType(array_keys(array_filter($this->stockConfiguration->getIsQtyTypeIds())));

        return $this;
    }

    /**
     * Add Notify Stock Qty Condition to collection
     *
     * @return Collection
     */
    public function useNotifyStockQtyFilter(): Collection
    {
        $this->joinInventoryConfiguration();

        $notifyQtyField = CreateSourceConfigurationTable::TABLE_NAME_SOURCE_ITEM_CONFIGURATION .
            '.' . SourceItemConfigurationInterface::INVENTORY_NOTIFY_QTY;

        $notifyStockExpression = $this->getConnection()->getIfNullSql(
            $notifyQtyField,
            (int)$this->stockConfiguration->getNotifyStockQty()
        );

        $this->getSelect()->where(
            SourceItemInterface::QUANTITY . ' < ?',
            $notifyStockExpression
        );
        
        return $this;
    }
}
