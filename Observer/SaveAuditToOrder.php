<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use WebtechSolutions\TraceLogic\Model\CheckResultHolder;

/**
 * sales_model_service_quote_submit_before: copy the check result onto the order.
 */
class SaveAuditToOrder implements ObserverInterface
{
    public function __construct(
        private readonly CheckResultHolder $holder,
        private readonly Json $json
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();
        $result = $this->holder->get((int)$quote->getId());
        if ($result === null) {
            return;
        }

        $order->setData('tracelogic_status', $result->getStatus());
        $order->setData('tracelogic_score', $result->getScore());
        $order->setData('tracelogic_audit_token', $result->getToken());
        $order->setData('tracelogic_flags', $this->json->serialize($result->getFlags()));
    }
}
