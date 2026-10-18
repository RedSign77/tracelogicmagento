<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

/**
 * Outcome of a compliance check (checkout or re-check).
 */
class CheckResult
{
    public const APPROVED = 'APPROVED';
    public const WARNING = 'WARNING';
    public const REJECTED = 'REJECTED';
    public const UNCHECKED = 'UNCHECKED';
    public const SKIPPED = 'SKIPPED';

    /**
     * @param list<array{code: string, severity: string, message: string, source: string, sku: string}> $flags
     */
    public function __construct(
        private readonly string $status,
        private readonly ?int $score = null,
        private readonly ?string $token = null,
        private readonly array $flags = []
    ) {
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function fromResponse(array $response): self
    {
        $flags = [];
        foreach (($response['checks'] ?? []) as $source => $check) {
            foreach (($check['flags'] ?? []) as $flag) {
                $flags[] = [
                    'code' => (string)($flag['code'] ?? ''),
                    'severity' => (string)($flag['severity'] ?? ''),
                    'message' => (string)($flag['message'] ?? ''),
                    'source' => (string)$source,
                    'sku' => (string)($flag['sku'] ?? ''),
                ];
            }
        }

        return new self(
            (string)($response['status'] ?? self::UNCHECKED),
            isset($response['compliance_score']) ? (int)$response['compliance_score'] : null,
            isset($response['audit_token']) ? (string)$response['audit_token'] : null,
            $flags
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['status'], $data['score'], $data['token'], $data['flags']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'score' => $this->score, 'token' => $this->token, 'flags' => $this->flags];
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @return list<array{code: string, severity: string, message: string, source: string, sku: string}>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    public function isBlocking(bool $warningBlocks): bool
    {
        return $this->status === self::REJECTED || ($warningBlocks && $this->status === self::WARNING);
    }
}
