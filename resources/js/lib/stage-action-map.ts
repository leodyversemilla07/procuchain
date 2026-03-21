export type StageActionPhase = 'pre_procurement' | 'procurement' | 'post_procurement';

export function resolveStageActionPhase(stageValue?: string, phase?: string): StageActionPhase | 'procurement_initiation' {
    if (stageValue === 'procurement_initiation') {
        return 'procurement_initiation';
    }

    if (phase === 'pre_procurement' || phase === 'post_procurement') {
        return phase;
    }

    return 'procurement';
}
