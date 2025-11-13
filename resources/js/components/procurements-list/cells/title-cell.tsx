import { Link, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { SharedData, ProcurementListItem } from '@/types';
import { show as bacSecretariatShow } from '@/routes/bac-secretariat/procurements';
import { show as bacChairmanShow } from '@/routes/bac-chairman/procurements';
import { show as hopeShow } from '@/routes/hope/procurements';
import { show as adminShow } from '@/routes/admin/procurements';
import { useIsTruncated } from '@/hooks/use-is-truncated';

interface TitleCellProps {
    procurement: ProcurementListItem;
}

const getProcurementShowUrl = (role: string, id: string): string => {
    switch (role) {
        case 'bac_secretariat':
            return bacSecretariatShow.url(id);
        case 'bac_chairman':
            return bacChairmanShow.url(id);
        case 'hope':
            return hopeShow.url(id);
        case 'admin':
            return adminShow.url(id);
        default:
            return `/procurements-list/${id}`;
    }
};

export const TitleCell = ({ procurement }: TitleCellProps) => {
    const textRef = useRef<HTMLDivElement>(null);
    const isTruncated = useIsTruncated(textRef, procurement.title);
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || 'guest';
    const procurementUrl = getProcurementShowUrl(userRole, procurement.id);
    
    const titleContent = (
        <div ref={textRef} className="max-w-[280px] truncate font-medium" title={procurement.title}>
            <Link
                href={procurementUrl}
                className="text-gray-900 transition-colors duration-150 hover:text-blue-600 hover:underline dark:text-gray-100"
                prefetch="hover"
                cacheFor="5m"
                aria-label={`View procurement: ${procurement.title}`}
            >
                {procurement.title}
            </Link>
        </div>
    );
    
    return isTruncated ? (
        <Tooltip>
            <TooltipTrigger asChild>{titleContent}</TooltipTrigger>
            <TooltipContent className="font-medium">{procurement.title}</TooltipContent>
        </Tooltip>
    ) : (
        titleContent
    );
};
