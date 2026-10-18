<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class OnRejected implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'block', 'label' => __('Block checkout')],
            ['value' => 'hold', 'label' => __('Place order on hold')],
        ];
    }
}
