<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Block\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;

/**
 * Order success page notice: the order was placed but is on hold for missing compliance data.
 */
class HoldNotice extends Template
{
    public function __construct(
        Context $context,
        private readonly Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isHeld(): bool
    {
        $order = $this->checkoutSession->getLastRealOrder();

        return $order && $order->getId()
            && $order->getState() === Order::STATE_HOLDED
            && (string)$order->getData('tracelogic_audit_token') !== '';
    }

    public function getReference(): string
    {
        return (string)$this->checkoutSession->getLastRealOrder()->getData('tracelogic_audit_token');
    }
}
