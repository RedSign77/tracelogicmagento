<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Cron;

use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use WebtechSolutions\TraceLogic\Model\CheckResult;
use WebtechSolutions\TraceLogic\Observer\QueueRecheck;

/**
 * Re-queues orders that are still UNCHECKED after 10 minutes.
 */
class RequeueUnchecked
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly PublisherInterface $publisher
    ) {
    }

    public function execute(): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('tracelogic_status', CheckResult::UNCHECKED)
            ->addFieldToFilter('created_at', ['lt' => gmdate('Y-m-d H:i:s', time() - 600)])
            ->setPageSize(100);

        foreach ($collection->getAllIds() as $id) {
            $this->publisher->publish(QueueRecheck::TOPIC, (string)$id);
        }
    }
}
