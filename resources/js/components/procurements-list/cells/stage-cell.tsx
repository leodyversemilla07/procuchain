import { getStageBadgeStyle } from '@/constants/procurement-badges';
import { Stage } from '@/types';
import { BadgeCell } from './badge-cell';

interface StageCellProps {
    stage: Stage;
}

export const StageCell = ({ stage }: StageCellProps) => <BadgeCell<Stage> value={stage} getStyle={getStageBadgeStyle} />;
