import { BreadcrumbItem } from '@/types';
import { Stage, Status } from '@/types/blockchain';

const STATUS_BADGE_STYLES: Record<Status, string> = {
    [Status.PROCUREMENT_SUBMITTED]: 'bg-[#014D4E] text-white border border-[#014D4E] hover:bg-[#235E6F]',
    [Status.PRE_PROCUREMENT_CONFERENCE_HELD]: 'bg-[#005F5F] text-white border border-[#005F5F] hover:bg-[#007C91]',
    [Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED]: 'bg-[#4C9085] text-white border border-[#4C9085] hover:bg-[#3C9D9B]',
    [Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED]: 'bg-[#008B84] text-white border border-[#008B84] hover:bg-[#2F8F89]',
    [Status.BIDDING_DOCUMENTS_PUBLISHED]: 'bg-[#015D5E] text-white border border-[#015D5E] hover:bg-[#93CCC6]',
    [Status.SUPPLEMENTAL_BID_BULLETINS_ONGOING]: 'bg-[#4F7CAC] text-white border border-[#4F7CAC] hover:bg-[#4B9EA1]',
    [Status.SUPPLEMENTAL_BID_BULLETINS_COMPLETED]: 'bg-[#016D6E] text-white border border-[#016D6E] hover:bg-[#B2DFDB]',
    [Status.PRE_BID_CONFERENCE_HELD]: 'bg-[#3C8B7D] text-white border border-[#3C8B7D] hover:bg-[#45B8AC]',
    [Status.PRE_BID_CONFERENCE_SKIPPED]: 'bg-[#3C7A6B] text-white border border-[#3C7A6B] hover:bg-[#266362]',
    [Status.PRE_BID_CONFERENCE_COMPLETED]: 'bg-[#1A4F4F] text-white border border-[#1A4F4F] hover:bg-[#235E6F]',
    [Status.BIDS_OPENED]: 'bg-[#017E7F] text-white border border-[#017E7F] hover:bg-[#5AC6B7]',
    [Status.BIDS_EVALUATED]: 'bg-[#4F6965] text-white border border-[#4F6965] hover:bg-[#468089]',
    [Status.POST_QUALIFICATION_VERIFIED]: 'bg-[#018F90] text-white border border-[#018F90] hover:bg-[#59A5A0]',
    [Status.POST_QUALIFICATION_FAILED]: 'bg-[#016B6C] text-white border border-[#016B6C] hover:bg-[#82CBB2]',
    [Status.RESOLUTION_RECORDED]: 'bg-[#357C78] text-white border border-[#357C78] hover:bg-[#266362]',
    [Status.AWARDED]: 'bg-[#2F8F89] text-white border border-[#2F8F89] hover:bg-[#225E63]',
    [Status.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED]: 'bg-[#015C5D] text-white border border-[#015C5D] hover:bg-[#B2DFDB]',
    [Status.NTP_RECORDED]: 'bg-[#014D4E] text-white border border-[#014D4E] hover:bg-[#C6F1E7]',
    [Status.MONITORING_COMPLETED]: 'bg-[#016D6E] text-white border border-[#016D6E] hover:bg-[#93CCC6]',
    [Status.COMPLETION_DOCUMENTS_UPLOADED]: 'bg-[#729B90] text-white border border-[#729B90] hover:bg-[#6D8C84]',
    [Status.COMPLETED]: 'bg-[#3AA9A3] text-white border border-[#3AA9A3] hover:bg-[#357C78]'
};

export const getStatusBadgeStyle = (state: Status): string => {
    return STATUS_BADGE_STYLES[state] ?? 'bg-[#CEDDDD] text-[#014D4E] border border-[#CEDDDD] hover:bg-[#C2F4EE]';
};

const STAGE_BADGE_STYLES: Record<Stage, string> = {
    [Stage.PROCUREMENT_INITIATION]: 'bg-[#015D5E] text-white border border-[#015D5E] hover:bg-[#3AA9A3]',
    [Stage.PRE_PROCUREMENT_CONFERENCE]: 'bg-[#016D6E] text-white border border-[#016D6E] hover:bg-[#468089]',
    [Stage.BIDDING_DOCUMENTS]: 'bg-[#017E7F] text-white border border-[#017E7F] hover:bg-[#59A5A0]',
    [Stage.PRE_BID_CONFERENCE]: 'bg-[#018F90] text-white border border-[#018F90] hover:bg-[#6EAF9C]',
    [Stage.SUPPLEMENTAL_BID_BULLETIN]: 'bg-[#357C78] text-white border border-[#357C78] hover:bg-[#8CC9BA]',
    [Stage.BID_OPENING]: 'bg-[#2F8F89] text-white border border-[#2F8F89] hover:bg-[#9FE2BF]',
    [Stage.BID_EVALUATION]: 'bg-[#3C7A6B] text-white border border-[#3C7A6B] hover:bg-[#5CD3B4]',
    [Stage.POST_QUALIFICATION]: 'bg-[#365C5C] text-white border border-[#365C5C] hover:bg-[#357C78]',
    [Stage.BAC_RESOLUTION]: 'bg-[#014D4E] text-white border border-[#014D4E] hover:bg-[#6D8C84]',
    [Stage.NOTICE_OF_AWARD]: 'bg-[#015D5E] text-white border border-[#015D5E] hover:bg-[#93CCC6]',
    [Stage.PERFORMANCE_BOND_CONTRACT_AND_PO]: 'bg-[#016D6E] text-white border border-[#016D6E] hover:bg-[#82CBB2]',
    [Stage.NOTICE_TO_PROCEED]: 'bg-[#266362] text-white border border-[#266362] hover:bg-[#225E63]',
    [Stage.MONITORING]: 'bg-[#095256] text-white border border-[#095256] hover:bg-[#014D4E]',
    [Stage.COMPLETION]: 'bg-[#43B3AE] text-white border border-[#43B3AE] hover:bg-[#3AA9A3]',
    [Stage.COMPLETED]: 'bg-[#357C78] text-white border border-[#357C78] hover:bg-[#A7DAD8]'
};

export const getStageBadgeStyle = (phase: Stage): string => {
    return STAGE_BADGE_STYLES[phase] ?? 'bg-[#CEDDDD] text-[#014D4E] border border-[#CEDDDD] hover:bg-[#C2F4EE]';
};

export const getBreadcrumbs = (role?: string): BreadcrumbItem[] => {
    switch (role) {
        case 'bac_secretariat':
            return [
                { title: 'BAC Secretariat Dashboard', href: '/bac-secretariat/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'bac_chairman':
            return [
                { title: 'BAC Chairman Dashboard', href: '/bac-chairman/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'hope':
            return [
                { title: 'HOPE Dashboard', href: '/hope/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        case 'admin':
            return [
                { title: 'Admin Dashboard', href: '/admin/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
        default:
            return [
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Procurement List', href: '#' },
            ];
    }
};
