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
    case METADATA = 'procurement.metadata';
    case DOCUMENTS = 'procurement.documents';
    case STATUS = 'procurement.status';
    case EVENTS = 'procurement.events';
    case CORRECTIONS = 'procurement.corrections';
    case PROCUREMENTS_CORRECTIONS = 'procurement.metadata.corrections';
    case FILE_DATA = 'file.data';
    case FILE_METADATA = 'file.metadata';
    case FILE_CHUNKS = 'file.chunks';
    case ARCHIVE = 'procurement.archive';
    case USER_REGISTRATIONS = 'user.registrations';
    case INTEGRITY_VIOLATIONS = 'integrity.violations';
    case AUDIT_TRAIL = 'audit.trail';
    case DOCUMENT_ACCESS = 'document.access';
    case CONFIG_WORKFLOWS = 'config.workflows';
    case CONFIG_STAGE_DOCS = 'config.stage_docs';
    case USER_LOGIN_SESSIONS = 'user.login_sessions';

    /**
     * Get the user-friendly display name for the stream
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::METADATA => 'Procurement Metadata',
            self::DOCUMENTS => 'Procurement Documents',
            self::STATUS => 'Procurement Status',
            self::EVENTS => 'Procurement Events',
            self::CORRECTIONS => 'Procurement Corrections',
            self::PROCUREMENTS_CORRECTIONS => 'Procurement Metadata Corrections',
            self::FILE_DATA => 'File Data',
            self::FILE_METADATA => 'File Metadata',
            self::FILE_CHUNKS => 'File Chunks',
            self::ARCHIVE => 'Procurement Archive',
            self::USER_REGISTRATIONS => 'User Registrations',
            self::INTEGRITY_VIOLATIONS => 'Integrity Violations',
            self::AUDIT_TRAIL => 'Audit Trail',
            self::DOCUMENT_ACCESS => 'Document Access',
            self::CONFIG_WORKFLOWS => 'Workflow Configurations',
            self::CONFIG_STAGE_DOCS => 'Stage Document Configurations',
            self::USER_LOGIN_SESSIONS => 'User Login Sessions',
        };
    }

    /**
     * Get a description of the stream's purpose
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::METADATA => 'Stores core procurement metadata and details',
            self::DOCUMENTS => 'Stores procurement documents and their metadata',
            self::STATUS => 'Tracks status changes throughout the procurement process',
            self::EVENTS => 'Records procurement events and activities',
            self::CORRECTIONS => 'Maintains correction records and amendments',
            self::PROCUREMENTS_CORRECTIONS => 'Maintains corrections to procurement metadata',
            self::FILE_DATA => 'Stores raw file data and binary content',
            self::FILE_METADATA => 'Stores file metadata, hashes, and storage information',
            self::FILE_CHUNKS => 'Stores chunked file data for large files',
            self::ARCHIVE => 'Tracks archived status of procurements',
            self::USER_REGISTRATIONS => 'Records user registration events on the blockchain',
            self::INTEGRITY_VIOLATIONS => 'Permanent append-only audit trail of all integrity violations and recovery operations',
            self::AUDIT_TRAIL => 'Immutable record of all user actions (create, update, delete) for accountability',
            self::DOCUMENT_ACCESS => 'Immutable record of who accessed which documents and when',
            self::CONFIG_WORKFLOWS => 'Immutable record of workflow configuration changes',
            self::CONFIG_STAGE_DOCS => 'Immutable record of stage document requirement changes',
            self::USER_LOGIN_SESSIONS => 'Immutable record of user login and logout events for forensics',
        };
    }

    /**
     * Check if the stream is procurement-related
     */
    public function isProcurementStream(): bool
    {
        return in_array($this, [
            self::METADATA,
            self::DOCUMENTS,
            self::STATUS,
            self::EVENTS,
            self::CORRECTIONS,
            self::PROCUREMENTS_CORRECTIONS,
            self::ARCHIVE,
        ]);
    }

    /**
     * Check if the stream is user-related
     */
    public function isUserStream(): bool
    {
        return in_array($this, [
            self::USER_REGISTRATIONS,
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
            self::FILE_CHUNKS,
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

    /**
     * Get all integrity-related stream cases.
     *
     * @return list<self>
     */
    public static function integrityStreams(): array
    {
        return [self::INTEGRITY_VIOLATIONS];
    }

    /**
     * Get all audit/compliance stream cases that require blockchain backing.
     *
     * @return list<self>
     */
    public static function auditStreams(): array
    {
        return [
            self::AUDIT_TRAIL,
            self::DOCUMENT_ACCESS,
            self::CONFIG_WORKFLOWS,
            self::CONFIG_STAGE_DOCS,
            self::USER_LOGIN_SESSIONS,
        ];
    }
}
