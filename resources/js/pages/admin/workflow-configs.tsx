import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Edit, Eye, GitBranch, RotateCcw, Settings } from 'lucide-react';
import { toast } from 'sonner';

// Wayfinder imports
import { resetToDefaults } from '@/actions/App/Http/Controllers/Admin/ProcurementWorkflowConfigController';
import { edit as workflowConfigEdit, preview as workflowConfigPreview } from '@/routes/admin/workflow-config';

interface WorkflowConfig {
    mode: string;
    display_name: string;
    description: string;
    irr_section: string;
    is_alternative_mode: boolean;
    stage_count: number;
    optional_stage_count: number;
    required_stage_count: number;
    is_customized: boolean;
    updated_at: string | null;
    updated_by: string | null;
}

interface PageProps {
    competitiveModes: WorkflowConfig[];
    alternativeModes: WorkflowConfig[];
}

const breadcrumbs = [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Workflow Configuration', href: '/admin/workflow-config' },
];

export default function WorkflowConfigs({ competitiveModes, alternativeModes }: PageProps) {
    const formatDate = (dateString: string | null) => {
        if (!dateString) return null;
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const handleResetConfig = (mode: string) => {
        router.post(
            resetToDefaults(mode).url,
            {},
            {
                onSuccess: () => {
                    toast.success('Workflow configuration reset to defaults');
                },
                onError: () => {
                    toast.error('Failed to reset workflow configuration');
                },
            },
        );
    };

    const ModeCard = ({ config }: { config: WorkflowConfig }) => (
        <Card className="group relative flex flex-col overflow-hidden transition-all hover:shadow-md">
            <CardHeader>
                <CardTitle className="text-lg">{config.display_name}</CardTitle>
                <CardDescription>{config.irr_section}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4">
                <div className="flex flex-wrap gap-2">
                    {config.is_customized && (
                        <Badge variant="secondary" className="text-xs">
                            Customized
                        </Badge>
                    )}
                    <Badge variant={config.is_alternative_mode ? 'outline' : 'default'} className="text-xs">
                        {config.is_alternative_mode ? 'Alternative' : 'Competitive'}
                    </Badge>
                </div>

                <p className="text-muted-foreground line-clamp-3 text-sm">{config.description}</p>

                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <div className="flex items-center gap-1.5">
                        <GitBranch data-icon="inline-start" className="text-muted-foreground" />
                        <span className="font-medium">{config.stage_count}</span>
                        <span className="text-muted-foreground">stages</span>
                    </div>
                    {config.optional_stage_count > 0 && <div className="text-muted-foreground">({config.optional_stage_count} optional)</div>}
                </div>

                {config.updated_at && (
                    <p className="text-muted-foreground text-xs">
                        Last updated: {formatDate(config.updated_at)}
                        {config.updated_by && ` by ${config.updated_by}`}
                    </p>
                )}
            </CardContent>
            <CardFooter className="flex flex-wrap items-center gap-2 border-t">
                <Button variant="outline" size="sm" nativeButton={false} render={<Link href={workflowConfigPreview(config.mode).url} />}>
                    <Eye data-icon="inline-start" />
                    Preview
                </Button>
                <Button
                    variant="default"
                    size="sm"
                    className="min-w-[140px] flex-1"
                    nativeButton={false}
                    render={<Link href={workflowConfigEdit(config.mode).url} />}
                >
                    <Edit data-icon="inline-start" />
                    Configure
                </Button>
                {config.is_customized && (
                    <Button variant="ghost" size="sm" onClick={() => handleResetConfig(config.mode)} title="Reset to defaults">
                        <RotateCcw />
                    </Button>
                )}
            </CardFooter>
        </Card>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workflow Configuration" />

            <div className="flex flex-col gap-8 p-6">
                {/* Header */}
                <HeroCard
                    icon={Settings}
                    title="Workflow Configuration"
                    description="Configure which stages appear in each procurement mode's workflow."
                />

                {/* Competitive Modes Section */}
                <div className="flex flex-col gap-4">
                    <div className="flex items-center gap-2">
                        <h2 className="text-xl font-semibold">Competitive Modes</h2>
                        <Badge variant="secondary">{competitiveModes.length}</Badge>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Full bidding process required. Cannot be delegated to End-User per NGPA IRR Section 26.4.
                    </p>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {competitiveModes.map((config) => (
                            <ModeCard key={config.mode} config={config} />
                        ))}
                    </div>
                </div>

                {/* Alternative Modes Section */}
                <div className="flex flex-col gap-4">
                    <div className="flex items-center gap-2">
                        <h2 className="text-xl font-semibold">Alternative Modes</h2>
                        <Badge variant="secondary">{alternativeModes.length}</Badge>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Simplified procedures. May be delegated to End-User or Procurement Unit per NGPA IRR Section 26.4.
                    </p>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {alternativeModes.map((config) => (
                            <ModeCard key={config.mode} config={config} />
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
