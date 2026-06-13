import { AlertTriangle, Archive, BookOpenText, FileText, GitBranch, Pencil, ScrollText } from 'lucide-react';

export const STREAM_CONFIG: Record<
    string,
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: React.ComponentType<{ className?: string }> }
> = {
    'procurement.metadata': {
        label: 'Created / Updated',
        variant: 'secondary',
        icon: BookOpenText,
    },
    'procurement.status': {
        label: 'Status Change',
        variant: 'default',
        icon: GitBranch,
    },
    'procurement.documents': { label: 'Document', variant: 'outline', icon: FileText },
    'procurement.corrections': {
        label: 'Document Correction',
        variant: 'destructive',
        icon: AlertTriangle,
    },
    'procurement.metadata.corrections': {
        label: 'Metadata Correction',
        variant: 'secondary',
        icon: Pencil,
    },
    'procurement.archive': { label: 'Archive', variant: 'outline', icon: Archive },
    'procurement.events': { label: 'Event', variant: 'secondary', icon: ScrollText },
    'file.data': { label: 'File Data', variant: 'outline', icon: FileText },
    'file.metadata': { label: 'File Meta', variant: 'outline', icon: FileText },
    'file.chunks': { label: 'File Chunk', variant: 'outline', icon: FileText },
};

export const getStreamConfig = (stream: string) => STREAM_CONFIG[stream] ?? { label: stream, variant: 'outline' as const, icon: ScrollText };
