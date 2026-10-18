<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const PATH = 'tracelogic/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?int $websiteId = null): bool
    {
        return $this->flag('general/enabled', $websiteId)
            && $this->getBaseUrl($websiteId) !== ''
            && $this->getApiKey($websiteId) !== '';
    }

    public function getBaseUrl(?int $websiteId = null): string
    {
        return rtrim(trim((string)$this->value('general/base_url', $websiteId)), '/');
    }

    public function getApiKey(?int $websiteId = null): string
    {
        $raw = (string)$this->value('general/api_key', $websiteId);

        return $raw === '' ? '' : trim($this->encryptor->decrypt($raw));
    }

    public function getTimeout(?int $websiteId = null): float
    {
        return max(0.1, (float)$this->value('general/timeout', $websiteId));
    }

    public function verifySsl(?int $websiteId = null): bool
    {
        return $this->flag('general/verify_ssl', $websiteId);
    }

    public function isFailOpen(?int $websiteId = null): bool
    {
        return $this->flag('general/fail_open', $websiteId);
    }

    /**
     * True: a rejected order is placed and held until the supplier data arrives; false: checkout is blocked.
     */
    public function holdOnRejected(?int $websiteId = null): bool
    {
        return (string)$this->value('general/on_rejected', $websiteId) === 'hold';
    }

    public function warningBlocks(?int $websiteId = null): bool
    {
        return $this->flag('general/warning_blocks', $websiteId);
    }

    public function scanOnSave(?int $websiteId = null): bool
    {
        return $this->flag('general/scan_on_save', $websiteId);
    }

    public function getCircuitThreshold(?int $websiteId = null): int
    {
        return max(1, (int)$this->value('general/circuit_threshold', $websiteId));
    }

    public function getCircuitPauseSeconds(?int $websiteId = null): int
    {
        return max(1, (int)$this->value('general/circuit_pause', $websiteId)) * 60;
    }

    /**
     * Product attribute code mapped to a compliance field (hs_code, supplier_code, ...).
     */
    public function getAttributeCode(string $field, ?int $websiteId = null): string
    {
        return trim((string)$this->value('attributes/' . $field, $websiteId));
    }

    public function autoRequestSuppliers(?int $websiteId = null): bool
    {
        return $this->flag('supplier/auto_request', $websiteId);
    }

    public function getEmailSender(?int $websiteId = null): string
    {
        return (string)$this->value('supplier/email_sender', $websiteId);
    }

    public function getEmailTemplate(?int $websiteId = null): string
    {
        return (string)$this->value('supplier/email_template', $websiteId);
    }

    private function flag(string $path, ?int $websiteId): bool
    {
        return (bool)$this->value($path, $websiteId);
    }

    private function value(string $path, ?int $websiteId): mixed
    {
        return $websiteId === null
            ? $this->scopeConfig->getValue(self::PATH . $path)
            : $this->scopeConfig->getValue(self::PATH . $path, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }
}
