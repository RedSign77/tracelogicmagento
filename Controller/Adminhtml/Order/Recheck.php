<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Api\OrderRepositoryInterface;
use WebtechSolutions\TraceLogic\Model\OrderRechecker;

class Recheck extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'WebtechSolutions_TraceLogic::order_check';

    public function __construct(
        Action\Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderRechecker $rechecker
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        try {
            $result = $this->rechecker->recheck($this->orderRepository->get($orderId));
            $this->messageManager->addSuccessMessage(__('TraceLogic re-check: %1', $result->getStatus()));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('TraceLogic re-check failed: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
