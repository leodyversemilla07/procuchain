import { Status } from '@/types';
import { getStatusBadgeStyle } from '@/constants/procurement-badges';
import { BadgeCell } from './badge-cell';

interface StatusCellProps {
    status: Status;
}

export const StatusCell = ({ status }: StatusCellProps) => (
    <BadgeCell<Status> value={status} getStyle={getStatusBadgeStyle} />
);
