<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventorySourceSelection\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventorySourceSelectionApi\Api\Data\AddressRequestInterface;

/**
 * Get geoname data by postcode
 */
class GetGeoNameDataByAddressRequest
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * GetGeoNameDataByPostcode constructor.
     *
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Return geonames information using a fallback mechanism
     *
     * @param AddressRequestInterface $addressRequest
     * @return array
     * @throws NoSuchEntityException
     */
    public function execute(AddressRequestInterface $addressRequest): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('inventory_geoname');

        $qry = $connection->select()->from($tableName)
            ->where('country_code = ?', $addressRequest->getCountry())
            ->where('postcode = ?', $addressRequest->getPostcode())
            ->limit(1);

        $row = $connection->fetchRow($qry);
        if (!$row) {
            $qry = $connection->select()->from($tableName)
                ->where('city = ?', $addressRequest->getCity())
                ->limit(1);

            $row = $connection->fetchRow($qry);
        }

        if (!$row) {
            $qry = $connection->select()->from($tableName)
                ->where('region = ?', $addressRequest->getRegion())
                ->limit(1);

            $row = $connection->fetchRow($qry);
        }

        if (!$row) {
            throw new NoSuchEntityException(__('Unknown geoname for %1', $addressRequest->getAsString()));
        }

        return $row;
    }
}
