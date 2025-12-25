import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Edit, FileText, Settings } from 'lucide-react';

// Wayfinder imports
import { index as stageDocumentsIndex, edit as stageDocumentsEdit } from '@/routes/admin/stage-documents';

interface StageConfig {
    stage: string;
    display_name: string;
    description: string;
    phase: string;
    phase_display_name: string;
    required_count: number;
    optional_count: number;
    total_count: number;
    is_customized: boolean;
    updated_at: string | null;
    updated_by: string | null;
}

interface Mode {
    value: string;
    display_name: string;
    is_alternative: boolean;
}

interface PageProps {
    selectedMode: string;
    selectedModeDisplayName: string;
    modes: Mode[];
    preProcurement: StageConfig[];
    procurement: StageConfig[];
    postProcurement: StageConfig[];
}

const breadcrumbs = [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Stage Documents', href: '/admin/stage-documents' },
];

export default function StageDocumentConfigs({
    selectedMode,
    selectedModeDisplayName,
    modes,
    preProcurement,
    procurement,
    postProcurement,
}: PageProps) {
    const handleModeChange = (newMode: string) => {
        router.get(stageDocumentsIndex().url, { mode: newMode }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return null;
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const StageTable = ({ stages, title, description }: { stages: StageConfig[]; title: string; description: string }) => (
        <Card>
            <CardHeader>
                <CardTitle className="text-lg">{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                {stages.length === 0 ? (
                    <div className="text-muted-foreground py-8 text-center text-sm">
                        No stages in this phase for {selectedModeDisplayName}
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Stage</TableHead>
                                <TableHead className="text-center">Required</TableHead>
                                <TableHead className="text-center">Optional</TableHead>
                                <TableHead className="text-center">Total</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {stages.map((stage) => (
                                <TableRow key={stage.stage}>
                                    <TableCell>
                                        <div className="space-y-0.5">
                                            <div className="font-medium">{stage.display_name}</div>
                                            <div className="text-muted-foreground line-clamp-1 text-xs">
                                                {stage.description}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <Badge variant="default" className="min-w-[2rem]">
                                            {stage.required_count}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <Badge variant="secondary" className="min-w-[2rem]">
                                            {stage.optional_count}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <span className="text-muted-foreground text-sm">
                                            {stage.total_count}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {stage.is_customized ? (
                                            <div className="space-y-0.5">
                                                <Badge variant="outline" className="text-xs">
                                                    Customized
                                                </Badge>
                                                {stage.updated_at && (
                                                    <div className="text-muted-foreground text-xs">
                                                        {formatDate(stage.updated_at)}
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground text-xs">
                                                Default
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button asChild variant="ghost" size="sm">
                                            <Link href={stageDocumentsEdit({ mode: selectedMode, stage: stage.stage }).url}>
                                                <Edit className="mr-2 h-4 w-4" />
                                                Edit
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );

    // Group competitive and alternative modes
    const competitiveModes = modes.filter((m) => !m.is_alternative);
    const alternativeModes = modes.filter((m) => m.is_alternative);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stage Document Configuration" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <HeroCard
                    icon={FileText}
                    title="Stage Document Configuration"
                    description="Configure required and optional documents for each procurement stage."
                />

                {/* Mode Selector */}
                <Card>
                    <CardContent className="flex items-center gap-4 p-4">
                        <Settings className="text-muted-foreground h-5 w-5" />
                        <div className="flex-1">
                            <Label className="text-sm font-medium">Procurement Mode</Label>
                            <p className="text-muted-foreground text-xs">
                                Select a procurement mode to view and configure its stage documents
                            </p>
                        </div>
                        <Select value={selectedMode} onValueChange={handleModeChange}>
                            <SelectTrigger className="w-[300px]">
                                <SelectValue placeholder="Select mode" />
                            </SelectTrigger>
                            <SelectContent>
                                <div className="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                                    Competitive Modes
                                </div>
                                {competitiveModes.map((mode) => (
                                    <SelectItem key={mode.value} value={mode.value}>
                                        {mode.display_name}
                                    </SelectItem>
                                ))}
                                <div className="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                                    Alternative Modes
                                </div>
                                {alternativeModes.map((mode) => (
                                    <SelectItem key={mode.value} value={mode.value}>
                                        {mode.display_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>

                {/* Summary Stats */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardContent className="p-4">
                            <div className="text-2xl font-bold">
                                {preProcurement.length + procurement.length + postProcurement.length}
                            </div>
                            <p className="text-muted-foreground text-sm">Total Stages</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="text-2xl font-bold">
                                {[...preProcurement, ...procurement, ...postProcurement].reduce(
                                    (sum, s) => sum + s.required_count,
                                    0
                                )}
                            </div>
                            <p className="text-muted-foreground text-sm">Required Documents</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="text-2xl font-bold">
                                {[...preProcurement, ...procurement, ...postProcurement].filter(
                                    (s) => s.is_customized
                                ).length}
                            </div>
                            <p className="text-muted-foreground text-sm">Customized Stages</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Stage Tables by Phase */}
                <div className="space-y-6">
                    <StageTable
                        stages={preProcurement}
                        title="Pre-Procurement Phase"
                        description="Planning & Preparation stages"
                    />
                    <StageTable
                        stages={procurement}
                        title="Procurement Phase"
                        description="Bidding & Evaluation stages"
                    />
                    <StageTable
                        stages={postProcurement}
                        title="Post-Procurement Phase"
                        description="Award & Implementation stages"
                    />
                </div>
            </div>
        </AppLayout>
    );
}

function Label({ children, className }: { children: React.ReactNode; className?: string }) {
    return <label className={className}>{children}</label>;
}
