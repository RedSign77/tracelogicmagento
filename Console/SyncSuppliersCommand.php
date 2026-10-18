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
use WebtechSolutions\TraceLogic\Model\Config;
use WebtechSolutions\TraceLogic\Model\SupplierSync;

class SyncSuppliersCommand extends Command
{
    public function __construct(
        private readonly SupplierSync $sync,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $state,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('tracelogic:supplier:sync')
            ->setDescription('Pull supplier-uploaded data from TraceLogic into empty product attributes')
            ->addOption('supplier', 's', InputOption::VALUE_REQUIRED, 'Only this supplier code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Throwable) {
            // Area already set.
        }

        $only = $input->getOption('supplier');
        $exit = Command::SUCCESS;
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteId = (int)$website->getId();
            if (!$this->config->isEnabled($websiteId)) {
                continue;
            }
            try {
                $stats = $this->sync->syncWebsite($websiteId, is_string($only) && $only !== '' ? $only : null);
                $output->writeln(sprintf(
                    'Website %s: %d known suppliers, %d products updated',
                    $website->getCode(),
                    $stats['suppliers'],
                    $stats['products']
                ));
            } catch (\Throwable $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                $exit = Command::FAILURE;
            }
        }

        return $exit;
    }
}
