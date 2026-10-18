<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Plugin;

use Magento\Checkout\Block\Cart\Item\Renderer;
use Magento\Checkout\Model\Session as CheckoutSession;
use WebtechSolutions\TraceLogic\Model\CartAssessor;

/**
 * Shows the compliance reasons in the cart row of the affected item.
 */
class CartItemMessages
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartAssessor $assessor
    ) {
    }

    /**
     * @param array<int, array{text: string, type: string}> $result
     * @return array<int, array{text: string, type: string}>
     */
    public function afterGetMessages(Renderer $subject, array $result): array
    {
        $item = $subject->getItem();
        $notice = $this->assessor->assess($this->checkoutSession->getQuote());
        if ($item === null || $notice === null) {
            return $result;
        }

        $type = $notice['type'] === 'error' ? 'error' : 'notice';
        foreach ($notice['items'] as $row) {
            if ($row['sku'] === (string)$item->getSku()) {
                foreach ($row['reasons'] as $reason) {
                    $result[] = ['text' => $reason, 'type' => $type];
                }
            }
        }

        return $result;
    }
}
