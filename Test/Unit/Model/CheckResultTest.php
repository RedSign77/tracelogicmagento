<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebtechSolutions\TraceLogic\Model\CheckResult;

class CheckResultTest extends TestCase
{
    public function testFromResponseCollectsFlagsFromAllChecks(): void
    {
        $result = CheckResult::fromResponse([
            'status' => 'REJECTED',
            'compliance_score' => 35,
            'audit_token' => 'chk_vault_99b1c88a11f0',
            'checks' => [
                'eudr' => ['status' => 'FAILED', 'flags' => [
                    ['code' => 'EUDR_MISSING_GEOLOCATION', 'severity' => 'CRITICAL', 'message' => 'm1'],
                ]],
                'green_claims' => ['status' => 'WARNING', 'flags' => [
                    ['code' => 'GC_UNVERIFIED_CLAIM', 'severity' => 'MEDIUM', 'message' => 'm2'],
                ]],
            ],
        ]);

        $this->assertSame('REJECTED', $result->getStatus());
        $this->assertSame(35, $result->getScore());
        $this->assertSame(
            ['EUDR_MISSING_GEOLOCATION', 'GC_UNVERIFIED_CLAIM'],
            array_column($result->getFlags(), 'code')
        );
        $this->assertSame(['eudr', 'green_claims'], array_column($result->getFlags(), 'source'));
    }

    public function testArrayRoundTrip(): void
    {
        $result = new CheckResult('WARNING', 80, 'chk_vault_aaaaaaaaaaaa', []);

        $this->assertEquals($result, CheckResult::fromArray($result->toArray()));
    }

    #[DataProvider('blockingProvider')]
    public function testIsBlocking(string $status, bool $warningBlocks, bool $expected): void
    {
        $this->assertSame($expected, (new CheckResult($status))->isBlocking($warningBlocks));
    }

    public static function blockingProvider(): array
    {
        return [
            'rejected always' => ['REJECTED', false, true],
            'warning allowed' => ['WARNING', false, false],
            'warning blocks' => ['WARNING', true, true],
            'approved' => ['APPROVED', true, false],
            'unchecked' => ['UNCHECKED', true, false],
        ];
    }
}
