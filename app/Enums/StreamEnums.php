<?php

namespace App\Enums;

/**
 * Stream Enum
 *
 * Represents blockchain stream types used for organizing
 * different categories of procurement data on the blockchain.
 */
enum StreamEnums: string
{
    case DOCUMENTS = 'procurement.documents';
    case STATUS = 'procurement.status';
    case EVENTS = 'procurement.events';
    case CORRECTIONS = 'procurement.corrections';
    case FILE_DATA = 'file.data';
    case FILE_METADATA = 'file.metadata';

    /**
     * Get the user-friendly display name for the stream
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::DOCUMENTS => 'Procurement Documents',
            self::STATUS => 'Procurement Status',
            self::EVENTS => 'Procurement Events',
            self::CORRECTIONS => 'Procurement Corrections',
            self::FILE_DATA => 'File Data',
            self::FILE_METADATA => 'File Metadata',
        };
    }

    /**
     * Get a description of the stream's purpose
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::DOCUMENTS => 'Stores procurement documents and their metadata',
            self::STATUS => 'Tracks status changes throughout the procurement process',
            self::EVENTS => 'Records procurement events and activities',
            self::CORRECTIONS => 'Maintains correction records and amendments',
            self::FILE_DATA => 'Stores raw file data and binary content',
            self::FILE_METADATA => 'Stores file metadata, hashes, and storage information',
        };
    }

    /**
     * Check if the stream is procurement-related
     */
    public function isProcurementStream(): bool
    {
        return in_array($this, [
            self::DOCUMENTS,
            self::STATUS,
            self::EVENTS,
            self::CORRECTIONS,
        ]);
    }

    /**
     * Check if the stream stores file content
     */
    public function isFileStream(): bool
    {
        return in_array($this, [
            self::FILE_DATA,
            self::FILE_METADATA,
        ]);
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
