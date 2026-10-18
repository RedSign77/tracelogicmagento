<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\Api\Client;

/**
 * Pulls the data suppliers uploaded to TraceLogic (declaration, certificates) into empty product attributes.
 * Values already present on a product are never overwritten.
 */
class SupplierSync
{
    private const FIELDS = ['country_of_origin', 'geolocation', 'dds_reference', 'certificates'];

    public function __construct(
        private readonly Client $client,
        private readonly Config $config,
        private readonly CollectionFactory $collectionFactory,
        private readonly Action $productAction,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Attribute changes (code => value) that fill the product's empty fields from the supplier data.
     *
     * @param array<string, string> $current logical field => current value
     * @param array<string, mixed> $supplier TraceLogic GET /v1/suppliers/{code} response
     * @return array<string, string> logical field => new value
     */
    public function computeChanges(array $current, array $supplier): array
    {
        $changes = [];

        $country = strtoupper((string)($supplier['country_of_production'] ?? ''));
        if (trim($current['country_of_origin'] ?? '') === '' && strlen($country) === 2) {
            $changes['country_of_origin'] = $country;
        }

        $geo = $supplier['geolocation'] ?? null;
        if (trim($current['geolocation'] ?? '') === '' && is_array($geo) && $geo !== []) {
            $changes['geolocation'] = (string)json_encode($geo, JSON_UNESCAPED_SLASHES);
        }

        $dds = trim((string)($supplier['dds_reference_number'] ?? ''));
        if (trim($current['dds_reference'] ?? '') === '' && $dds !== '') {
            $changes['dds_reference'] = $dds;
        }

        $existing = array_filter(array_map('trim', explode(',', (string)($current['certificates'] ?? ''))));
        $merged = array_values(array_unique([...$existing, ...array_map('strval', (array)($supplier['certificates'] ?? []))]));
        if (count($merged) > count($existing)) {
            $changes['certificates'] = implode(',', $merged);
        }

        return $changes;
    }

    /**
     * @return array{suppliers: int, products: int}
     * @throws Api\Exception\UnavailableException
     */
    public function syncWebsite(int $websiteId, ?string $onlySupplier = null): array
    {
        $stats = ['suppliers' => 0, 'products' => 0];

        foreach ($this->productsBySupplier($websiteId, $onlySupplier) as $code => $products) {
            try {
                $supplier = $this->client->getSupplier((string)$code, $websiteId);
            } catch (Api\Exception\RequestException $e) {
                $this->logger->warning('TraceLogic supplier sync skipped', ['supplier' => $code, 'error' => $e->getMessage()]);
                continue;
            }
            if ($supplier === null) {
                continue;
            }

            $stats['suppliers']++;
            foreach ($products as $productId => $current) {
                $changes = $this->computeChanges($current, $supplier);
                if ($changes === []) {
                    continue;
                }
                $data = [];
                foreach ($changes as $field => $value) {
                    $data[$this->config->getAttributeCode($field, $websiteId)] = $value;
                }
                $this->productAction->updateAttributes([$productId], $data, 0);
                $stats['products']++;
                $this->logger->info('TraceLogic supplier data synced', [
                    'supplier' => $code,
                    'product_id' => $productId,
                    'fields' => array_keys($changes),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @return array<string, array<int, array<string, string>>> supplier code => product id => current field values
     */
    private function productsBySupplier(int $websiteId, ?string $onlySupplier): array
    {
        $supplierAttr = $this->config->getAttributeCode('supplier_code', $websiteId);
        if ($supplierAttr === '') {
            return [];
        }

        $codes = [];
        foreach (self::FIELDS as $field) {
            $code = $this->config->getAttributeCode($field, $websiteId);
            if ($code !== '') {
                $codes[$field] = $code;
            }
        }

        $collection = $this->collectionFactory->create();
        $collection->setStoreId(0)
            ->addWebsiteFilter($websiteId)
            ->addAttributeToSelect([$supplierAttr, ...array_values($codes)])
            ->addAttributeToFilter($supplierAttr, $onlySupplier !== null ? ['eq' => $onlySupplier] : ['notnull' => true]);

        $result = [];
        foreach ($collection as $product) {
            $supplier = trim((string)$product->getData($supplierAttr));
            if ($supplier === '') {
                continue;
            }
            $current = [];
            foreach (self::FIELDS as $field) {
                $current[$field] = isset($codes[$field]) ? trim((string)$product->getData($codes[$field])) : '';
            }
            $result[$supplier][(int)$product->getId()] = $current;
        }

        return $result;
    }
}
