<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ProductCheck extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('tracelogic_product_check', 'entity_id');
    }

    /**
     * @param array{product_id: int, website_id: int, sku: string, status: string, flags: string} $row
     */
    public function upsert(array $row): void
    {
        $row['checked_at'] = gmdate('Y-m-d H:i:s');
        $this->getConnection()->insertOnDuplicate(
            $this->getMainTable(),
            $row,
            ['sku', 'status', 'flags', 'checked_at']
        );
    }
}
