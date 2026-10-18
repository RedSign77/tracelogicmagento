<?php
/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
declare(strict_types=1);

namespace WebtechSolutions\TraceLogic\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddProductAttributes implements DataPatchInterface
{
    private const GROUP = 'TraceLogic Compliance';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributes = [
            'tracelogic_hs_code' => ['HS code', 'varchar', 'text', 10],
            'tracelogic_supplier_code' => ['Supplier code', 'varchar', 'text', 20],
            'tracelogic_certificates' => ['Certificates (comma separated)', 'varchar', 'text', 30],
            'tracelogic_country_of_origin' => ['Country of origin (ISO-2)', 'varchar', 'text', 40],
            'tracelogic_species' => ['Scientific species name', 'varchar', 'text', 50],
            'tracelogic_geolocation' => ['Geolocation (GeoJSON)', 'text', 'textarea', 60],
            'tracelogic_plot_area' => ['Plot area (hectares)', 'decimal', 'text', 70],
            'tracelogic_dds_reference' => ['DDS reference number', 'varchar', 'text', 80],
        ];

        foreach ($attributes as $code => [$label, $type, $input, $sort]) {
            $eavSetup->addAttribute(Product::ENTITY, $code, [
                'type' => $type,
                'label' => $label,
                'input' => $input,
                'required' => false,
                'user_defined' => true,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'group' => self::GROUP,
                'sort_order' => $sort,
            ]);
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
