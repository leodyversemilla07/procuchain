<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Philippine Government Procurement Categories (RA 9184)
 *
 * Reference: Government Procurement Policy Board - Revised IRR of RA 9184
 */
enum ProcurementCategoryEnums: string
{
    case GOODS = 'goods';
    case INFRASTRUCTURE_PROJECTS = 'infrastructure_projects';
    case CONSULTING_SERVICES = 'consulting_services';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::GOODS => 'Goods',
            self::INFRASTRUCTURE_PROJECTS => 'Infrastructure Projects',
            self::CONSULTING_SERVICES => 'Consulting Services',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::GOODS => 'Supplies, materials, equipment, and other movable property',
            self::INFRASTRUCTURE_PROJECTS => 'Construction, repair, or maintenance of roads, buildings, and other structures',
            self::CONSULTING_SERVICES => 'Professional and technical services requiring specialized expertise',
        };
    }
}
