import { BreadcrumbItem } from '@/types';
import { Stage, Status } from '@/types/blockchain';

const STATUS_BADGE_STYLES: Record<Status, string> = {
    'Procurement Submitted': 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
    'Pre-Procurement Conference Held': 'bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-900',
    'Pre-Procurement Conference Skipped': 'bg-yellow-50 text-yellow-700 border border-yellow-200 hover:bg-yellow-100 dark:bg-yellow-950/40 dark:text-yellow-300 dark:border-yellow-900',
    'Pre-Procurement Conference Completed': 'bg-lime-50 text-lime-700 border border-lime-200 hover:bg-lime-100 dark:bg-lime-950/40 dark:text-lime-300 dark:border-lime-900',
    'Bidding Documents Published': 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
    'Supplemental Bulletins Ongoing': 'bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900',
    'Supplemental Bulletins Completed': 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-900',
    'Pre-Bid Conference Held': 'bg-violet-50 text-violet-700 border border-violet-200 hover:bg-violet-100 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-900',
    'Pre-Bid Conference Skipped': 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900',
    'Bids Opened': 'bg-cyan-50 text-cyan-700 border border-cyan-200 hover:bg-cyan-100 dark:bg-cyan-950/40 dark:text-cyan-300 dark:border-cyan-900',
    'Bids Evaluated': 'bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200 hover:bg-fuchsia-100 dark:bg-fuchsia-950/40 dark:text-fuchsia-300 dark:border-fuchsia-900',
    'Post-Qualification Verified': 'bg-teal-50 text-teal-700 border border-teal-200 hover:bg-teal-100 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-900',
    'Post-Qualification Failed': 'bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900',
    'Resolution Recorded': 'bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900',
    'Awarded': 'bg-pink-50 text-pink-700 border border-pink-200 hover:bg-pink-100 dark:bg-pink-950/40 dark:text-pink-300 dark:border-pink-900',
    'Performance Bond, Contract and PO Recorded': 'bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-900',
    'NTP Recorded': 'bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100 dark:bg-slate-950/40 dark:text-slate-300 dark:border-slate-900',
    'Monitoring': 'bg-zinc-50 text-zinc-700 border border-zinc-200 hover:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700',
    'Completion Documents Uploaded': 'bg-stone-50 text-stone-700 border border-stone-200 hover:bg-stone-100 dark:bg-stone-950/40 dark:text-stone-300 dark:border-stone-900',
    'Completed': 'bg-neutral-50 text-neutral-700 border border-neutral-200 hover:bg-neutral-100 dark:bg-neutral-950/40 dark:text-neutral-300 dark:border-neutral-900'
};

export const getStatusBadgeStyle = (state: Status): string => {
    return STATUS_BADGE_STYLES[state] ?? 'bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-gray-950 dark:text-gray-300 dark:border-gray-800';
};

const STAGE_BADGE_STYLES: Record<Stage, string> = {
    'Procurement Initiation': 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
    'Pre-Procurement Conference': 'bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-900',
    'Bidding Documents': 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
    'Pre-Bid Conference': 'bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900',
    'Supplemental Bid Bulletin': 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-900',
    'Bid Opening': 'bg-cyan-50 text-cyan-700 border border-cyan-200 hover:bg-cyan-100 dark:bg-cyan-950/40 dark:text-cyan-300 dark:border-cyan-900',
    'Bid Evaluation': 'bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200 hover:bg-fuchsia-100 dark:bg-fuchsia-950/40 dark:text-fuchsia-300 dark:border-fuchsia-900',
    'Post-Qualification': 'bg-teal-50 text-teal-700 border border-teal-200 hover:bg-teal-100 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-900',
    'BAC Resolution': 'bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900',
    'Notice of Award': 'bg-pink-50 text-pink-700 border border-pink-200 hover:bg-pink-100 dark:bg-pink-950/40 dark:text-pink-300 dark:border-pink-900',
    'Performance Bond, Contract and PO': 'bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-900',
    'Notice to Proceed': 'bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100 dark:bg-slate-950/40 dark:text-slate-300 dark:border-slate-900',
    'Monitoring': 'bg-violet-50 text-violet-700 border border-violet-200 hover:bg-violet-100 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-900',
    'Completion': 'bg-stone-50 text-stone-700 border border-stone-200 hover:bg-stone-100 dark:bg-stone-950/40 dark:text-stone-300 dark:border-stone-900',
    'Completed': 'bg-lime-50 text-lime-700 border border-lime-200 hover:bg-lime-100 dark:bg-lime-950/40 dark:text-lime-300 dark:border-lime-900'
};

export const getStageBadgeStyle = (phase: Stage): string => {
    return STAGE_BADGE_STYLES[phase] ?? 'bg-gray-50 text-gray-700 border border-gray-200 hover:bg-gray-100 dark:bg-gray-950 dark:text-gray-300 dark:border-gray-800';
};

export const getBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    switch (role) {
        case 'bac_secretariat':
            return [
                { title: 'Dashboard', href: '/bac-secretariat/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'bac_chairman':
            return [
                { title: 'Dashboard', href: '/bac-chairman/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'hope':
            return [
                { title: 'Dashboard', href: '/hope/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        default:
            return [
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
    }
};