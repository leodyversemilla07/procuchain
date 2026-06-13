<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Philippine Government Procurement Categories (RA 12009 - NGPA)
 *
 * Reference: NGPA IRR Rule I, Section 5 - Definition of Terms
 *
 * Note: Under NGPA, "General Support Services" are technically included under Goods (Section 5.n.ii),
 * but are separated here for practical procurement document requirements.
 */
enum ProcurementCategory: string
{
    case GOODS = 'goods';
    case SERVICES = 'services';
    case INFRASTRUCTURE_PROJECTS = 'infrastructure_projects';
    case CONSULTING_SERVICES = 'consulting_services';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::GOODS => 'Goods',
            self::SERVICES => 'General Support Services',
            self::INFRASTRUCTURE_PROJECTS => 'Infrastructure Projects',
            self::CONSULTING_SERVICES => 'Consulting Services',
        };
    }

    /**
     * Alias for getDisplayName() for convenience
     */
    public function label(): string
    {
        return $this->getDisplayName();
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::GOODS => 'All items, supplies, materials, equipment, furniture, stationery, and personal property needed in the transaction of public businesses (NGPA IRR Section 5.n.i)',
            self::SERVICES => 'General support services such as repair and maintenance of equipment, trucking, hauling, janitorial, security, and related services (NGPA IRR Section 5.n.ii)',
            self::INFRASTRUCTURE_PROJECTS => 'Construction, improvement, rehabilitation, demolition, repair, restoration, or maintenance of roads, bridges, buildings, and other civil works (NGPA IRR Section 5.r)',
            self::CONSULTING_SERVICES => 'Services requiring adequate external technical and professional expertise beyond the capability of the government, such as advisory, feasibility studies, design, and construction supervision (NGPA IRR Section 5.i)',
        };
    }
}
