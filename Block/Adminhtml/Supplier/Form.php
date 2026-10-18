<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Block\Adminhtml\Supplier;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use WebtechSolutions\TraceLogic\Model\Config;
use WebtechSolutions\TraceLogic\Model\SupplierRequester;

class Form extends Template
{
    public function __construct(
        Context $context,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getActionUrl(): string
    {
        return $this->getUrl('tracelogic/supplier/send');
    }

    /**
     * @return array<int, string> website id => name, only TraceLogic-enabled websites
     */
    public function getWebsites(): array
    {
        $websites = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            if ($this->config->isEnabled((int)$website->getId())) {
                $websites[(int)$website->getId()] = $website->getName();
            }
        }

        return $websites;
    }

    /**
     * @return list<string>
     */
    public function getFields(): array
    {
        return SupplierRequester::FIELDS;
    }
}
