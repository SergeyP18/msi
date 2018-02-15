<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryLowStockNotification\Block\Adminhtml\Product\Lowstock\Grid\Options;

use Magento\Framework\Option\ArrayInterface;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

/**
 * Source model for inventory sources list
 */
class SourceCodeOption implements ArrayInterface
{
    /**
     * @var SourceRepositoryInterface
     */
    protected $sourceRepository;

    /**
     * @param SourceRepositoryInterface $sourceRepository
     */
    public function __construct(
        SourceRepositoryInterface $sourceRepository
    ) {
        $this->sourceRepository = $sourceRepository;
    }

    /**
     * Return array of available sources
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $optionArray = [];
        $sourcesSearchResult = $this->sourceRepository->getList();
        $sourcesList = $sourcesSearchResult->getItems();

        /** @var SourceInterface $source */
        foreach ($sourcesList as $source) {
            $optionArray[] = ['value' => $source->getSourceCode(), 'label' => $source->getSourceCode()];
        }

        return $optionArray;
    }
}
