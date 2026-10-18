<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Psr\Log\LoggerInterface;

/**
 * E-mails a data request link to suppliers whose missing data caused a blocked order.
 */
class SupplierNotifier
{
    /** flag code => TraceLogic required_fields entries */
    private const DATA_FLAGS = [
        'EUDR_UNKNOWN_SUPPLIER' => ['geolocation', 'dds_reference_number', 'country_of_production'],
        'EUDR_MISSING_GEOLOCATION' => ['geolocation'],
        'EUDR_INVALID_GEOLOCATION' => ['geolocation'],
        'EUDR_POLYGON_REQUIRED' => ['geolocation'],
        'EUDR_MISSING_DDS' => ['dds_reference_number'],
        'EUDR_INVALID_DDS' => ['dds_reference_number'],
        'EUDR_DDS_NOT_FOUND' => ['dds_reference_number'],
        'EUDR_DECLARATION_EXPIRED' => ['valid_until'],
    ];

    public function __construct(
        private readonly Config $config,
        private readonly RequestMapper $mapper,
        private readonly SupplierRequester $requester,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Best effort: never throws, so it cannot disturb the checkout.
     *
     * @param list<array{product_id: int, sku: string, qty: float}> $lines
     * @return int number of suppliers e-mailed
     */
    public function notify(CheckResult $result, array $lines, int $websiteId, int $storeId): int
    {
        if (!$this->config->autoRequestSuppliers($websiteId)) {
            return 0;
        }

        try {
            $bySku = [];
            foreach ($lines as $line) {
                $bySku[$line['sku']] = $line['product_id'];
            }

            /** @var array<string, array{email: string, fields: array<string, true>}> $suppliers */
            $suppliers = [];
            foreach ($result->getFlags() as $flag) {
                if (!isset(self::DATA_FLAGS[$flag['code']], $bySku[$flag['sku'] ?? ''])) {
                    continue;
                }
                $contact = $this->mapper->supplierContact($bySku[$flag['sku']], $storeId, $websiteId);
                if ($contact['code'] === '' || !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $suppliers[$contact['code']]['email'] = $contact['email'];
                foreach (self::DATA_FLAGS[$flag['code']] as $field) {
                    $suppliers[$contact['code']]['fields'][$field] = true;
                }
            }

            $sent = 0;
            foreach ($suppliers as $code => $data) {
                if ($this->requester->hasRecentRequest($code)) {
                    continue;
                }
                $outcome = $this->requester->request($code, $data['email'], array_keys($data['fields']), $websiteId);
                $sent += $outcome['emailed'] ? 1 : 0;
                $this->logger->info('TraceLogic supplier data request', ['supplier' => $code, 'emailed' => $outcome['emailed']]);
            }

            return $sent;
        } catch (\Throwable $e) {
            $this->logger->error('TraceLogic supplier notification failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }
}
