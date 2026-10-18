<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model\ResourceModel\ProductCheck;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use WebtechSolutions\TraceLogic\Model\ProductCheck;
use WebtechSolutions\TraceLogic\Model\ResourceModel\ProductCheck as ProductCheckResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(ProductCheck::class, ProductCheckResource::class);
    }
}
