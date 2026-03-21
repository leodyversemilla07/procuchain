import type { WorkflowInfo } from '@/types';

export interface StageUploadProcurement {
    pr_number: string;
    title: string;
    status?: string;
    stage_value?: string;
    current_stage?: string;
    delivery_location?: string;
    delivery_date?: string;
    delivery_date_formatted?: string;
    delivery_term_days?: number;
}

export interface StageUploadWorkflowProps {
    procurement: StageUploadProcurement;
    workflowInfo?: WorkflowInfo;
}

export interface ConfirmDialogState {
    open: boolean;
    documentValue: string;
    documentName: string;
}
