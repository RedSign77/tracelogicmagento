<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Test\Unit\Model\Api;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\TestCase;
use WebtechSolutions\TraceLogic\Model\Api\CircuitBreaker;
use WebtechSolutions\TraceLogic\Model\Config;

class CircuitBreakerTest extends TestCase
{
    public function testOpensAfterThreshold(): void
    {
        $store = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(static function (string $id) use (&$store) {
            return $store[$id] ?? false;
        });
        $cache->method('save')->willReturnCallback(static function ($data, string $id) use (&$store) {
            $store[$id] = $data;
            return true;
        });
        $cache->method('remove')->willReturnCallback(static function (string $id) use (&$store) {
            $store[$id] = false;
            return true;
        });
        $config = $this->createStub(Config::class);
        $config->method('getCircuitThreshold')->willReturn(2);
        $config->method('getCircuitPauseSeconds')->willReturn(300);

        $breaker = new CircuitBreaker($cache, $config);

        $breaker->recordFailure(1);
        $this->assertFalse($breaker->isOpen(1));
        $breaker->recordFailure(1);
        $this->assertTrue($breaker->isOpen(1));
        $this->assertFalse($breaker->isOpen(2));
    }
}
