<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Cron;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\Api\Exception\RequestException;
use WebtechSolutions\TraceLogic\Model\Api\Exception\UnavailableException;
use WebtechSolutions\TraceLogic\Model\CheckResult;
use WebtechSolutions\TraceLogic\Model\OrderRechecker;

/**
 * Re-checks orders held by TraceLogic; the re-check releases them once the supplier data is in place.
 */
class ReleaseHeldOrders
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly OrderRechecker $rechecker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('tracelogic_status', ['in' => [CheckResult::REJECTED, CheckResult::WARNING]])
            ->addFieldToFilter('state', Order::STATE_HOLDED)
            ->setOrder('updated_at', 'ASC')
            ->setPageSize(50);

        foreach ($collection as $order) {
            try {
                $this->rechecker->recheck($order);
            } catch (UnavailableException | RequestException) {
                return;
            } catch (\Throwable $e) {
                $this->logger->error('TraceLogic release check failed', ['order' => $order->getIncrementId(), 'error' => $e->getMessage()]);
            }
        }
    }
}
