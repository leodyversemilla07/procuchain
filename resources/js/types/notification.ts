/**
 * Notification Types
 * Contains interfaces for user notifications and notification filtering
 */

// ============================================================================
// NOTIFICATION
// ============================================================================

export interface Notification {
    id: string;
    type: string;
    notifiable_type: string;
    notifiable_id: number;
    data: {
        title: string;
        message: string;
        pr_number: string;
        procurement_title: string;
        stage_identifier: string;
        current_status: string;
        timestamp: string;
        action_type: string;
        next_stage?: string;
        transition_message?: string;
    };
    read_at: string | null;
    created_at: string;
    updated_at: string;
}

// ============================================================================
// NOTIFICATION FILTERING
// ============================================================================

export type NotificationFilterType = 'all' | 'read' | 'unread';
