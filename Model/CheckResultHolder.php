<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

/**
 * Carries the checkout result from the submit plugin to the order observer (per request).
 */
class CheckResultHolder
{
    /** @var array<int, CheckResult> */
    private array $results = [];

    public function set(int $quoteId, CheckResult $result): void
    {
        $this->results[$quoteId] = $result;
    }

    public function get(int $quoteId): ?CheckResult
    {
        return $this->results[$quoteId] ?? null;
    }
}
