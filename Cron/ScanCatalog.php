<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\Api\Client;
use WebtechSolutions\TraceLogic\Model\CatalogScanner;
use WebtechSolutions\TraceLogic\Model\Config;
use WebtechSolutions\TraceLogic\Model\SupplierSync;

class ScanCatalog
{
    public function __construct(
        private readonly CatalogScanner $scanner,
        private readonly SupplierSync $supplierSync,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteId = (int)$website->getId();
            if (!$this->config->isEnabled($websiteId)) {
                continue;
            }
            try {
                $this->supplierSync->syncWebsite($websiteId);
                foreach (array_chunk($this->scanner->getScannableIds($websiteId), Client::BATCH_LIMIT) as $chunk) {
                    $this->scanner->scanProducts($chunk, $websiteId);
                }
            } catch (\Throwable $e) {
                $this->logger->error('TraceLogic nightly scan failed', ['website' => $websiteId, 'error' => $e->getMessage()]);
            }
        }
    }
}
