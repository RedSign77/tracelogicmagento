<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\Api\Client;
use WebtechSolutions\TraceLogic\Model\Api\Exception\RequestException;
use WebtechSolutions\TraceLogic\Model\Api\Exception\UnavailableException;
use WebtechSolutions\TraceLogic\Model\ResourceModel\ProductCheck as ProductCheckResource;

/**
 * Runs EUDR + Green Claims checks on catalog products and stores the result.
 */
class CatalogScanner
{
    public const COMPLIANT = 'COMPLIANT';
    public const NON_COMPLIANT = 'NON_COMPLIANT';
    public const WARNING = 'WARNING';
    public const INCOMPLETE = 'INCOMPLETE';

    public function __construct(
        private readonly Client $client,
        private readonly RequestMapper $mapper,
        private readonly Config $config,
        private readonly ProductCheckResource $storage,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * IDs of the website's products that have an HS code.
     *
     * @return list<int>
     */
    public function getScannableIds(int $websiteId): array
    {
        $hsCode = $this->config->getAttributeCode('hs_code', $websiteId);
        if ($hsCode === '') {
            return [];
        }

        try {
            $collection = $this->productCollectionFactory->create();
            $collection->addWebsiteFilter($websiteId)
                ->addAttributeToFilter($hsCode, ['notnull' => true])
                ->addAttributeToFilter($hsCode, ['neq' => '']);

            return array_map('intval', $collection->getAllIds());
        } catch (\Throwable $e) {
            $this->logger->error('TraceLogic catalog scan: cannot select products', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * @param list<int> $productIds
     * @return array<int, string> product id => stored status
     * @throws UnavailableException
     * @throws RequestException
     */
    public function scanProducts(array $productIds, int $websiteId): array
    {
        $storeId = (int)$this->storeManager->getWebsite($websiteId)->getDefaultStore()->getId();
        $skus = $this->loadSkus($productIds);

        $entries = [];
        $meta = [];
        foreach ($productIds as $id) {
            if (!isset($skus[$id])) {
                continue;
            }
            $mapped = $this->mapper->productEntry($id, $skus[$id], $storeId, $websiteId);
            if ($mapped === null) {
                continue;
            }
            $meta[$id] = $mapped;
            if ($mapped['missing'] === []) {
                $entries[$skus[$id]] = $mapped['entry'];
            }
        }

        $eudr = [];
        if ($entries !== []) {
            foreach ($this->client->validateProducts(array_values($entries), $websiteId) as $result) {
                $eudr[(string)($result['sku'] ?? '')] = $result;
            }
        }

        $statuses = [];
        foreach ($meta as $id => $mapped) {
            $sku = $skus[$id];
            $flags = [];
            $status = self::COMPLIANT;

            if ($mapped['missing'] !== []) {
                $status = self::INCOMPLETE;
                foreach ($mapped['missing'] as $field) {
                    $flags[] = ['code' => 'MISSING_' . strtoupper($field), 'severity' => 'MEDIUM', 'message' => $field];
                }
            } elseif (isset($eudr[$sku])) {
                $flags = $eudr[$sku]['flags'] ?? [];
                if (empty($eudr[$sku]['is_compliant'])) {
                    $status = self::NON_COMPLIANT;
                } elseif ($flags !== []) {
                    $status = self::WARNING;
                }
            }

            if ($mapped['description'] !== '') {
                $scan = $this->client->scanText($mapped['description'], $mapped['certificates'], $websiteId);
                foreach ($scan['detected_claims'] ?? [] as $claim) {
                    $flags[] = [
                        'code' => 'GC_' . ($claim['category'] ?? 'CLAIM'),
                        'severity' => $claim['risk'] ?? 'MEDIUM',
                        'message' => trim(($claim['phrase'] ?? '') . ': ' . ($claim['reason'] ?? '')),
                    ];
                    if ($status === self::COMPLIANT) {
                        $status = self::WARNING;
                    }
                }
            }

            $this->storage->upsert([
                'product_id' => $id,
                'website_id' => $websiteId,
                'sku' => $sku,
                'status' => $status,
                'flags' => $this->json->serialize($flags),
            ]);
            $statuses[$id] = $status;
        }

        return $statuses;
    }

    /**
     * @param list<int> $productIds
     * @return array<int, string>
     */
    private function loadSkus(array $productIds): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $productIds]);
        $skus = [];
        foreach ($collection as $product) {
            $skus[(int)$product->getId()] = (string)$product->getSku();
        }

        return $skus;
    }
}
