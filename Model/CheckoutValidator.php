<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\Api\Client;
use WebtechSolutions\TraceLogic\Model\Api\Exception\RequestException;
use WebtechSolutions\TraceLogic\Model\Api\Exception\UnavailableException;

class CheckoutValidator
{
    private const CACHE_TTL = 120;

    public function __construct(
        private readonly Client $client,
        private readonly RequestMapper $mapper,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * SKIPPED when no line carries compliance data, UNCHECKED when TraceLogic failed.
     *
     * @param list<array{product_id: int, sku: string, qty: float}> $lines
     */
    public function validate(
        string $orderRef,
        string $currency,
        array $lines,
        int $websiteId,
        int $storeId,
        bool $useCache = true,
        bool $preview = false
    ): CheckResult {
        $payload = $this->mapper->checkoutPayload($orderRef, $currency, $lines, $websiteId, $storeId);
        if ($payload === null) {
            return new CheckResult(CheckResult::SKIPPED);
        }

        // Previews (cart page) share a result per cart content, real checks are keyed by order reference.
        $keyPayload = $preview ? array_diff_key($payload, ['order_id' => 1]) : $payload;
        $cacheKey = 'tracelogic_check_' . ($preview ? 'preview_' : '') . $websiteId . '_'
            . hash('sha256', $this->json->serialize($keyPayload));
        if ($useCache && ($cached = $this->cache->load($cacheKey))) {
            return CheckResult::fromArray($this->json->unserialize($cached));
        }

        try {
            $result = CheckResult::fromResponse($this->client->complianceCheck($payload, $websiteId));
        } catch (UnavailableException | RequestException $e) {
            $this->logger->error('TraceLogic checkout check unavailable', ['order' => $orderRef, 'error' => $e->getMessage()]);
            return new CheckResult(CheckResult::UNCHECKED);
        }

        $this->logger->info('TraceLogic checkout check', [
            'order' => $orderRef,
            'status' => $result->getStatus(),
            'token' => $result->getToken(),
            'flags' => array_column($result->getFlags(), 'code'),
        ]);
        $this->cache->save($this->json->serialize($result->toArray()), $cacheKey, [], self::CACHE_TTL);

        return $result;
    }
}
