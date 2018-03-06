<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Plugin\Sales;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\InventorySales\Model\StockByWebsiteIdResolver;
use Magento\InventorySourceSelectionApi\Api\Data\ItemRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\SourceSelectionServiceInterface;
use Magento\InventoryShipping\Model\SourceSelection\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\InventoryCatalog\Model\GetSkusByProductIdsInterface;

/**
 * This is the best entry point for both POST and API request
 */
class CollectSourcesForShipmentItems
{
    /**
     * @var StockByWebsiteIdResolver
     */
    private $stockByWebsiteIdResolver;

    /**
     * @var ItemRequestInterfaceFactory
     */
    private $itemRequestFactory;

    /**
     * @var InventoryRequestInterfaceFactory
     */
    private $inventoryRequestFactory;

    /**
     * @var SourceSelectionServiceInterface
     */
    private $sourceSelectionService;

    /**
     * @var GetDefaultSourceSelectionAlgorithmCodeInterface
     */
    private $getDefaultSourceSelectionAlgorithmCode;

    /**
     * @var GetSkusByProductIdsInterface
     */
    private $getSkusByProductIds;

    /**
     * @param StockByWebsiteIdResolver $stockByWebsiteIdResolver
     * @param ItemRequestInterfaceFactory $itemRequestFactory
     * @param InventoryRequestInterfaceFactory $inventoryRequestFactory
     * @param SourceSelectionServiceInterface $sourceSelectionService
     * @param GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     */
    public function __construct(
        StockByWebsiteIdResolver $stockByWebsiteIdResolver,
        ItemRequestInterfaceFactory $itemRequestFactory,
        InventoryRequestInterfaceFactory $inventoryRequestFactory,
        SourceSelectionServiceInterface $sourceSelectionService,
        GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode,
        GetSkusByProductIdsInterface $getSkusByProductIds
    ) {
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->itemRequestFactory = $itemRequestFactory;
        $this->inventoryRequestFactory = $inventoryRequestFactory;
        $this->sourceSelectionService = $sourceSelectionService;
        $this->getDefaultSourceSelectionAlgorithmCode = $getDefaultSourceSelectionAlgorithmCode;
        $this->getSkusByProductIds = $getSkusByProductIds;
    }

    /**
     * @param ShipmentFactory $subject
     * @param callable $proceed
     * @param Order $order
     * @param array $items
     * @param null $tracks
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @return
     */
    public function aroundCreate(
        ShipmentFactory $subject,
        callable $proceed,
        Order $order,
        array $items = [],
        $tracks = null
    ) {
        //TODO: process data from request
        $shipment = $proceed($order, $items, $tracks);
        if (empty($items)) {
            return $shipment;
        }
        //TODO: !!! Temporary decision. Need to implement logic with UI part (get data from items array)
        $websiteId = $order->getStore()->getWebsiteId();
        $stockId = (int)$this->stockByWebsiteIdResolver->get((int)$websiteId)->getStockId();
        /** @var \Magento\Sales\Api\Data\ShipmentItemInterface $item */
        foreach ($shipment->getItems() as $item) {
            //TODO: I didn't test, but I think it can be broken with configurable products
            $sku = $item->getSku() ?: $this->getSkusByProductIds->execute(
                [$item->getProductId()]
            )[$item->getProductId()];
            $requestItem = $this->itemRequestFactory->create([
                    'sku' => $sku,
                    'qty' => $item->getQty()
            ]);
            $inventoryRequest = $this->inventoryRequestFactory->create([
                'stockId' => $stockId,
                'items' => [$requestItem]
            ]);
            $sourceSelectionResult = $this->sourceSelectionService->execute(
                $inventoryRequest,
                $this->getDefaultSourceSelectionAlgorithmCode->execute()
            );
            $shippingItemSources = [];
            foreach ($sourceSelectionResult->getSourceSelectionItems() as $data) {
                //TODO: need to implement it as Extension Attribute
                $shippingItemSources[] = [
                    'sourceCode' => $data->getSourceCode(),
                    'qtyToDeduct' => $data->getQtyToDeduct()
                ];
            }

            $item->setSources($shippingItemSources);
        }

        return $shipment;
    }
}
