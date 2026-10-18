<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Per-item compliance reasons for the checkout order summary (keyed by quote item id).
 */
class CheckoutConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartAssessor $assessor
    ) {
    }

    public function getConfig(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $notice = $this->assessor->assess($quote);
        if ($notice === null) {
            return [];
        }

        $bySku = [];
        foreach ($notice['items'] as $row) {
            $bySku[$row['sku']] = $row['reasons'];
        }

        $byItem = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            if (isset($bySku[(string)$item->getSku()])) {
                $byItem[(int)$item->getId()] = [
                    'type' => $notice['type'],
                    'reasons' => $bySku[(string)$item->getSku()],
                ];
            }
        }

        return ['tracelogicItemNotices' => $byItem];
    }
}
