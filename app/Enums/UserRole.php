<?php

namespace App\Enums;

/**
 * User Role Enum
 *
 * Represents all available user roles in the procurement system.
 * Each role has specific permissions and responsibilities.
 */
enum UserRole: string
{
    case BAC_SECRETARIAT = 'bac_secretariat';
    case BAC_CHAIRMAN = 'bac_chairman';
    case HOPE = 'hope';
    case ADMIN = 'admin';

    /**
     * Get the user-friendly display name for the role
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::BAC_SECRETARIAT => 'BAC Secretariat',
            self::BAC_CHAIRMAN => 'BAC Chairman',
            self::HOPE => 'Head of Procuring Entity',
            self::ADMIN => 'Administrator',
        };
    }

    /**
     * Get a description of the role's responsibilities
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::BAC_SECRETARIAT => 'Manages procurement documentation and administrative tasks',
            self::BAC_CHAIRMAN => 'Leads the Bids and Awards Committee and oversees procurement decisions',
            self::HOPE => 'Approves procurement activities and has final decision authority',
            self::ADMIN => 'Full system access with user and system management capabilities',
        };
    }

    /**
     * Check if the role has administrative privileges
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Check if the role is a BAC member
     */
    public function isBacMember(): bool
    {
        return in_array($this, [
            self::BAC_SECRETARIAT,
            self::BAC_CHAIRMAN,
        ]);
    }

    /**
     * Check if the role has approval authority
     */
    public function hasApprovalAuthority(): bool
    {
        return in_array($this, [
            self::BAC_CHAIRMAN,
            self::HOPE,
            self::ADMIN,
        ]);
    }

    /**
     * Get the hierarchy level (lower number = higher authority)
     */
    public function getHierarchyLevel(): int
    {
        return match ($this) {
            self::ADMIN => 1,
            self::HOPE => 2,
            self::BAC_CHAIRMAN => 3,
            self::BAC_SECRETARIAT => 4,
        };
    }

    /**
     * Get all cases as an array of values
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as an array of display names
     *
     * @return array<string, string> [value => display_name]
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getDisplayName();
        }

        return $options;
    }
}
