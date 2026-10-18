<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Console;

use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WebtechSolutions\TraceLogic\Model\Api\Client;
use WebtechSolutions\TraceLogic\Model\CatalogScanner;
use WebtechSolutions\TraceLogic\Model\Config;
use WebtechSolutions\TraceLogic\Model\SupplierSync;

class ScanCatalogCommand extends Command
{
    public function __construct(
        private readonly CatalogScanner $scanner,
        private readonly SupplierSync $supplierSync,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $state,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('tracelogic:catalog:scan')
            ->setDescription('Check catalog products with TraceLogic (EUDR + Green Claims)')
            ->addOption('batch', 'b', InputOption::VALUE_REQUIRED, 'Products per batch (max 200)', (string)Client::BATCH_LIMIT)
            ->addOption('no-sync', null, InputOption::VALUE_NONE, 'Skip pulling supplier data before the scan');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Throwable) {
            // Area already set.
        }

        $batch = max(1, min(Client::BATCH_LIMIT, (int)$input->getOption('batch')));
        $exit = Command::SUCCESS;

        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteId = (int)$website->getId();
            if (!$this->config->isEnabled($websiteId)) {
                continue;
            }
            if (!$input->getOption('no-sync')) {
                try {
                    $sync = $this->supplierSync->syncWebsite($websiteId);
                    $output->writeln(sprintf('  Supplier sync: %d products updated', $sync['products']));
                } catch (\Throwable $e) {
                    $output->writeln('<comment>  Supplier sync skipped: ' . $e->getMessage() . '</comment>');
                }
            }
            $ids = $this->scanner->getScannableIds($websiteId);
            $output->writeln(sprintf('Website %s: %d products', $website->getCode(), count($ids)));
            $counts = [];
            try {
                foreach (array_chunk($ids, $batch) as $chunk) {
                    foreach ($this->scanner->scanProducts($chunk, $websiteId) as $status) {
                        $counts[$status] = ($counts[$status] ?? 0) + 1;
                    }
                }
            } catch (\Throwable $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                $exit = Command::FAILURE;
            }
            foreach ($counts as $status => $n) {
                $output->writeln(sprintf('  %s: %d', $status, $n));
            }
        }

        return $exit;
    }
}
