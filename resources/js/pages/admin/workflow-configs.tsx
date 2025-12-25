import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Edit, Eye, GitBranch, RotateCcw, Settings } from 'lucide-react';
import { toast } from 'sonner';

// Wayfinder imports
import { edit as workflowConfigEdit, preview as workflowConfigPreview } from '@/routes/admin/workflow-config';
import { resetToDefaults } from '@/actions/App/Http/Controllers/Admin/ProcurementWorkflowConfigController';

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
            }
        );
    };

    const ModeCard = ({ config }: { config: WorkflowConfig }) => (
        <Card className="group relative flex flex-col overflow-hidden transition-all hover:shadow-md">
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <CardTitle className="text-lg">{config.display_name}</CardTitle>
                        <CardDescription className="text-sm">{config.irr_section}</CardDescription>
                    </div>
                    <div className="flex items-center gap-2">
                        {config.is_customized && (
                            <Badge variant="secondary" className="text-xs">
                                Customized
                            </Badge>
                        )}
                        <Badge variant={config.is_alternative_mode ? 'outline' : 'default'} className="text-xs">
                            {config.is_alternative_mode ? 'Alternative' : 'Competitive'}
                        </Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="flex-1 space-y-3">
                <p className="text-muted-foreground line-clamp-2 text-sm">{config.description}</p>

                <div className="flex items-center gap-4 text-sm">
                    <div className="flex items-center gap-1.5">
                        <GitBranch className="text-muted-foreground h-4 w-4" />
                        <span className="font-medium">{config.stage_count}</span>
                        <span className="text-muted-foreground">stages</span>
                    </div>
                    {config.optional_stage_count > 0 && (
                        <div className="text-muted-foreground">
                            ({config.optional_stage_count} optional)
                        </div>
                    )}
                </div>

                {config.updated_at && (
                    <p className="text-muted-foreground text-xs">
                        Last updated: {formatDate(config.updated_at)}
                        {config.updated_by && ` by ${config.updated_by}`}
                    </p>
                )}
            </CardContent>
            <CardFooter className="flex items-center gap-2 border-t pt-4">
                <Button asChild variant="outline" size="sm">
                    <Link href={workflowConfigPreview(config.mode).url}>
                        <Eye className="mr-2 h-4 w-4" />
                        Preview
                    </Link>
                </Button>
                <Button asChild variant="default" size="sm" className="flex-1">
                    <Link href={workflowConfigEdit(config.mode).url}>
                        <Edit className="mr-2 h-4 w-4" />
                        Configure
                    </Link>
                </Button>
                {config.is_customized && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleResetConfig(config.mode)}
                        title="Reset to defaults"
                    >
                        <RotateCcw className="h-4 w-4" />
                    </Button>
                )}
            </CardFooter>
        </Card>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workflow Configuration" />

            <div className="space-y-8 p-6">
                {/* Header */}
                <HeroCard
                    icon={Settings}
                    title="Workflow Configuration"
                    description="Configure which stages appear in each procurement mode's workflow."
                />

                {/* Competitive Modes Section */}
                <div className="space-y-4">
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
                <div className="space-y-4">
                    <div className="flex items-center gap-2">
                        <h2 className="text-xl font-semibold">Alternative Modes</h2>
                        <Badge variant="secondary">{alternativeModes.length}</Badge>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Simplified procedures. May be delegated to End-User or Procurement Unit per NGPA IRR Section 26.4.
                    </p>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {alternativeModes.map((config) => (
                            <ModeCard key={config.mode} config={config} />
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
