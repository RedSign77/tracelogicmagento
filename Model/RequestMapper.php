<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Builds TraceLogic payloads from quotes, orders and products.
 */
class RequestMapper
{
    private const HS_CODE_PATTERN = '/^[0-9.\s]{4,14}$/';

    /** @var array<string, array<string, string>> */
    private array $attributeCache = [];

    public function __construct(
        private readonly Config $config,
        private readonly ProductResource $productResource
    ) {
    }

    /**
     * @return list<array{product_id: int, sku: string, qty: float}>
     */
    public function cartLines(Quote $quote): array
    {
        $lines = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $lines[] = [
                'product_id' => (int)$item->getProductId(),
                'sku' => (string)$item->getSku(),
                'qty' => (float)$item->getQty(),
            ];
        }

        return $lines;
    }

    /**
     * @return list<array{product_id: int, sku: string, qty: float}>
     */
    public function orderLines(OrderInterface $order): array
    {
        $lines = [];
        foreach ($order->getItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $lines[] = [
                'product_id' => (int)$item->getProductId(),
                'sku' => (string)$item->getSku(),
                'qty' => (float)$item->getQtyOrdered(),
            ];
        }

        return $lines;
    }

    /**
     * Payload for checkout/compliance-check, or null when no line carries compliance data.
     *
     * @param list<array{product_id: int, sku: string, qty: float}> $lines
     * @return array<string, mixed>|null
     */
    public function checkoutPayload(
        string $orderRef,
        string $currency,
        array $lines,
        int $websiteId,
        int $storeId
    ): ?array {
        $items = [];
        foreach ($lines as $line) {
            $attrs = $this->attributes($line['product_id'], $storeId, $websiteId);
            $hs = $attrs['hs_code'];
            if ($attrs['supplier_code'] === '' || !preg_match(self::HS_CODE_PATTERN, $hs)) {
                continue;
            }
            $item = [
                'sku' => mb_substr($line['sku'], 0, 100),
                'hs_code' => $hs,
                'quantity' => $line['qty'],
                'supplier_code' => mb_substr($attrs['supplier_code'], 0, 100),
                'certificates' => $this->certificates($attrs['certificates']),
            ];
            if ($attrs['description'] !== '') {
                $item['product_description'] = $attrs['description'];
            }
            $items[] = $item;
        }

        if ($items === []) {
            return null;
        }

        return [
            'order_id' => mb_substr($orderRef, 0, 100),
            'currency' => strtoupper(substr($currency, 0, 3)),
            'items' => array_slice($items, 0, 500),
        ];
    }

    /**
     * EUDR product entry for eudr/validate-product, or null when the product has no HS code.
     * Returns ['entry' => ..., 'missing' => list<string>] so callers can flag incomplete data.
     *
     * @return array{entry: array<string, mixed>, missing: list<string>, description: string, certificates: list<string>}|null
     */
    public function productEntry(int $productId, string $sku, int $storeId, int $websiteId): ?array
    {
        $attrs = $this->attributes($productId, $storeId, $websiteId);
        if (!preg_match(self::HS_CODE_PATTERN, $attrs['hs_code'])) {
            return null;
        }

        $missing = [];
        $country = strtoupper($attrs['country_of_origin']);
        if (strlen($country) !== 2) {
            $missing[] = 'country_of_origin';
        }

        $entry = ['sku' => $sku, 'hs_code' => $attrs['hs_code'], 'country_of_origin' => $country];
        if ($attrs['species'] !== '') {
            $entry['scientific_species_name'] = mb_substr($attrs['species'], 0, 255);
        }
        $geo = $attrs['geolocation'] !== '' ? json_decode($attrs['geolocation'], true) : null;
        if (is_array($geo) && isset($geo['type'], $geo['coordinates'])) {
            $entry['geolocation'] = $geo;
        }
        if (is_numeric($attrs['plot_area'])) {
            $entry['plot_area_hectares'] = max(0.0, (float)$attrs['plot_area']);
        }
        if ($attrs['dds_reference'] !== '') {
            $entry['dds_reference_number'] = mb_substr($attrs['dds_reference'], 0, 100);
        }

        return [
            'entry' => $entry,
            'missing' => $missing,
            'description' => $attrs['description'],
            'certificates' => $this->certificates($attrs['certificates']),
        ];
    }

    /**
     * @return array{code: string, email: string}
     */
    public function supplierContact(int $productId, int $storeId, int $websiteId): array
    {
        $attrs = $this->attributes($productId, $storeId, $websiteId);

        return ['code' => $attrs['supplier_code'], 'email' => $attrs['supplier_email']];
    }

    /**
     * @return array<string, string> keyed by logical field name, all values trimmed strings
     */
    private function attributes(int $productId, int $storeId, int $websiteId): array
    {
        $key = $productId . '_' . $storeId . '_' . $websiteId;
        if (isset($this->attributeCache[$key])) {
            return $this->attributeCache[$key];
        }

        $fields = [
            'hs_code', 'supplier_code', 'certificates', 'country_of_origin', 'species',
            'geolocation', 'plot_area', 'dds_reference', 'supplier_email', 'description',
        ];
        $codes = [];
        foreach ($fields as $field) {
            $codes[$field] = $this->config->getAttributeCode($field, $websiteId);
        }
        $wanted = array_values(array_unique(array_filter($codes)));
        $raw = $wanted === [] ? [] : $this->productResource->getAttributeRawValue($productId, $wanted, $storeId);
        if (!is_array($raw)) {
            $raw = $wanted !== [] ? [$wanted[0] => $raw] : [];
        }

        $values = [];
        foreach ($codes as $field => $code) {
            $value = $code !== '' && isset($raw[$code]) && is_scalar($raw[$code]) ? (string)$raw[$code] : '';
            $values[$field] = trim($field === 'description' ? $this->plainText($value) : $value);
        }
        $values['description'] = mb_substr($values['description'], 0, 5000);

        return $this->attributeCache[$key] = $values;
    }

    private function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    }

    /**
     * @return list<string>
     */
    private function certificates(string $csv): array
    {
        $list = array_filter(array_map(
            static fn(string $c): string => mb_substr(trim($c), 0, 100),
            explode(',', $csv)
        ));

        return array_values(array_unique($list));
    }
}
