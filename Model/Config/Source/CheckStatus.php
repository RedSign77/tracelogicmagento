<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use WebtechSolutions\TraceLogic\Model\CatalogScanner;

class CheckStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        $options = [];
        foreach ([CatalogScanner::COMPLIANT, CatalogScanner::WARNING, CatalogScanner::NON_COMPLIANT, CatalogScanner::INCOMPLETE] as $status) {
            $options[] = ['value' => $status, 'label' => $status];
        }

        return $options;
    }
}
