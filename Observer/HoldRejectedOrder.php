<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\CheckResult;
use WebtechSolutions\TraceLogic\Model\Config;

/**
 * checkout_submit_all_after: in "hold" mode a rejected order is placed but held until a re-check passes.
 */
class HoldRejectedOrder implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getId()) {
            return;
        }

        $websiteId = (int)$order->getStore()->getWebsiteId();
        $status = $order->getData('tracelogic_status');
        $blocking = $status === CheckResult::REJECTED
            || ($status === CheckResult::WARNING && $this->config->warningBlocks($websiteId));
        if (!$blocking || !$this->config->holdOnRejected($websiteId) || !$order->canHold()) {
            return;
        }

        try {
            $order->hold();
            $order->addCommentToStatusHistory((string)__(
                'Held by TraceLogic: compliance data is missing (%1). Token: %2. The order is released automatically '
                . 'when a re-check passes.',
                $status,
                $order->getData('tracelogic_audit_token')
            ));
            $this->orderRepository->save($order);
        } catch (\Throwable $e) {
            $this->logger->error('TraceLogic could not hold order', ['order' => $order->getIncrementId(), 'error' => $e->getMessage()]);
        }
    }
}
