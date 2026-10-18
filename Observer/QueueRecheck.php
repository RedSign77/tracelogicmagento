<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\CheckResult;

/**
 * checkout_submit_all_after: queue a re-check for orders placed while TraceLogic was down (fail-open).
 */
class QueueRecheck implements ObserverInterface
{
    public const TOPIC = 'tracelogic.order.recheck';

    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if ($order && $order->getId() && $order->getData('tracelogic_status') === CheckResult::UNCHECKED) {
            try {
                $this->publisher->publish(self::TOPIC, (string)$order->getId());
            } catch (\Throwable $e) {
                // The hourly cron re-queues UNCHECKED orders; never break the checkout response.
                $this->logger->warning('TraceLogic re-check not queued', ['order_id' => $order->getId(), 'error' => $e->getMessage()]);
            }
        }
    }
}
