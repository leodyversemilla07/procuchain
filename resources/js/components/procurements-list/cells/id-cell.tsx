import { Link, usePage } from '@inertiajs/react';
import { SharedData } from '@/types';
import { show as bacSecretariatShow } from '@/routes/bac-secretariat/procurements';
import { show as bacChairmanShow } from '@/routes/bac-chairman/procurements';
import { show as hopeShow } from '@/routes/hope/procurements';
import { show as adminShow } from '@/routes/admin/procurements';

interface IdCellProps {
    id: string;
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

export const IdCell = ({ id }: IdCellProps) => {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.roles?.[0] || auth?.user?.role || 'guest';
    const procurementUrl = getProcurementShowUrl(userRole, id);
    
    return (
        <div className="font-medium text-blue-600 dark:text-blue-400">
            <Link 
                href={procurementUrl} 
                className="flex items-center transition-all duration-150 hover:underline" 
                prefetch="hover" 
                cacheFor="5m"
                aria-label={`View procurement ${id}`}
            >
                <span className="rounded border border-blue-100 bg-blue-50 px-1.5 py-0.5 font-mono text-xs dark:border-blue-800/60 dark:bg-blue-900/30">
                    {id}
                </span>
            </Link>
        </div>
    );
};
