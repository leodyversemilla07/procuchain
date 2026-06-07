import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { preview as workflowPreview } from '@/routes/admin/workflow-config';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, ClipboardList, Eye, FileCheck, FileText, GitBranch, Layers } from 'lucide-react';

interface Document {
    value: string;
    display_name: string;
    description: string;
}

interface Stage {
    stage: string;
    display_name: string;
    description: string;
    phase: string;
    phase_display_name: string;
    is_optional: boolean;
    required_documents: Document[];
    optional_documents: Document[];
    document_counts: {
        required_count: number;
        optional_count: number;
        total_count: number;
    };
}

interface Phase {
    name: string;
    stages: Stage[];
}

interface Mode {
    value: string;
    display_name: string;
    description: string;
    irr_section: string;
    is_alternative_mode: boolean;
}

interface PageProps {
    mode: Mode;
    phases: Record<string, Phase>;
    summary: {
        total_stages: number;
        optional_stages: number;
        required_stages: number;
        total_required_documents: number;
        total_optional_documents: number;
    };
    allModes: Mode[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Workflow Configuration', href: '/admin/workflow-config' },
    { title: 'Preview', href: '#' },
];

export default function WorkflowPreview({ mode, phases, summary, allModes }: PageProps) {
    const handleModeChange = (newMode: string) => {
        router.visit(workflowPreview(newMode).url);
    };

    const PhaseIcon = ({ phase }: { phase: string }) => {
        switch (phase) {
            case 'pre_procurement':
                return <ClipboardList />;
            case 'procurement':
                return <FileText />;
            case 'post_procurement':
                return <FileCheck />;
            default:
                return <GitBranch />;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Preview: ${mode.display_name}`} />

            <div className="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                {/* Header */}
                <HeroCard
                    icon={Eye}
                    title="Workflow Preview"
                    description="Preview what users will see for this procurement mode - including all stages and document requirements"
                    actions={
                        <Select value={mode.value} onValueChange={(value) => value && handleModeChange(value)}>
                            <SelectTrigger className="w-full sm:w-[280px]">
                                <SelectValue placeholder="Select procurement mode">{() => mode.display_name}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {allModes.map((m) => (
                                        <SelectItem key={m.value} value={m.value}>
                                            <div className="flex items-center gap-2">
                                                <span>{m.display_name}</span>
                                                {m.is_alternative_mode && (
                                                    <Badge variant="outline" className="text-xs">
                                                        Alt
                                                    </Badge>
                                                )}
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    }
                />

                {/* Mode Info Card */}
                <Card className="border-sidebar-border/70 dark:border-sidebar-border">
                    <CardHeader className="p-4 pb-3 sm:p-6 sm:pb-3">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-3">
                                <GitBranch />
                                <div className="min-w-0">
                                    <CardTitle className="text-base sm:text-lg">{mode.display_name}</CardTitle>
                                    <CardDescription className="line-clamp-2 text-xs sm:text-sm">{mode.description}</CardDescription>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant={mode.is_alternative_mode ? 'secondary' : 'default'} className="text-xs">
                                    {mode.is_alternative_mode ? 'Alternative' : 'Competitive'}
                                </Badge>
                                <Badge variant="outline" className="text-xs">
                                    {mode.irr_section}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                        <div className="grid grid-cols-2 gap-2 sm:gap-4 md:grid-cols-3 lg:grid-cols-5">
                            <div className="bg-primary/10 rounded-lg p-2 text-center sm:p-3">
                                <p className="text-primary text-xl font-bold sm:text-2xl">{summary.total_stages}</p>
                                <p className="text-muted-foreground text-[10px] sm:text-xs">Total Stages</p>
                            </div>
                            <div className="bg-primary/10 rounded-lg p-2 text-center sm:p-3">
                                <p className="text-primary text-xl font-bold sm:text-2xl">{summary.required_stages}</p>
                                <p className="text-muted-foreground text-[10px] sm:text-xs">Required</p>
                            </div>
                            <div className="bg-muted rounded-lg p-2 text-center sm:p-3">
                                <p className="text-muted-foreground text-xl font-bold sm:text-2xl">{summary.optional_stages}</p>
                                <p className="text-muted-foreground text-[10px] sm:text-xs">Optional</p>
                            </div>
                            <div className="bg-primary/10 rounded-lg p-2 text-center sm:p-3">
                                <p className="text-primary text-xl font-bold sm:text-2xl">{summary.total_required_documents}</p>
                                <p className="text-muted-foreground text-[10px] sm:text-xs">Req. Docs</p>
                            </div>
                            <div className="bg-muted col-span-2 rounded-lg p-2 text-center sm:p-3 md:col-span-1">
                                <p className="text-muted-foreground text-xl font-bold sm:text-2xl">{summary.total_optional_documents}</p>
                                <p className="text-muted-foreground text-[10px] sm:text-xs">Opt. Docs</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Phases */}
                <div className="flex flex-col gap-4 sm:gap-6">
                    {Object.entries(phases).map(([phaseKey, phase]) => {
                        if (phase.stages.length === 0) return null;

                        return (
                            <Card key={phaseKey} className="border-sidebar-border/70 dark:border-sidebar-border">
                                <CardHeader className="p-4 pb-3 sm:p-6 sm:pb-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <PhaseIcon phase={phaseKey} />
                                        <CardTitle className="text-base sm:text-lg">{phase.name}</CardTitle>
                                        <Badge variant="outline" className="text-xs">
                                            {phase.stages.length} stage{phase.stages.length !== 1 ? 's' : ''}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3 p-4 pt-0 sm:gap-4 sm:p-6 sm:pt-0">
                                    {phase.stages.map((stage, index) => (
                                        <Card key={stage.stage} className="border-sidebar-border/50 bg-muted/30">
                                            <CardHeader className="p-3 pb-2 sm:p-4 sm:pb-2">
                                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                    <div className="flex items-start gap-2 sm:items-center sm:gap-3">
                                                        <div className="bg-primary/10 text-primary flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold sm:h-7 sm:w-7 sm:text-sm">
                                                            {index + 1}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <CardTitle className="text-sm sm:text-base">{stage.display_name}</CardTitle>
                                                            <CardDescription className="line-clamp-2 text-[10px] sm:text-xs">
                                                                {stage.description}
                                                            </CardDescription>
                                                        </div>
                                                    </div>
                                                    <div className="ml-8 flex flex-wrap items-center gap-1 sm:ml-0 sm:gap-2">
                                                        {stage.is_optional && (
                                                            <Badge variant="outline" className="text-muted-foreground text-[10px] sm:text-xs">
                                                                Optional
                                                            </Badge>
                                                        )}
                                                        <Badge variant="secondary" className="text-[10px] sm:text-xs">
                                                            <FileText data-icon="inline-start" />
                                                            {stage.document_counts.total_count} docs
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="p-3 pt-0 sm:p-4 sm:pt-0">
                                                <div className="grid gap-3 sm:gap-4 md:grid-cols-2">
                                                    {/* Required Documents */}
                                                    {stage.required_documents.length > 0 && (
                                                        <div className="flex flex-col gap-2">
                                                            <div className="flex flex-wrap items-center gap-1 sm:gap-2">
                                                                <Layers className="text-primary" />
                                                                <span className="text-xs font-medium sm:text-sm">Required Documents</span>
                                                                <Badge variant="default" className="text-[10px] sm:text-xs">
                                                                    {stage.document_counts.required_count}
                                                                </Badge>
                                                            </div>
                                                            <div className="flex flex-col gap-1 rounded-md border p-2 sm:gap-2">
                                                                {stage.required_documents.map((doc) => (
                                                                    <div key={doc.value} className="bg-primary/5 rounded-md p-1.5 sm:p-2">
                                                                        <p className="text-[10px] font-medium sm:text-xs">{doc.display_name}</p>
                                                                        {doc.description && (
                                                                            <p className="text-muted-foreground hidden text-[10px] sm:block">
                                                                                {doc.description}
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}

                                                    {/* Optional Documents */}
                                                    {stage.optional_documents.length > 0 && (
                                                        <div className="flex flex-col gap-2">
                                                            <div className="flex flex-wrap items-center gap-1 sm:gap-2">
                                                                <CheckCircle2 className="text-muted-foreground" />
                                                                <span className="text-xs font-medium sm:text-sm">Optional Documents</span>
                                                                <Badge variant="outline" className="text-[10px] sm:text-xs">
                                                                    {stage.document_counts.optional_count}
                                                                </Badge>
                                                            </div>
                                                            <div className="flex flex-col gap-1 rounded-md border p-2 sm:gap-2">
                                                                {stage.optional_documents.map((doc) => (
                                                                    <div key={doc.value} className="bg-muted/50 rounded-md p-1.5 sm:p-2">
                                                                        <p className="text-[10px] font-medium sm:text-xs">{doc.display_name}</p>
                                                                        {doc.description && (
                                                                            <p className="text-muted-foreground hidden text-[10px] sm:block">
                                                                                {doc.description}
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}

                                                    {/* No documents */}
                                                    {stage.required_documents.length === 0 && stage.optional_documents.length === 0 && (
                                                        <div className="bg-muted/50 col-span-2 rounded-md p-4 text-center">
                                                            <p className="text-muted-foreground text-sm">No documents configured for this stage</p>
                                                        </div>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
