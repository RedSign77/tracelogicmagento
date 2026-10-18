<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Test\Unit\Model;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\TestCase;
use WebtechSolutions\TraceLogic\Model\CartAssessor;
use WebtechSolutions\TraceLogic\Model\CheckoutValidator;
use WebtechSolutions\TraceLogic\Model\CheckResult;
use WebtechSolutions\TraceLogic\Model\Config;
use WebtechSolutions\TraceLogic\Model\RequestMapper;

class CartAssessorTest extends TestCase
{
    public function testDescribesReasonsPerItemWithoutInternalDetails(): void
    {
        $item = $this->createStub(Item::class);
        $item->method('getSku')->willReturn('GX-OAK');
        $item->method('getName')->willReturn('Oak Flooring');
        $quote = $this->createStub(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item]);

        $flag = static fn(string $code, string $sev, string $sku) => [
            'code' => $code, 'severity' => $sev, 'message' => 'Supplier SUP-1 secret', 'source' => 'eudr', 'sku' => $sku,
        ];
        $result = new CheckResult('REJECTED', 10, 't', [
            $flag('EUDR_MISSING_GEOLOCATION', 'CRITICAL', 'GX-OAK'),
            $flag('EUDR_MISSING_DDS', 'CRITICAL', 'GX-OAK'),
            $flag('GC_VAGUE_ENV_CLAIM', 'HIGH', 'GX-OAK'),
            $flag('GC_UNVERIFIED_CLAIM', 'MEDIUM', 'GX-OAK'),
            $flag('EUDR_DDS_UNVERIFIED', 'LOW', 'GX-OAK'),
        ]);

        $assessor = new CartAssessor(
            $this->createStub(Config::class),
            $this->createStub(RequestMapper::class),
            $this->createStub(CheckoutValidator::class)
        );
        $describe = new \ReflectionMethod($assessor, 'describeItems');
        $items = $describe->invoke($assessor, $quote, $result);

        $this->assertCount(1, $items);
        $this->assertSame('Oak Flooring', $items[0]['name']);
        $this->assertCount(3, $items[0]['reasons']);
        $this->assertStringNotContainsString('SUP-1', implode(' ', $items[0]['reasons']));
    }
}
