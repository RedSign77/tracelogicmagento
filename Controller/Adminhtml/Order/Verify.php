<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Api\OrderRepositoryInterface;
use WebtechSolutions\TraceLogic\Model\Api\Client;

class Verify extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'WebtechSolutions_TraceLogic::order_check';

    public function __construct(
        Action\Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Client $client
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        try {
            $order = $this->orderRepository->get($orderId);
            $token = (string)$order->getData('tracelogic_audit_token');
            $audit = $this->client->getAudit($token, (int)$order->getStore()->getWebsiteId());
            $integrity = (string)($audit['integrity'] ?? 'UNKNOWN');
            $message = __('TraceLogic audit %1: integrity %2 (chain #%3, status %4).', $token, $integrity, $audit['chain_index'] ?? '-', $audit['status'] ?? '-');
            $integrity === 'VALID'
                ? $this->messageManager->addSuccessMessage($message)
                : $this->messageManager->addErrorMessage($message);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('TraceLogic audit lookup failed: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
