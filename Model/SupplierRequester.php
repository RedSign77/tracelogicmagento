<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use WebtechSolutions\TraceLogic\Model\Api\Client;

class SupplierRequester
{
    public const FIELDS = ['geolocation', 'dds_reference_number', 'country_of_production', 'valid_until'];

    public function __construct(
        private readonly Client $client,
        private readonly Config $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function hasRecentRequest(string $supplierCode, int $days = 7): bool
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('tracelogic_supplier_request'), 'entity_id')
            ->where('supplier_code = ?', $supplierCode)
            ->where('created_at > ?', gmdate('Y-m-d H:i:s', time() - $days * 86400))
            ->limit(1);

        return (bool)$connection->fetchOne($select);
    }

    /**
     * @param list<string> $fields subset of self::FIELDS
     * @return array{magic_link: string, expires_at: string, emailed: bool}
     * @throws \InvalidArgumentException
     * @throws Api\Exception\UnavailableException
     * @throws Api\Exception\RequestException
     */
    public function request(string $supplierCode, string $email, array $fields, int $websiteId): array
    {
        $fields = array_values(array_intersect(self::FIELDS, $fields));
        if ($supplierCode === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $fields === []) {
            throw new \InvalidArgumentException('Supplier code, valid e-mail and at least one field are required.');
        }

        $response = $this->client->requestSupplierData($supplierCode, $email, $fields, $websiteId);
        $link = (string)($response['magic_link'] ?? '');
        $expires = (string)($response['expires_at'] ?? '');

        $this->resource->getConnection()->insert($this->resource->getTableName('tracelogic_supplier_request'), [
            'supplier_code' => $supplierCode,
            'supplier_email' => $email,
            'required_fields' => implode(',', $fields),
            'expires_at' => $expires !== '' ? gmdate('Y-m-d H:i:s', (int)strtotime($expires)) : null,
        ]);

        return ['magic_link' => $link, 'expires_at' => $expires, 'emailed' => $this->send($email, $supplierCode, $link, $expires, $fields, $websiteId)];
    }

    /**
     * @param list<string> $fields
     */
    private function send(string $email, string $code, string $link, string $expires, array $fields, int $websiteId): bool
    {
        $storeId = (int)$this->storeManager->getWebsite($websiteId)->getDefaultStore()->getId();
        $this->inlineTranslation->suspend();
        try {
            $this->transportBuilder
                ->setTemplateIdentifier($this->config->getEmailTemplate($websiteId))
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars([
                    'supplier_code' => $code,
                    'magic_link' => $link,
                    'expires_at' => $expires,
                    'fields' => implode(', ', $fields),
                ])
                ->setFromByScope($this->config->getEmailSender($websiteId), $storeId)
                ->addTo($email)
                ->getTransport()
                ->sendMessage();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('TraceLogic supplier e-mail failed', ['supplier' => $code, 'error' => $e->getMessage()]);

            return false;
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
