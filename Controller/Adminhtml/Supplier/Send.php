<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Controller\Adminhtml\Supplier;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use WebtechSolutions\TraceLogic\Model\SupplierRequester;

class Send extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'WebtechSolutions_TraceLogic::supplier';

    public function __construct(Action\Context $context, private readonly SupplierRequester $requester)
    {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $request = $this->getRequest();
        try {
            $result = $this->requester->request(
                trim((string)$request->getParam('supplier_code')),
                trim((string)$request->getParam('supplier_email')),
                (array)$request->getParam('fields', []),
                (int)$request->getParam('website_id')
            );
            if ($result['emailed']) {
                $this->messageManager->addSuccessMessage(__('Request e-mail sent. Link valid until %1.', $result['expires_at']));
            } else {
                $this->messageManager->addWarningMessage(
                    __('The e-mail could not be sent. Send this link manually: %1', $result['magic_link'])
                );
            }
        } catch (\InvalidArgumentException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('TraceLogic request failed: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
