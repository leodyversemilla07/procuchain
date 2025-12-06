/**
 * Workflow Types
 *
 * Types for procurement workflow display, mode information,
 * and stage progress tracking per NGPA IRR.
 */

export interface WorkflowStage {
    value: string;
    display_name: string;
    is_optional: boolean;
    is_current: boolean;
    is_completed: boolean;
}

export interface ProcurementMode {
    value: string;
    display_name: string;
    description: string;
    irr_section: string;
}

export interface WorkflowProgress {
    stages: WorkflowStage[];
    total_stages: number;
    current_index: number;
    progress_percentage: number;
}

export interface WorkflowInfo {
    mode: ProcurementMode | null;
    workflow: WorkflowProgress;
}

/**
 * Server-provided action configuration
 */
export type ActionType = 'view' | 'upload' | 'dialog' | 'skip' | 'verify' | 'corrections';

export type ActionVariant =
    | 'default'
    | 'blue'
    | 'green'
    | 'indigo'
    | 'amber'
    | 'purple'
    | 'cyan'
    | 'teal'
    | 'emerald'
    | 'warning'
    | 'success'
    | 'outline';

export type ActionIcon =
    | 'upload'
    | 'edit'
    | 'chart'
    | 'eye'
    | 'shield-check'
    | 'alert-circle'
    | 'skip';

export interface ProcurementAction {
    type: ActionType;
    label: string;
    icon: ActionIcon;
    variant: ActionVariant;
    href?: string;
    action?: 'pre-procurement' | 'pre-bid' | 'supplemental-bid-bulletin';
    is_optional?: boolean;
}

export interface ProcurementActions {
    workflow_actions: ProcurementAction[];
    static_actions: ProcurementAction[];
}
