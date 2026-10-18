<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Block\Adminhtml\Order;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;

class Info extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Json $json,
        private readonly ProductResource $productResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?Order
    {
        return $this->registry->registry('current_order');
    }

    public function hasCheck(): bool
    {
        $order = $this->getOrder();

        return $order !== null && (string)$order->getData('tracelogic_status') !== '';
    }

    public function getStatus(): string
    {
        return (string)$this->getOrder()->getData('tracelogic_status');
    }

    public function getScore(): string
    {
        $score = $this->getOrder()->getData('tracelogic_score');

        return $score === null ? '-' : (string)$score;
    }

    public function getToken(): string
    {
        return (string)$this->getOrder()->getData('tracelogic_audit_token');
    }

    /**
     * @return list<array{code: string, severity: string, message: string, sku?: string}>
     */
    public function getFlags(): array
    {
        $raw = (string)$this->getOrder()->getData('tracelogic_flags');
        if ($raw === '') {
            return [];
        }
        try {
            return (array)$this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    /**
     * Admin product edit URL for a SKU, or null when the product no longer exists.
     */
    public function getProductUrl(string $sku): ?string
    {
        $id = $sku !== '' ? $this->productResource->getIdBySku($sku) : false;

        return $id ? $this->getUrl('catalog/product/edit', ['id' => $id]) : null;
    }

    public function canCheck(): bool
    {
        return $this->_authorization->isAllowed('WebtechSolutions_TraceLogic::order_check');
    }

    public function getVerifyUrl(): string
    {
        return $this->getUrl('tracelogic/order/verify', ['order_id' => $this->getOrder()->getId()]);
    }

    public function getRecheckUrl(): string
    {
        return $this->getUrl('tracelogic/order/recheck', ['order_id' => $this->getOrder()->getId()]);
    }
}
