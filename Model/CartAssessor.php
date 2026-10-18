<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Magento\Quote\Model\Quote;

/**
 * Customer-facing compliance notice for a cart (preview check, cached per cart content).
 */
class CartAssessor
{
    public function __construct(
        private readonly Config $config,
        private readonly RequestMapper $mapper,
        private readonly CheckoutValidator $validator
    ) {
    }

    /** flag code (or prefix) => customer-facing reason */
    private const REASONS = [
        'EUDR_UNKNOWN_SUPPLIER' => 'Supplier data (declaration) is missing',
        'EUDR_MISSING_GEOLOCATION' => 'Plot geolocation is missing',
        'EUDR_INVALID_GEOLOCATION' => 'Plot geolocation is invalid',
        'EUDR_POLYGON_REQUIRED' => 'Plot outline (polygon) is required for this plot size',
        'EUDR_MISSING_DDS' => 'Due diligence statement (DDS) reference is missing',
        'EUDR_INVALID_DDS' => 'Due diligence statement (DDS) reference is invalid',
        'EUDR_DDS_NOT_FOUND' => 'Due diligence statement (DDS) was not found',
        'EUDR_DDS_EXPIRED' => 'Due diligence statement (DDS) is no longer active',
        'EUDR_DECLARATION_EXPIRED' => 'Supplier declaration has expired',
        'EUDR_HIGH_RISK_ORIGIN' => 'Country of origin is classified as high risk',
        'GC_' => 'Unsubstantiated environmental claim in the product description',
    ];

    /**
     * @return array{type: string, message: string, items: list<array{sku: string, name: string, reasons: list<string>}>}|null
     *         type is "error" or "warning"
     */
    public function assess(Quote $quote): ?array
    {
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        if (!$quote->getId() || !$quote->hasItems() || !$this->config->isEnabled($websiteId)) {
            return null;
        }

        $result = $this->validator->validate(
            'Q' . $quote->getId(),
            (string)$quote->getQuoteCurrencyCode(),
            $this->mapper->cartLines($quote),
            $websiteId,
            (int)$quote->getStoreId(),
            true,
            true
        );

        $items = $this->describeItems($quote, $result);

        if ($result->isBlocking($this->config->warningBlocks($websiteId))) {
            if ($this->config->holdOnRejected($websiteId)) {
                return ['type' => 'warning', 'message' => (string)__(
                    'Some items in your cart still need compliance data from the supplier (EUDR / green claims). '
                    . 'You can place the order, but it will be on hold until the data is provided. Reference: %1',
                    $result->getToken() ?? '-'
                ), 'items' => $items];
            }

            return ['type' => 'error', 'message' => (string)__(
                'Some items in your cart do not meet EU compliance requirements (EUDR / green claims). '
                . 'The order cannot be placed until they are removed. Reference: %1',
                $result->getToken() ?? '-'
            ), 'items' => $items];
        }

        if ($result->getStatus() === CheckResult::WARNING) {
            return [
                'type' => 'warning',
                'message' => (string)__('Some items in your cart have open compliance warnings.'),
                'items' => $items,
            ];
        }

        return null;
    }

    /**
     * Customer-facing reasons per cart item (LOW severity flags are not shown).
     *
     * @return list<array{sku: string, name: string, reasons: list<string>}>
     */
    private function describeItems(Quote $quote, CheckResult $result): array
    {
        $names = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $names[(string)$item->getSku()] = (string)$item->getName();
        }

        $bySku = [];
        foreach ($result->getFlags() as $flag) {
            $sku = $flag['sku'] ?? '';
            if ($sku === '' || ($flag['severity'] ?? '') === 'LOW') {
                continue;
            }
            $bySku[$sku][$this->reason($flag)] = true;
        }

        $items = [];
        foreach ($bySku as $sku => $reasons) {
            $items[] = ['sku' => (string)$sku, 'name' => $names[$sku] ?? $sku, 'reasons' => array_keys($reasons)];
        }

        return $items;
    }

    /**
     * @param array{code: string, message: string} $flag
     */
    private function reason(array $flag): string
    {
        foreach (self::REASONS as $code => $text) {
            if ($flag['code'] === $code || (str_ends_with($code, '_') && str_starts_with($flag['code'], $code))) {
                return (string)__($text);
            }
        }

        return $flag['message'];
    }
}
