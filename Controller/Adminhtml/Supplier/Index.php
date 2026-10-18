<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Controller\Adminhtml\Supplier;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'WebtechSolutions_TraceLogic::supplier';

    public function __construct(Action\Context $context, private readonly PageFactory $pageFactory)
    {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('WebtechSolutions_TraceLogic::supplier');
        $page->getConfig()->getTitle()->prepend(__('TraceLogic Supplier data request'));

        return $page;
    }
}
