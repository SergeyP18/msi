<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Model\SourceDeduction;

use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryShipping\Model\GetSourceItemBySourceCodeAndSku;
use Magento\InventoryShipping\Model\SourceDeduction\Request\SourceDeductionRequestInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * @inheritdoc
 */
class SourceDeductionService implements SourceDeductionServiceInterface
{
    /**
     * @var SourceItemsSaveInterface
     */
    private $sourceItemsSave;

    /**
     * @var GetSourceItemBySourceCodeAndSku
     */
    private $getSourceItemBySourceCodeAndSku;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * @var StockResolverInterface
     */
    private $stockResolver;

    /**
     * @var ItemToSellInterfaceFactory
     */
    private $itemsToSellFactory;

    /**
     * @var PlaceReservationsForSalesEventInterface
     */
    private $placeReservationsForSalesEvent;

    /**
     * @param SourceItemsSaveInterface $sourceItemsSave
     * @param GetSourceItemBySourceCodeAndSku $getSourceItemBySourceCodeAndSku
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     * @param StockResolverInterface $stockResolver
     * @param ItemToSellInterfaceFactory $itemsToSellFactory
     * @param PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
     * @internal param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     */
    public function __construct(
        SourceItemsSaveInterface $sourceItemsSave,
        GetSourceItemBySourceCodeAndSku $getSourceItemBySourceCodeAndSku,
        GetStockItemConfigurationInterface $getStockItemConfiguration,
        StockResolverInterface $stockResolver,
        ItemToSellInterfaceFactory $itemsToSellFactory,
        PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
    ) {
        $this->sourceItemsSave = $sourceItemsSave;
        $this->getSourceItemBySourceCodeAndSku = $getSourceItemBySourceCodeAndSku;
        $this->getStockItemConfiguration = $getStockItemConfiguration;
        $this->stockResolver = $stockResolver;
        $this->itemsToSellFactory = $itemsToSellFactory;
        $this->placeReservationsForSalesEvent = $placeReservationsForSalesEvent;
    }

    /**
     * @inheritdoc
     */
    public function execute(SourceDeductionRequestInterface $sourceDeductionRequest): void
    {
        $sourceItemToSave = [];
        $sourceCode = $sourceDeductionRequest->getSourceCode();
        $salesChannel = $sourceDeductionRequest->getSalesChannel();

        $stockId = (int)$this->stockResolver->get(
            $salesChannel->getType(),
            $salesChannel->getCode()
        )->getStockId();
        $itemsToSell = [];
        foreach ($sourceDeductionRequest->getItems() as $item) {
            $itemSku = $item->getSku();
            $qty = $item->getQty();
            $stockItemConfiguration = $this->getStockItemConfiguration->execute(
                $itemSku,
                $stockId
            );

            if ($stockItemConfiguration === null || !$stockItemConfiguration->isManageStock()) {
                //Product not assigned to Given Stock or we No need to Manage Stock
                continue;
            }

            $sourceItem = $this->getSourceItemBySourceCodeAndSku->execute($sourceCode, $itemSku);
            if (null !== $sourceItem) {
                if (($sourceItem->getQuantity() - $qty) >= 0) {
                    $sourceItem->setQuantity($sourceItem->getQuantity() - $qty);
                    $sourceItemToSave[] = $sourceItem;
                    $itemsToSell[] = $this->itemsToSellFactory->create([
                        'sku' => $itemSku,
                        'qty' => (float)$qty
                    ]);
                } else {
                    throw new LocalizedException(
                        __('Not all of your products are available in the requested quantity.')
                    );
                }
            }
        }
        $this->sourceItemsSave->execute($sourceItemToSave);

        $salesEvent = $sourceDeductionRequest->getSalesEvent();

        $this->placeReservationsForSalesEvent->execute($itemsToSell, $salesChannel, $salesEvent);
    }
}
