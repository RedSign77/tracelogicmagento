<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\Api\Exception\UnavailableException;

/**
 * Re-runs the compliance check for a placed order and holds it when rejected.
 */
class OrderRechecker
{
    public function __construct(
        private readonly CheckoutValidator $validator,
        private readonly RequestMapper $mapper,
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Json $json,
        private readonly SupplierNotifier $notifier,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws UnavailableException when TraceLogic cannot be reached (order stays UNCHECKED)
     */
    public function recheck(Order $order): CheckResult
    {
        $websiteId = (int)$order->getStore()->getWebsiteId();
        $result = $this->validator->validate(
            (string)$order->getIncrementId(),
            (string)$order->getOrderCurrencyCode(),
            $this->mapper->orderLines($order),
            $websiteId,
            (int)$order->getStoreId(),
            false
        );

        if ($result->getStatus() === CheckResult::UNCHECKED) {
            throw new UnavailableException('TraceLogic unavailable for order ' . $order->getIncrementId());
        }

        $wasBlocking = in_array($order->getData('tracelogic_status'), [CheckResult::REJECTED, CheckResult::WARNING], true);
        $order->setData('tracelogic_status', $result->getStatus());
        $order->setData('tracelogic_score', $result->getScore());
        $order->setData('tracelogic_audit_token', $result->getToken());
        $order->setData('tracelogic_flags', $this->json->serialize($result->getFlags()));

        if ($result->isBlocking($this->config->warningBlocks($websiteId)) && $order->canHold()) {
            $order->hold();
            $order->addCommentToStatusHistory(
                (string)__('Held by TraceLogic compliance re-check (%1). Token: %2', $result->getStatus(), $result->getToken())
            );
            $this->notifier->notify(
                $result,
                $this->mapper->orderLines($order),
                $websiteId,
                (int)$order->getStoreId()
            );
            $this->logger->warning('TraceLogic held order', ['order' => $order->getIncrementId()]);
        }

        if (
            $wasBlocking
            && !$result->isBlocking($this->config->warningBlocks($websiteId))
            && $order->getState() === Order::STATE_HOLDED
            && $order->canUnhold()
        ) {
            $order->unhold();
            $order->addCommentToStatusHistory(
                (string)__('Released by TraceLogic compliance re-check (%1). Token: %2', $result->getStatus(), $result->getToken())
            );
            $this->logger->info('TraceLogic released order', ['order' => $order->getIncrementId()]);
        }

        $this->orderRepository->save($order);

        return $result;
    }
}
