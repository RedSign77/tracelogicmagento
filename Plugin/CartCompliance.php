<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Plugin;

use Magento\Checkout\Controller\Cart\Index;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use WebtechSolutions\TraceLogic\Model\CartAssessor;

/**
 * Early warning on the cart page (summary message; per-item reasons are shown by CartItemMessages). The binding check still runs when the order is submitted.
 */
class CartCompliance
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartAssessor $assessor,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function beforeExecute(Index $subject): void
    {
        $notice = $this->assessor->assess($this->checkoutSession->getQuote());
        if ($notice === null) {
            return;
        }

        $add = $notice['type'] === 'error'
            ? fn(string $text) => $this->messageManager->addErrorMessage($text)
            : fn(string $text) => $this->messageManager->addWarningMessage($text);

        $add($notice['message']);
    }
}
