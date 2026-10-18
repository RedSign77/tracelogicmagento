<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model\Api;

use Laminas\Http\Request;
use Magento\Framework\HTTP\LaminasClientFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\Api\Exception\RequestException;
use WebtechSolutions\TraceLogic\Model\Api\Exception\UnavailableException;
use WebtechSolutions\TraceLogic\Model\Config;

class Client
{
    public const BATCH_LIMIT = 200;

    public function __construct(
        private readonly LaminasClientFactory $clientFactory,
        private readonly Json $json,
        private readonly Config $config,
        private readonly CircuitBreaker $breaker,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function complianceCheck(array $payload, ?int $websiteId = null): array
    {
        return $this->request(Request::METHOD_POST, '/v1/checkout/compliance-check', $payload, $websiteId);
    }

    /**
     * @param list<array<string, mixed>> $products
     * @return list<array<string, mixed>> per-product results, each with "sku"
     */
    public function validateProducts(array $products, ?int $websiteId = null): array
    {
        $results = [];
        foreach (array_chunk($products, self::BATCH_LIMIT) as $chunk) {
            $response = $this->request(
                Request::METHOD_POST,
                '/v1/eudr/validate-product',
                ['products' => array_values($chunk)],
                $websiteId
            );
            foreach ($response['results'] ?? [] as $result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param list<string> $certificates
     * @return array<string, mixed>
     */
    public function scanText(string $text, array $certificates = [], ?int $websiteId = null): array
    {
        return $this->request(
            Request::METHOD_POST,
            '/v1/green-claims/scan-text',
            ['text' => mb_substr($text, 0, 20000), 'linked_certificates' => $certificates],
            $websiteId
        );
    }

    /**
     * @param list<string> $requiredFields
     * @return array<string, mixed> magic_link, expires_at
     */
    public function requestSupplierData(
        string $supplierCode,
        string $supplierEmail,
        array $requiredFields,
        ?int $websiteId = null
    ): array {
        return $this->request(Request::METHOD_POST, '/v1/suppliers/request-data', [
            'supplier_code' => $supplierCode,
            'supplier_email' => $supplierEmail,
            'required_fields' => array_values($requiredFields),
        ], $websiteId);
    }

    /**
     * Supplier declaration and trusted certificates, or null when TraceLogic does not know the supplier.
     *
     * @return array<string, mixed>|null
     */
    public function getSupplier(string $supplierCode, ?int $websiteId = null): ?array
    {
        try {
            return $this->request(Request::METHOD_GET, '/v1/suppliers/' . rawurlencode($supplierCode), null, $websiteId);
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getAudit(string $token, ?int $websiteId = null): array
    {
        return $this->request(Request::METHOD_GET, '/v1/audit/' . rawurlencode($token), null, $websiteId);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     * @throws UnavailableException
     * @throws RequestException
     */
    private function request(string $method, string $path, ?array $payload, ?int $websiteId): array
    {
        if ($this->breaker->isOpen($websiteId)) {
            throw new UnavailableException('TraceLogic circuit breaker is open.');
        }

        try {
            $client = $this->clientFactory->create();
            $client->setUri($this->config->getBaseUrl($websiteId) . $path);
            $client->setMethod($method);
            $client->setHeaders([
                'Authorization' => 'Bearer ' . $this->config->getApiKey($websiteId),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
            $client->setOptions([
                'timeout' => $this->config->getTimeout($websiteId),
                'maxredirects' => 0,
                'verifypeer' => $this->config->verifySsl($websiteId),
                'verifyhost' => $this->config->verifySsl($websiteId) ? 2 : 0,
            ]);
            if ($payload !== null) {
                $client->setRawBody($this->json->serialize($payload));
            }
            $response = $client->send();
            $status = $response->getStatusCode();
            $body = $response->getBody();
        } catch (\Throwable $e) {
            $this->breaker->recordFailure($websiteId);
            $this->logger->warning('TraceLogic request failed', ['path' => $path, 'error' => $e->getMessage()]);
            throw new UnavailableException('TraceLogic request failed: ' . $e->getMessage(), 0, $e);
        }

        if ($status >= 500 || $status === 429) {
            $this->breaker->recordFailure($websiteId);
            $this->logger->warning('TraceLogic unavailable', ['path' => $path, 'status' => $status]);
            throw new UnavailableException('TraceLogic answered HTTP ' . $status);
        }

        if ($status >= 400) {
            $this->logger->error('TraceLogic rejected request', ['path' => $path, 'status' => $status]);
            throw new RequestException('TraceLogic answered HTTP ' . $status . ': ' . mb_substr((string)$body, 0, 500), $status);
        }

        $this->breaker->recordSuccess($websiteId);

        try {
            $data = $this->json->unserialize($body);
        } catch (\InvalidArgumentException $e) {
            throw new UnavailableException('TraceLogic returned invalid JSON.', 0, $e);
        }

        return is_array($data) ? $data : [];
    }
}
