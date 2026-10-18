<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model\Api;

use Magento\Framework\App\CacheInterface;
use WebtechSolutions\TraceLogic\Model\Config;

class CircuitBreaker
{
    private const KEY = 'tracelogic_circuit_failures_';
    private const OPEN_KEY = 'tracelogic_circuit_open_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Config $config
    ) {
    }

    public function isOpen(?int $websiteId): bool
    {
        return (bool)$this->cache->load(self::OPEN_KEY . (int)$websiteId);
    }

    public function recordFailure(?int $websiteId): void
    {
        $id = (int)$websiteId;
        $pause = $this->config->getCircuitPauseSeconds($websiteId);
        $failures = (int)$this->cache->load(self::KEY . $id) + 1;

        if ($failures >= $this->config->getCircuitThreshold($websiteId)) {
            $this->cache->save('1', self::OPEN_KEY . $id, [], $pause);
            $this->cache->remove(self::KEY . $id);
            return;
        }

        $this->cache->save((string)$failures, self::KEY . $id, [], $pause);
    }

    public function recordSuccess(?int $websiteId): void
    {
        $this->cache->remove(self::KEY . (int)$websiteId);
    }
}
