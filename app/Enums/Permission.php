<?php

namespace App\Enums;

/**
 * Permission Enum
 *
 * Centralizes all permission strings used with Spatie Permission.
 * Prevents typos and provides IDE autocomplete for permission checks.
 */
enum Permission: string
{
    // User management
    case MANAGE_USERS = 'manage users';
    case CREATE_USERS = 'create users';
    case EDIT_USERS = 'edit users';
    case DELETE_USERS = 'delete users';
    case ASSIGN_ROLES = 'assign roles';

    // Procurement
    case VIEW_PROCUREMENT = 'view procurement';
    case CREATE_PROCUREMENT = 'create procurement';
    case EDIT_PROCUREMENT = 'edit procurement';
    case DELETE_PROCUREMENT = 'delete procurement';
    case APPROVE_PROCUREMENT = 'approve procurement';
    case MANAGE_PROCUREMENTS = 'manage procurements';

    // Documents
    case VIEW_DOCUMENTS = 'view documents';
    case DOWNLOAD_DOCUMENTS = 'download documents';
    case UPLOAD_DOCUMENTS = 'upload documents';
    case DELETE_DOCUMENTS = 'delete documents';

    // Blockchain
    case VIEW_BLOCKCHAIN_TRANSACTIONS = 'view blockchain transactions';
    case PUBLISH_TO_BLOCKCHAIN = 'publish to blockchain';

    // Settings
    case VIEW_SETTINGS = 'view settings';
    case MANAGE_SETTINGS = 'manage settings';

    // Notifications
    case SEND_NOTIFICATIONS = 'send notifications';

    // Dashboard views
    case VIEW_ADMIN_DASHBOARD = 'view admin dashboard';
    case VIEW_BAC_SECRETARIAT_DASHBOARD = 'view bac-secretariat dashboard';
    case VIEW_BAC_CHAIRMAN_DASHBOARD = 'view bac-chairman dashboard';
    case VIEW_HOPE_DASHBOARD = 'view hope dashboard';

    // Procurement stage management
    case MANAGE_PROCUREMENT_INITIATION = 'manage procurement initiation';
    case MANAGE_PRE_PROCUREMENT_CONFERENCE = 'manage pre-procurement conference';
    case MANAGE_BIDDING_DOCUMENTS = 'manage bidding documents';
    case MANAGE_PRE_BID_CONFERENCE = 'manage pre-bid conference';
    case MANAGE_SUPPLEMENTAL_BID_BULLETIN = 'manage supplemental bid bulletin';
    case MANAGE_BID_OPENING = 'manage bid opening';
    case MANAGE_BID_EVALUATION = 'manage bid evaluation';
    case MANAGE_POST_QUALIFICATION = 'manage post-qualification';
    case MANAGE_BAC_RESOLUTION = 'manage bac resolution';
    case MANAGE_NOTICE_OF_AWARD = 'manage notice of award';
    case MANAGE_PERFORMANCE_BOND_CONTRACT_PO = 'manage performance bond contract po';
    case MANAGE_NOTICE_TO_PROCEED = 'manage notice to proceed';
    case MANAGE_MONITORING = 'manage monitoring';
    case MANAGE_COMPLETION = 'manage completion';

    /**
     * Get all cases as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
