import {
    markStageComplete as initMarkStageComplete,
    uploadSingleDocument as initUploadSingleDocument,
} from '@/actions/App/Http/Controllers/Procurement/ProcurementInitiationController';
import {
    markStageComplete,
    updateDeliveryDetails,
    uploadSingleDocument,
} from '@/actions/App/Http/Controllers/Procurement/ProcurementStageController';
import { resolveStageActionPhase } from '@/lib/stage-action-map';
import { useMemo } from 'react';

type StagePhase = 'pre_procurement' | 'procurement' | 'post_procurement' | string;

export function resolveStageActions(stageValue?: string, phase?: StagePhase) {
    const completeUploadAndStage = {
        pre_procurement: {
            upload: uploadSingleDocument['/bac-secretariat/pre-procurement/{pr_number}/{stage}/upload-document'],
            complete: markStageComplete['/bac-secretariat/pre-procurement/{pr_number}/{stage}/complete'],
        },
        procurement: {
            upload: uploadSingleDocument['/bac-secretariat/procurement/{pr_number}/{stage}/upload-document'],
            complete: markStageComplete['/bac-secretariat/procurement/{pr_number}/{stage}/complete'],
        },
        post_procurement: {
            upload: uploadSingleDocument['/bac-secretariat/post-procurement/{pr_number}/{stage}/upload-document'],
            complete: markStageComplete['/bac-secretariat/post-procurement/{pr_number}/{stage}/complete'],
        },
    } as const;

    const resolvedPhase = resolveStageActionPhase(stageValue, phase);

    if (resolvedPhase === 'procurement_initiation') {
        return {
            upload: initUploadSingleDocument,
            complete: initMarkStageComplete,
            deliveryDetails: updateDeliveryDetails,
        };
    }

    return {
        ...(completeUploadAndStage[resolvedPhase] ?? completeUploadAndStage.procurement),
        deliveryDetails: updateDeliveryDetails,
    };
}

export function useStageActions(stageValue?: string, phase?: StagePhase) {
    return useMemo(() => resolveStageActions(stageValue, phase), [phase, stageValue]);
}
