<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Test\Unit\Model;

use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\Api\Client;
use WebtechSolutions\TraceLogic\Model\Config;
use WebtechSolutions\TraceLogic\Model\SupplierSync;

class SupplierSyncTest extends TestCase
{
    private function sync(): SupplierSync
    {
        return new SupplierSync(
            $this->createStub(Client::class),
            $this->createStub(Config::class),
            $this->createStub(CollectionFactory::class),
            $this->createStub(Action::class),
            $this->createStub(LoggerInterface::class)
        );
    }

    private function supplier(): array
    {
        return [
            'country_of_production' => 'br',
            'geolocation' => ['type' => 'Point', 'coordinates' => [-54.1, -15.4]],
            'dds_reference_number' => '26EU1234567890DDS1',
            'certificates' => ['FSC-C123456', 'PEFC/00-00-1'],
        ];
    }

    public function testFillsAllEmptyFields(): void
    {
        $changes = $this->sync()->computeChanges(
            ['country_of_origin' => '', 'geolocation' => '', 'dds_reference' => '', 'certificates' => ''],
            $this->supplier()
        );

        $this->assertSame('BR', $changes['country_of_origin']);
        $this->assertSame('{"type":"Point","coordinates":[-54.1,-15.4]}', $changes['geolocation']);
        $this->assertSame('26EU1234567890DDS1', $changes['dds_reference']);
        $this->assertSame('FSC-C123456,PEFC/00-00-1', $changes['certificates']);
    }

    public function testNeverOverwritesExistingValues(): void
    {
        $changes = $this->sync()->computeChanges(
            ['country_of_origin' => 'CO', 'geolocation' => '{"x":1}', 'dds_reference' => 'MINE', 'certificates' => 'FSC-C123456'],
            $this->supplier()
        );

        $this->assertSame(['certificates' => 'FSC-C123456,PEFC/00-00-1'], $changes);
    }

    public function testNoChangesWhenNothingNew(): void
    {
        $changes = $this->sync()->computeChanges(
            ['country_of_origin' => 'BR', 'geolocation' => '{"x":1}', 'dds_reference' => 'D', 'certificates' => 'PEFC/00-00-1, FSC-C123456'],
            $this->supplier()
        );

        $this->assertSame([], $changes);
    }
}
