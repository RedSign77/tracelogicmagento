<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Test\Unit\Model\Api;

use Laminas\Http\Response;
use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\HTTP\LaminasClientFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\Api\CircuitBreaker;
use WebtechSolutions\TraceLogic\Model\Api\Client;
use WebtechSolutions\TraceLogic\Model\Api\Exception\RequestException;
use WebtechSolutions\TraceLogic\Model\Api\Exception\UnavailableException;
use WebtechSolutions\TraceLogic\Model\Config;

class ClientTest extends TestCase
{
    private LaminasClient|MockObject $http;
    private CircuitBreaker|MockObject $breaker;
    private Client $client;

    protected function setUp(): void
    {
        $this->http = $this->createMock(LaminasClient::class);
        $factory = $this->createStub(LaminasClientFactory::class);
        $factory->method('create')->willReturn($this->http);

        $config = $this->createStub(Config::class);
        $config->method('getBaseUrl')->willReturn('https://gx.test');
        $config->method('getApiKey')->willReturn('sec_live_x');
        $config->method('getTimeout')->willReturn(2.0);

        $this->breaker = $this->createMock(CircuitBreaker::class);
        $this->client = new Client(
            $factory,
            new Json(),
            $config,
            $this->breaker,
            $this->createStub(LoggerInterface::class)
        );
    }

    private function respond(int $status, string $body): void
    {
        $response = new Response();
        $response->setStatusCode($status);
        $response->setContent($body);
        $this->http->method('send')->willReturn($response);
    }

    public function testComplianceCheckSendsBearerAndParsesJson(): void
    {
        $this->respond(200, '{"status":"APPROVED"}');
        $this->http->expects($this->once())->method('setUri')->with('https://gx.test/v1/checkout/compliance-check');
        $this->http->expects($this->once())->method('setHeaders')->with($this->callback(
            static fn(array $h) => $h['Authorization'] === 'Bearer sec_live_x'
        ));
        $this->breaker->expects($this->once())->method('recordSuccess');

        $this->assertSame(['status' => 'APPROVED'], $this->client->complianceCheck(['order_id' => 'A']));
    }

    public function testServerErrorIsUnavailableAndTripsBreaker(): void
    {
        $this->respond(503, '');
        $this->breaker->expects($this->once())->method('recordFailure');

        $this->expectException(UnavailableException::class);
        $this->client->complianceCheck([]);
    }

    public function testClientErrorIsRequestExceptionWithoutBreaker(): void
    {
        $this->respond(422, '{"title":"invalid"}');
        $this->breaker->expects($this->never())->method('recordFailure');

        $this->expectException(RequestException::class);
        $this->client->complianceCheck([]);
    }

    public function testOpenBreakerShortCircuits(): void
    {
        $this->breaker->method('isOpen')->willReturn(true);
        $this->http->expects($this->never())->method('send');

        $this->expectException(UnavailableException::class);
        $this->client->complianceCheck([]);
    }

    public function testValidateProductsChunksBatches(): void
    {
        $this->respond(200, '{"results":[{"sku":"a","is_compliant":true}]}');
        $products = array_fill(0, 201, ['sku' => 'a']);

        $this->http->expects($this->exactly(2))->method('send');

        $this->assertCount(2, $this->client->validateProducts($products));
    }
}
