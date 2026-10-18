<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\CheckResult;
use WebtechSolutions\TraceLogic\Model\Config;
use WebtechSolutions\TraceLogic\Model\RequestMapper;
use WebtechSolutions\TraceLogic\Model\SupplierNotifier;
use WebtechSolutions\TraceLogic\Model\SupplierRequester;

class SupplierNotifierTest extends TestCase
{
    private function flag(string $code, string $sku): array
    {
        return ['code' => $code, 'severity' => 'CRITICAL', 'message' => '', 'source' => 'eudr', 'sku' => $sku];
    }

    private function build(bool $enabled, bool $recent, ?SupplierRequester &$requester = null): SupplierNotifier
    {
        $config = $this->createStub(Config::class);
        $config->method('autoRequestSuppliers')->willReturn($enabled);
        $mapper = $this->createStub(RequestMapper::class);
        $mapper->method('supplierContact')->willReturnCallback(static fn(int $id) => match ($id) {
            1 => ['code' => 'SUP-1', 'email' => 'a@example.com'],
            2 => ['code' => 'SUP-2', 'email' => 'not-an-email'],
            default => ['code' => '', 'email' => ''],
        });
        $requester = $this->createMock(SupplierRequester::class);
        $requester->method('hasRecentRequest')->willReturn($recent);

        return new SupplierNotifier($config, $mapper, $requester, $this->createStub(LoggerInterface::class));
    }

    private function lines(): array
    {
        return [
            ['product_id' => 1, 'sku' => 'A', 'qty' => 1.0],
            ['product_id' => 2, 'sku' => 'B', 'qty' => 1.0],
        ];
    }

    public function testMergesFieldsPerSupplierAndSkipsInvalidEmail(): void
    {
        $notifier = $this->build(true, false, $requester);
        $result = new CheckResult('REJECTED', 10, 't', [
            $this->flag('EUDR_MISSING_GEOLOCATION', 'A'),
            $this->flag('EUDR_MISSING_DDS', 'A'),
            $this->flag('EUDR_MISSING_DDS', 'B'),
            $this->flag('GC_VAGUE_ENV_CLAIM', 'A'),
        ]);

        $requester->expects($this->once())->method('request')
            ->with('SUP-1', 'a@example.com', ['geolocation', 'dds_reference_number'], 1)
            ->willReturn(['magic_link' => 'l', 'expires_at' => 'e', 'emailed' => true]);

        $this->assertSame(1, $notifier->notify($result, $this->lines(), 1, 1));
    }

    public function testDisabledOrRecentSendsNothing(): void
    {
        $result = new CheckResult('REJECTED', 10, 't', [$this->flag('EUDR_MISSING_DDS', 'A')]);

        $disabled = $this->build(false, false, $r1);
        $r1->expects($this->never())->method('request');
        $this->assertSame(0, $disabled->notify($result, $this->lines(), 1, 1));

        $recent = $this->build(true, true, $r2);
        $r2->expects($this->never())->method('request');
        $this->assertSame(0, $recent->notify($result, $this->lines(), 1, 1));
    }
}
