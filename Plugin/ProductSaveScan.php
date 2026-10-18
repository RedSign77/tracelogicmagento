<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Controller\Adminhtml\Product\Save;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\CatalogScanner;
use WebtechSolutions\TraceLogic\Model\Config;

/**
 * Non-blocking compliance warning after an existing product is saved in the admin.
 */
class ProductSaveScan
{
    public function __construct(
        private readonly Config $config,
        private readonly CatalogScanner $scanner,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function afterExecute(Save $subject, $result)
    {
        $productId = (int)$subject->getRequest()->getParam('id');
        if ($productId === 0) {
            return $result;
        }

        try {
            $product = $this->productRepository->getById($productId);
            foreach ($product->getWebsiteIds() as $websiteId) {
                $websiteId = (int)$websiteId;
                if (!$this->config->isEnabled($websiteId) || !$this->config->scanOnSave($websiteId)) {
                    continue;
                }
                $status = $this->scanner->scanProducts([$productId], $websiteId)[$productId] ?? null;
                if ($status !== null && $status !== CatalogScanner::COMPLIANT) {
                    $this->messageManager->addWarningMessage(
                        (string)__('TraceLogic compliance check: %1 (website %2). See TraceLogic > Compliance report.', $status, $websiteId)
                    );
                }
            }
        } catch (NoSuchEntityException) {
            // Product vanished; nothing to check.
        } catch (\Throwable $e) {
            $this->logger->warning('TraceLogic product check skipped', ['product_id' => $productId, 'error' => $e->getMessage()]);
        }

        return $result;
    }
}
