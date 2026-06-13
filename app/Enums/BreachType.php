<?php

namespace App\Enums;

/**
 * Breach Type Enum
 *
 * Represents the types of integrity breaches that can be detected
 * when verifying procurement mirror data against the blockchain.
 */
enum BreachType: string
{
    case HASH_MISMATCH = 'hash_mismatch';
    case CONTENT_MISMATCH = 'content_mismatch';
    case UNAUTHORIZED_PUBLISHER = 'unauthorized_publisher';
    case ROW_DELETED = 'row_deleted';
    case USER_ADDRESS_TAMPERED = 'user_address_tampered';
    case UNAUTHORIZED_RECORD = 'unauthorized_record';

    /**
     * Get the user-friendly display name for the breach type
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::HASH_MISMATCH => 'Hash Mismatch',
            self::CONTENT_MISMATCH => 'Content Mismatch',
            self::UNAUTHORIZED_PUBLISHER => 'Unauthorized Publisher',
            self::ROW_DELETED => 'Row Deleted',
            self::USER_ADDRESS_TAMPERED => 'User Address Tampered',
            self::UNAUTHORIZED_RECORD => 'Unauthorized Record',
        };
    }

    /**
     * Get a description of the breach type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::HASH_MISMATCH => 'The SHA-256 hash of the data does not match the stored data_hash, indicating the data has been altered',
            self::CONTENT_MISMATCH => 'The content of the mirrored record does not match the corresponding blockchain entry',
            self::UNAUTHORIZED_PUBLISHER => 'The publisher address is not authorized to publish to this stream',
            self::ROW_DELETED => 'A record that exists on the blockchain has been deleted from the mirror',
            self::USER_ADDRESS_TAMPERED => 'The user address associated with the record has been modified',
            self::UNAUTHORIZED_RECORD => 'A record exists in the database that has no corresponding entry on the blockchain',
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
