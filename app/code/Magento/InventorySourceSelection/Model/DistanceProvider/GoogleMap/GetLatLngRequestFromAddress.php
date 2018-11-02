<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventorySourceSelection\Model\DistanceProvider\GoogleMap;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\InventorySourceSelection\Model\Request\LatLngRequest;
use Magento\InventorySourceSelection\Model\Request\LatLngRequestFactory;
use Magento\InventorySourceSelectionApi\Api\Data\AddressRequestInterface;

/**
 * Get latitude and longitude from address
 */
class GetLatLngRequestFromAddress
{
    const GOOGLE_ENDPOINT = 'https://maps.google.com/maps/api/geocode/json';

    private $latLngCache = [];

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LatLngRequestFactory
     */
    private $latLngRequestFactory;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var GetApiKey
     */
    private $getApiKey;

    /**
     * GetLatLngRequestFromAddress constructor.
     *
     * @param ClientInterface $client
     * @param LatLngRequestFactory $latLngRequestFactory
     * @param Json $json
     * @param ScopeConfigInterface $scopeConfig
     * @param GetApiKey $getApiKey
     */
    public function __construct(
        ClientInterface $client,
        LatLngRequestFactory $latLngRequestFactory,
        Json $json,
        ScopeConfigInterface $scopeConfig,
        GetApiKey $getApiKey
    ) {
        $this->client = $client;
        $this->scopeConfig = $scopeConfig;
        $this->latLngRequestFactory = $latLngRequestFactory;
        $this->json = $json;
        $this->getApiKey = $getApiKey;
    }

    /**
     * Get latitude and longitude from address
     *
     * @param AddressRequestInterface $addressRequest
     * @return LatLngRequest
     * @throws LocalizedException
     */
    public function execute(AddressRequestInterface $addressRequest): LatLngRequest
    {
        $cacheKey = $addressRequest->getAsString();
        if (!isset($this->latLngCache[$cacheKey])) {

            $queryString = http_build_query([
                'key' => $this->getApiKey->execute(),
                'address' => $addressRequest->getAsString(),
            ]);

            $this->client->get(self::GOOGLE_ENDPOINT . '?' . $queryString);
            if ($this->client->getStatus() !== 200) {
                throw new LocalizedException(__('Unable to connect google API for geocoding'));
            }

            $res = $this->json->unserialize($this->client->getBody());

            if ($res['status'] !== 'OK') {
                throw new LocalizedException(__('Unable to geocode address %1', $addressRequest->getAsString()));
            }

            $location = $res['results'][0]['geometry']['location'];
            $this->latLngCache[$cacheKey] = $this->latLngRequestFactory->create([
                'lat' => (float)$location['lat'],
                'lng' => (float)$location['lng'],
            ]);
        }

        return $this->latLngCache[$cacheKey];
    }
}
