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
use WebtechSolutions\TraceLogic\Model\CartAssessor;

/**
 * Compliance notice rendered on the checkout page (messages would pile up in the session there).
 */
class CartNotice extends Template
{
    public function __construct(
        Context $context,
        private readonly Session $checkoutSession,
        private readonly CartAssessor $assessor,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array{type: string, message: string, items: list<array{sku: string, name: string, reasons: list<string>}>}|null
     */
    public function getNotice(): ?array
    {
        return $this->assessor->assess($this->checkoutSession->getQuote());
    }
}
