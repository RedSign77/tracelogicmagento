<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model\Queue;

use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\CheckResult;
use WebtechSolutions\TraceLogic\Model\OrderRechecker;
use WebtechSolutions\TraceLogic\Model\Api\Exception\UnavailableException;

class OrderRecheckConsumer
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderRechecker $rechecker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(string $orderId): void
    {
        try {
            $order = $this->orderRepository->get((int)$orderId);
            if ($order->getData('tracelogic_status') !== CheckResult::UNCHECKED) {
                return;
            }
            $this->rechecker->recheck($order);
        } catch (UnavailableException $e) {
            // The hourly cron re-queues orders that are still UNCHECKED.
            $this->logger->warning('TraceLogic re-check postponed', ['order_id' => $orderId]);
        } catch (\Throwable $e) {
            $this->logger->error('TraceLogic re-check failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        }
    }
}
