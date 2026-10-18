<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use WebtechSolutions\TraceLogic\Model\CheckoutValidator;
use WebtechSolutions\TraceLogic\Model\CheckResult;
use WebtechSolutions\TraceLogic\Model\CheckResultHolder;
use WebtechSolutions\TraceLogic\Model\Config;
use WebtechSolutions\TraceLogic\Model\RequestMapper;
use WebtechSolutions\TraceLogic\Model\SupplierNotifier;

/**
 * Blocks order submission (storefront, guest, admin) when TraceLogic rejects the cart.
 */
class PlaceOrderGate
{
    public function __construct(
        private readonly Config $config,
        private readonly RequestMapper $mapper,
        private readonly CheckoutValidator $validator,
        private readonly CheckResultHolder $holder,
        private readonly SupplierNotifier $notifier
    ) {
    }

    /**
     * @param array<string, mixed> $orderData
     * @return null
     * @throws LocalizedException
     */
    public function beforeSubmit(QuoteManagement $subject, Quote $quote, $orderData = [])
    {
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        if (!$this->config->isEnabled($websiteId)) {
            return null;
        }

        $quote->reserveOrderId();
        $lines = $this->mapper->cartLines($quote);
        $result = $this->validator->validate(
            (string)($quote->getReservedOrderId() ?: 'Q' . $quote->getId()),
            (string)$quote->getQuoteCurrencyCode(),
            $lines,
            $websiteId,
            (int)$quote->getStoreId()
        );

        if ($result->getStatus() === CheckResult::SKIPPED) {
            return null;
        }

        if ($result->getStatus() === CheckResult::UNCHECKED && !$this->config->isFailOpen($websiteId)) {
            throw new LocalizedException(
                __('The compliance service is temporarily unavailable. Please try again later.')
            );
        }

        if ($result->isBlocking($this->config->warningBlocks($websiteId))) {
            $this->notifier->notify($result, $lines, $websiteId, (int)$quote->getStoreId());

            if (!$this->config->holdOnRejected($websiteId)) {
                throw new LocalizedException(__(
                    'This order cannot be placed because it does not meet EU compliance requirements '
                    . '(EUDR / green claims). Reference: %1',
                    $result->getToken() ?? '-'
                ));
            }
        }

        $this->holder->set((int)$quote->getId(), $result);

        return null;
    }
}
