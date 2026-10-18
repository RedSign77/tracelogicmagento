<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Magento\Framework\Model\AbstractModel;

class ProductCheck extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\ProductCheck::class);
    }
}
