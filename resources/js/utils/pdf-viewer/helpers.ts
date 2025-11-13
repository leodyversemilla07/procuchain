import { UserRole } from '@/types';
import {
    Activity,
    Award,
    BarChart3,
    CheckCircle,
    Clock,
    FileCheck,
    FileText,
    Flag,
    Gavel,
    PlayCircle,
    Target,
    Users2,
} from 'lucide-react';
import React from 'react';

export const formatFileSize = (bytes?: number): string => {
    if (bytes === undefined || bytes === null || isNaN(bytes) || bytes < 0) return 'N/A';
    if (bytes === 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const size = parseFloat((bytes / Math.pow(1024, i)).toFixed(i > 1 ? 1 : 0));

    return `${size} ${units[i]}`;
};

export const formatTimestamp = (timestamp: string) => {
    try {
        const date = new Date(timestamp);
        return {
            date: date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            }),
            time: date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            }),
            relative: getRelativeTime(date),
        };
    } catch {
        return {
            date: 'Invalid Date',
            time: 'Invalid Time',
            relative: 'Unknown',
        };
    }
};

export const getRelativeTime = (date: Date) => {
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
    if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)} days ago`;
    if (diffInSeconds < 31536000) return `${Math.floor(diffInSeconds / 2592000)} months ago`;
    return `${Math.floor(diffInSeconds / 31536000)} years ago`;
};

export const formatUserAddress = (address: string) => {
    if (!address || address.length < 10) return address;
    return `${address.slice(0, 6)}...${address.slice(-4)}`;
};

export const formatStatus = (status: string) => {
    if (!status) return 'Unknown';
    return status.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

export const formatRole = (role: string) => {
    switch (role) {
        case UserRole.BAC_CHAIRMAN:
            return 'BAC Chairman';
        case UserRole.BAC_SECRETARIAT:
            return 'BAC Secretariat';
        case UserRole.HOPE:
            return 'Head of Office';
        case UserRole.ADMIN:
            return 'Administrator';
        default:
            return role.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
    }
};

export const getRoleBadgeColor = (role: string) => {
    switch (role) {
        case UserRole.BAC_CHAIRMAN:
            return 'bg-primary/10 text-primary';
        case UserRole.BAC_SECRETARIAT:
            return 'bg-info/10 text-info';
        case UserRole.HOPE:
            return 'bg-success/10 text-success';
        case UserRole.ADMIN:
            return 'bg-destructive/10 text-destructive';
        default:
            return 'bg-muted text-muted-foreground';
    }
};

export const getStatusBadgeColor = (status: string) => {
    if (!status) return 'bg-muted text-muted-foreground';

    const lowerStatus = status.toLowerCase();
    if (lowerStatus.includes('active') || lowerStatus.includes('in_progress') || lowerStatus.includes('ongoing')) {
        return 'bg-success/10 text-success';
    } else if (lowerStatus.includes('pending') || lowerStatus.includes('waiting')) {
        return 'bg-warning/10 text-warning';
    } else if (lowerStatus.includes('complete') || lowerStatus.includes('finished') || lowerStatus.includes('closed')) {
        return 'bg-primary/10 text-primary';
    } else if (lowerStatus.includes('cancelled') || lowerStatus.includes('rejected')) {
        return 'bg-destructive/10 text-destructive';
    }
    return 'bg-muted text-muted-foreground';
};

export const getStatusIcon = (status: string) => {
    if (!status) return Activity;

    const lowerStatus = status.toLowerCase();
    if (lowerStatus.includes('active') || lowerStatus.includes('in_progress') || lowerStatus.includes('ongoing')) {
        return PlayCircle;
    } else if (lowerStatus.includes('pending') || lowerStatus.includes('waiting')) {
        return Clock;
    } else if (lowerStatus.includes('complete') || lowerStatus.includes('finished') || lowerStatus.includes('closed')) {
        return CheckCircle;
    } else if (lowerStatus.includes('cancelled') || lowerStatus.includes('rejected')) {
        return Flag;
    }
    return Activity;
};

export const getStageIcon = (stage: string) => {
    const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
        ProcurementInitiation: PlayCircle,
        PreProcurementConference: Users2,
        BiddingDocuments: FileCheck,
        PreBidConference: Users2,
        BidOpening: FileText,
        BidEvaluation: BarChart3,
        PostQualification: CheckCircle,
        NoticeOfAward: Award,
        NoticeToProceed: Flag,
        PerformanceBondContractAndPo: FileCheck,
        Monitoring: Activity,
        Completion: Target,
        BacResolution: Gavel,
        SupplementalBidBulletin: FileText,
    };

    const IconComponent = iconMap[stage] || FileText;
    return IconComponent;
};
