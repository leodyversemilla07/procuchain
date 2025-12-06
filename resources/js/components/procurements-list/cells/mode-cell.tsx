import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface ModeCellProps {
    mode: string | null | undefined;
    modeLabel: string | null | undefined;
}

/**
 * Style mappings for procurement modes
 * Colors match the NGPA IRR categorization
 * Note: Shopping was removed in NGPA (RA 12009) and replaced with SVP
 */
const MODE_BADGE_STYLES: Record<string, string> = {
    competitive_bidding: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-800',
    limited_source_bidding: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/30 dark:text-indigo-400 dark:border-indigo-800',
    direct_contracting: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-800',
    repeat_order: 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-950/30 dark:text-cyan-400 dark:border-cyan-800',
    negotiated_procurement: 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/30 dark:text-orange-400 dark:border-orange-800',
    small_value_procurement: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-950/30 dark:text-green-400 dark:border-green-800',
    competitive_dialogue: 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/30 dark:text-purple-400 dark:border-purple-800',
    unsolicited_offer_with_bid_matching: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-800',
    lease_of_real_property_and_venue: 'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/30 dark:text-teal-400 dark:border-teal-800',
    agency_to_agency: 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-950/30 dark:text-slate-400 dark:border-slate-800',
};

const DEFAULT_MODE_BADGE_STYLE = 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-950/30 dark:text-gray-400 dark:border-gray-800';

/**
 * Short display labels for procurement modes in compact table view
 */
const MODE_SHORT_LABELS: Record<string, string> = {
    competitive_bidding: 'CB',
    limited_source_bidding: 'LSB',
    direct_contracting: 'DC',
    repeat_order: 'RO',
    negotiated_procurement: 'NP',
    small_value_procurement: 'SVP',
    competitive_dialogue: 'CD',
    unsolicited_offer_with_bid_matching: 'UO',
    lease_of_real_property_and_venue: 'Lease',
    agency_to_agency: 'A2A',
};

export const ModeCell = ({ mode, modeLabel }: ModeCellProps) => {
    if (!mode) {
        return (
            <Badge
                variant="outline"
                className={cn(
                    'bg-gray-50 text-gray-500 border-gray-200 dark:bg-gray-950/30 dark:text-gray-500 dark:border-gray-800',
                    'inline-flex items-center px-2 py-0.5 text-xs font-medium',
                )}
            >
                —
            </Badge>
        );
    }

    const style = MODE_BADGE_STYLES[mode] ?? DEFAULT_MODE_BADGE_STYLE;
    const shortLabel = MODE_SHORT_LABELS[mode] ?? mode.substring(0, 3).toUpperCase();
    const fullLabel = modeLabel ?? mode.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge
                        variant="outline"
                        className={cn(
                            style,
                            'inline-flex items-center px-2 py-0.5 text-xs font-medium cursor-help',
                            'border shadow-sm transition-all duration-150',
                        )}
                    >
                        {shortLabel}
                    </Badge>
                </TooltipTrigger>
                <TooltipContent>
                    <p className="font-medium">{fullLabel}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
};
