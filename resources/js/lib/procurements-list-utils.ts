import { BreadcrumbItem } from '@/types';
import { Stage, Status } from '@/types/blockchain';

const STATUS_BADGE_STYLES: Record<Status, string> = {
    [Status.PROCUREMENT_SUBMITTED]: 'bg-[#014D4E] text-white border border-[#014D4E] hover:bg-[#235E6F]',
    [Status.PRE_PROCUREMENT_CONFERENCE_HELD]: 'bg-[#008080] text-white border border-[#008080] hover:bg-[#007C91]',
    [Status.PRE_PROCUREMENT_CONFERENCE_SKIPPED]: 'bg-[#4C9085] text-white border border-[#4C9085] hover:bg-[#3C9D9B]',
    [Status.PRE_PROCUREMENT_CONFERENCE_COMPLETED]: 'bg-[#00B2A9] text-white border border-[#00B2A9] hover:bg-[#2F8F89]',
    [Status.BIDDING_DOCUMENTS_PUBLISHED]: 'bg-[#A7DAD8] text-[#014D4E] border border-[#A7DAD8] hover:bg-[#93CCC6]',
    [Status.SUPPLEMENTAL_BID_BULLETINS_ONGOING]: 'bg-[#4F7CAC] text-white border border-[#4F7CAC] hover:bg-[#4B9EA1]',
    [Status.SUPPLEMENTAL_BID_BULLETINS_COMPLETED]: 'bg-[#C8E8DF] text-[#014D4E] border border-[#C8E8DF] hover:bg-[#B2DFDB]',
    [Status.PRE_BID_CONFERENCE_HELD]: 'bg-[#5CD3B4] text-white border border-[#5CD3B4] hover:bg-[#45B8AC]',
    [Status.PRE_BID_CONFERENCE_SKIPPED]: 'bg-[#3C7A6B] text-white border border-[#3C7A6B] hover:bg-[#266362]',
    [Status.PRE_BID_CONFERENCE_COMPLETED]: 'bg-[#1A4F4F] text-white border border-[#1A4F4F] hover:bg-[#235E6F]',
    [Status.BIDS_OPENED]: 'bg-[#6DD6C1] text-white border border-[#6DD6C1] hover:bg-[#5AC6B7]',
    [Status.BIDS_EVALUATED]: 'bg-[#4F6965] text-white border border-[#4F6965] hover:bg-[#468089]',
    [Status.POST_QUALIFICATION_VERIFIED]: 'bg-[#6EAF9C] text-white border border-[#6EAF9C] hover:bg-[#59A5A0]',
    [Status.POST_QUALIFICATION_FAILED]: 'bg-[#8CC9BA] text-[#014D4E] border border-[#8CC9BA] hover:bg-[#82CBB2]',
    [Status.RESOLUTION_RECORDED]: 'bg-[#357C78] text-white border border-[#357C78] hover:bg-[#266362]',
    [Status.AWARDED]: 'bg-[#2F8F89] text-white border border-[#2F8F89] hover:bg-[#225E63]',
    [Status.PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED]: 'bg-[#C2F4EE] text-[#014D4E] border border-[#C2F4EE] hover:bg-[#B2DFDB]',
    [Status.NTP_RECORDED]: 'bg-[#D3ECEA] text-[#014D4E] border border-[#D3ECEA] hover:bg-[#C6F1E7]',
    [Status.MONITORING]: 'bg-[#9FB9A2] text-[#014D4E] border border-[#9FB9A2] hover:bg-[#93CCC6]',
    [Status.COMPLETION_DOCUMENTS_UPLOADED]: 'bg-[#729B90] text-white border border-[#729B90] hover:bg-[#6D8C84]',
    [Status.COMPLETED]: 'bg-[#3AA9A3] text-white border border-[#3AA9A3] hover:bg-[#357C78]'
};

export const getStatusBadgeStyle = (state: Status): string => {
    return STATUS_BADGE_STYLES[state] ?? 'bg-[#CEDDDD] text-[#014D4E] border border-[#CEDDDD] hover:bg-[#C2F4EE]';
};

const STAGE_BADGE_STYLES: Record<Stage, string> = {
    [Stage.PROCUREMENT_INITIATION]: 'bg-[#45B8AC] text-white border border-[#45B8AC] hover:bg-[#3AA9A3]',
    [Stage.PRE_PROCUREMENT_CONFERENCE]: 'bg-[#4B9EA1] text-white border border-[#4B9EA1] hover:bg-[#468089]',
    [Stage.BIDDING_DOCUMENTS]: 'bg-[#5AC6B7] text-white border border-[#5AC6B7] hover:bg-[#59A5A0]',
    [Stage.PRE_BID_CONFERENCE]: 'bg-[#6D8C84] text-white border border-[#6D8C84] hover:bg-[#6EAF9C]',
    [Stage.SUPPLEMENTAL_BID_BULLETIN]: 'bg-[#82CBB2] text-[#014D4E] border border-[#82CBB2] hover:bg-[#8CC9BA]',
    [Stage.BID_OPENING]: 'bg-[#A1E3D8] text-[#014D4E] border border-[#A1E3D8] hover:bg-[#9FE2BF]',
    [Stage.BID_EVALUATION]: 'bg-[#55D6BE] text-white border border-[#55D6BE] hover:bg-[#5CD3B4]',
    [Stage.POST_QUALIFICATION]: 'bg-[#365C5C] text-white border border-[#365C5C] hover:bg-[#357C78]',
    [Stage.BAC_RESOLUTION]: 'bg-[#6B8C88] text-white border border-[#6B8C88] hover:bg-[#6D8C84]',
    [Stage.NOTICE_OF_AWARD]: 'bg-[#9AD9DB] text-[#014D4E] border border-[#9AD9DB] hover:bg-[#93CCC6]',
    [Stage.PERFORMANCE_BOND_CONTRACT_AND_PO]: 'bg-[#8AA39B] text-white border border-[#8AA39B] hover:bg-[#82CBB2]',
    [Stage.NOTICE_TO_PROCEED]: 'bg-[#266362] text-white border border-[#266362] hover:bg-[#225E63]',
    [Stage.MONITORING]: 'bg-[#095256] text-white border border-[#095256] hover:bg-[#014D4E]',
    [Stage.COMPLETION]: 'bg-[#43B3AE] text-white border border-[#43B3AE] hover:bg-[#3AA9A3]',
    [Stage.COMPLETED]: 'bg-[#B2DFDB] text-[#014D4E] border border-[#B2DFDB] hover:bg-[#A7DAD8]'
};

export const getStageBadgeStyle = (phase: Stage): string => {
    return STAGE_BADGE_STYLES[phase] ?? 'bg-[#CEDDDD] text-[#014D4E] border border-[#CEDDDD] hover:bg-[#C2F4EE]';
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