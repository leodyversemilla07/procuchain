import Footer from '@/components/footer';
import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    Award,
    BookOpen,
    Briefcase,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    FileCheck,
    FileSearch,
    FileText,
    Gavel,
    HardHat,
    Layers,
    Lightbulb,
    ListChecks,
    type LucideIcon,
    MessageSquare,
    Package,
    Play,
    RefreshCw,
    Scale,
    Search,
    ShieldCheck,
    ShoppingCart,
    Store,
    Target,
    Users,
    Zap,
} from 'lucide-react';
import { useState } from 'react';

// Procurement phase definitions
const phases = [
    {
        id: 'pre_procurement',
        name: 'Pre-Procurement',
        description: 'Planning & Preparation',
    },
    {
        id: 'procurement',
        name: 'Procurement',
        description: 'Bidding & Evaluation',
    },
    {
        id: 'post_procurement',
        name: 'Post-Procurement',
        description: 'Award & Implementation',
    },
];

// Icon mapping for stages
const stageIcons: Record<string, LucideIcon> = {
    procurement_initiation: Play,
    pre_procurement_conference: Users,
    bidding_documents: FileText,
    request_for_quotation: ClipboardList,
    pre_bid_conference: MessageSquare,
    supplemental_bid_bulletin: FileCheck,
    bid_opening: BookOpen,
    abstract_of_quotations: ListChecks,
    bid_evaluation: FileSearch,
    post_qualification: Search,
    bac_resolution: Gavel,
    notice_of_award: Award,
    performance_bond_contract_and_po: ShieldCheck,
    notice_to_proceed: Target,
    monitoring: ClipboardCheck,
    completion: HardHat,
    completed: CheckCircle2,
};

// Stage interface matching backend data
interface Stage {
    id: string;
    name: string;
    phase: string;
    description: string;
    optional: boolean;
    repeatable: boolean;
    details: string[];
    documents: string[];
}

interface WorkflowMode {
    mode: string;
    name: string;
    stages: Stage[];
}

interface WorkflowProps {
    workflows: WorkflowMode[];
}

// Procurement modes metadata (static info not in DB)
const procurementModesMetadata: Record<string, { category: string; section: string; description: string; icon: LucideIcon }> = {
    // Competitive Modes
    competitive_bidding: {
        category: 'competitive',
        section: 'Section 27',
        description: 'Open to participation by any eligible bidder through full bidding process',
        icon: Briefcase,
    },
    limited_source_bidding: {
        category: 'competitive',
        section: 'Section 28',
        description: 'Direct invitation to pre-selected suppliers with known experience and proven capability',
        icon: Users,
    },
    competitive_dialogue: {
        category: 'competitive',
        section: 'Section 29',
        description: 'Two-stage process for complex or innovative procurement needs',
        icon: MessageSquare,
    },
    unsolicited_offer_with_bid_matching: {
        category: 'competitive',
        section: 'Section 30',
        description: 'Consideration of unsolicited offers for new concepts or technology with bid matching',
        icon: Lightbulb,
    },
    // Alternative Modes
    direct_contracting: {
        category: 'alternative',
        section: 'Section 31',
        description: 'Procurement of proprietary goods or from exclusive dealer/manufacturer',
        icon: ShoppingCart,
    },
    direct_acquisition: {
        category: 'alternative',
        section: 'Section 32',
        description: 'Procurement of goods/services with ABC not exceeding ₱200,000',
        icon: Package,
    },
    repeat_order: {
        category: 'alternative',
        section: 'Section 33',
        description: 'Replenishment from previous winning bidder within 6 months (max 25% of original)',
        icon: RefreshCw,
    },
    small_value_procurement: {
        category: 'alternative',
        section: 'Section 34',
        description: 'Request for at least 3 price quotations (up to ₱2,000,000 threshold)',
        icon: ClipboardList,
    },
    negotiated_procurement: {
        category: 'alternative',
        section: 'Section 35',
        description: 'Direct negotiation for failed biddings, emergencies, or special cases',
        icon: Gavel,
    },
    direct_sales: {
        category: 'alternative',
        section: 'Section 36',
        description: 'Purchase from supplier that satisfactorily delivered to another government agency',
        icon: Store,
    },
    direct_procurement_for_sti: {
        category: 'alternative',
        section: 'Section 37',
        description: 'Procurement for science, technology, innovation, and research & development',
        icon: Zap,
    },
};

function StageCard({ stage, index }: { stage: Stage; index: number }) {
    const Icon = stageIcons[stage.id] || FileText;

    return (
        <Card className="border">
            <CardHeader className="p-4 pb-2 sm:p-6 sm:pb-3">
                <div className="flex items-start justify-between gap-2 sm:gap-3">
                    <div className="bg-primary/10 rounded-lg p-1.5 sm:p-2">
                        <Icon className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-1 sm:gap-2">
                        {stage.optional && (
                            <Badge variant="outline" className="text-[10px] sm:text-xs">
                                Optional
                            </Badge>
                        )}
                        {stage.repeatable && (
                            <Badge variant="outline" className="text-[10px] sm:text-xs">
                                Repeatable
                            </Badge>
                        )}
                        <Badge variant="secondary" className="text-[10px] sm:text-xs">
                            {String(index).padStart(2, '0')}
                        </Badge>
                    </div>
                </div>
                <CardTitle className="text-base sm:text-lg">{stage.name}</CardTitle>
                <CardDescription className="text-xs sm:text-sm">{stage.description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3 p-4 pt-0 sm:space-y-4 sm:p-6 sm:pt-0">
                <div>
                    <h4 className="mb-1.5 text-xs font-medium sm:mb-2 sm:text-sm">Key Activities</h4>
                    <ul className="space-y-1">
                        {stage.details.slice(0, 3).map((detail, i) => (
                            <li key={i} className="text-muted-foreground flex items-start gap-1.5 text-xs sm:gap-2 sm:text-sm">
                                <CheckCircle2 className="text-primary mt-0.5 h-3 w-3 shrink-0 sm:h-3.5 sm:w-3.5" />
                                <span>{detail}</span>
                            </li>
                        ))}
                        {stage.details.length > 3 && (
                            <li className="text-muted-foreground text-xs sm:text-sm">+{stage.details.length - 3} more activities</li>
                        )}
                    </ul>
                </div>
                <div>
                    <h4 className="mb-1.5 text-xs font-medium sm:mb-2 sm:text-sm">Required Documents</h4>
                    <div className="flex flex-wrap gap-1">
                        {stage.documents.slice(0, 2).map((doc, i) => (
                            <Badge key={i} variant="outline" className="text-[10px] font-normal sm:text-xs">
                                {doc}
                            </Badge>
                        ))}
                        {stage.documents.length > 2 && (
                            <Badge variant="outline" className="text-[10px] font-normal sm:text-xs">
                                +{stage.documents.length - 2} more
                            </Badge>
                        )}
                        {stage.documents.length === 0 && (
                            <span className="text-muted-foreground text-[10px] italic sm:text-xs">No specific documents required</span>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function FlowDiagram({ stages }: { stages: Stage[] }) {
    const phaseColors: Record<string, string> = {
        pre_procurement: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        procurement: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        post_procurement: 'bg-green-500/10 text-green-600 dark:text-green-400',
    };

    return (
        <div className="space-y-6">
            {/* Visual Flow */}
            <div className="overflow-x-auto pb-4">
                <div className="min-w-[320px] sm:min-w-[600px] lg:min-w-[800px]">
                    {phases.map((phase, phaseIndex) => {
                        const phaseStages = stages.filter((s) => s.phase === phase.id);
                        if (phaseStages.length === 0) return null;
                        return (
                            <div key={phase.id} className="mb-6 last:mb-0">
                                <div
                                    className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 sm:px-4 sm:py-1.5 ${phaseColors[phase.id]}`}
                                >
                                    <span className="text-xs font-medium sm:text-sm">{phase.name}</span>
                                </div>
                                <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                                    {phaseStages.map((stage, index) => {
                                        const Icon = stageIcons[stage.id] || FileText;
                                        const isLast = index === phaseStages.length - 1;
                                        const isLastPhase = phaseIndex === phases.length - 1;
                                        return (
                                            <div key={stage.id} className="flex items-center">
                                                <div
                                                    className={`bg-card group flex cursor-default items-center gap-1.5 rounded-lg border px-2 py-1.5 shadow-sm transition-all hover:shadow-md sm:gap-2 sm:px-3 sm:py-2 ${stage.optional ? 'border-dashed' : ''}`}
                                                >
                                                    <Icon className="text-primary h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                                    <span className="text-xs font-medium sm:text-sm sm:whitespace-nowrap">{stage.name}</span>
                                                    {stage.optional && (
                                                        <span title="Optional Stage" className="hidden sm:inline">
                                                            <AlertCircle className="text-muted-foreground h-3 w-3" />
                                                        </span>
                                                    )}
                                                    {stage.repeatable && (
                                                        <span title="Can be repeated" className="hidden sm:inline">
                                                            <RefreshCw className="text-muted-foreground h-3 w-3" />
                                                        </span>
                                                    )}
                                                </div>
                                                {(!isLast || !isLastPhase) && (
                                                    <ArrowRight className="text-muted-foreground mx-0.5 h-3 w-3 shrink-0 sm:mx-1 sm:h-4 sm:w-4" />
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Legend */}
            <div className="bg-muted/50 flex flex-wrap items-center gap-3 rounded-lg border p-3 sm:gap-6 sm:p-4">
                <span className="text-xs font-medium sm:text-sm">Legend:</span>
                <div className="flex items-center gap-1.5 sm:gap-2">
                    <div className="rounded border border-dashed px-1.5 py-0.5 sm:px-2 sm:py-1">
                        <AlertCircle className="text-muted-foreground h-2.5 w-2.5 sm:h-3 sm:w-3" />
                    </div>
                    <span className="text-muted-foreground text-xs sm:text-sm">Optional Stage</span>
                </div>
                <div className="flex items-center gap-1.5 sm:gap-2">
                    <RefreshCw className="text-muted-foreground h-2.5 w-2.5 sm:h-3 sm:w-3" />
                    <span className="text-muted-foreground text-xs sm:text-sm">Repeatable</span>
                </div>
            </div>
        </div>
    );
}

function WorkflowByMode({ stages }: { stages: Stage[] }) {
    const phaseIcons: Record<string, LucideIcon> = {
        pre_procurement: ClipboardList,
        procurement: FileSearch,
        post_procurement: Award,
    };

    let stageNumber = 0;
    return (
        <div className="space-y-12">
            {phases.map((phase) => {
                const phaseStages = stages.filter((s) => s.phase === phase.id);
                if (phaseStages.length === 0) return null;
                const PhaseIcon = phaseIcons[phase.id];
                return (
                    <div key={phase.id}>
                        <Card className="mb-4 border sm:mb-6">
                            <CardContent className="p-4 sm:p-6">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                                    <div className="flex items-center gap-3 sm:gap-4">
                                        <div className="bg-primary/10 rounded-lg p-2 sm:p-3">
                                            <PhaseIcon className="text-primary h-5 w-5 sm:h-6 sm:w-6" />
                                        </div>
                                        <div>
                                            <h3 className="text-lg font-semibold sm:text-xl">{phase.name}</h3>
                                            <p className="text-muted-foreground text-xs sm:text-sm">{phase.description}</p>
                                        </div>
                                    </div>
                                    <Badge variant="secondary" className="w-fit sm:ml-auto">
                                        {phaseStages.length} {phaseStages.length === 1 ? 'Stage' : 'Stages'}
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>
                        <div className="grid gap-3 sm:gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {phaseStages.map((stage) => {
                                stageNumber++;
                                return <StageCard key={stage.id} stage={stage} index={stageNumber} />;
                            })}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default function Workflow({ workflows }: WorkflowProps) {
    const [selectedMode, setSelectedMode] = useState('competitive_bidding');

    // Fallback if no workflows are loaded or the selected mode isn't available
    const currentWorkflow = workflows.find((w) => w.mode === selectedMode) ||
        workflows[0] || {
            mode: 'unknown',
            name: 'Unknown Mode',
            stages: [],
        };

    const currentModeMetadata = procurementModesMetadata[currentWorkflow.mode] || {
        category: 'unknown',
        section: '',
        description: 'Custom Procurement Mode',
        icon: Briefcase,
    };

    const CurrentModeIcon = currentModeMetadata.icon;

    return (
        <>
            <Head title="Procurement Workflow">
                <meta
                    name="description"
                    content="Explore the complete government procurement workflow in ProcuChain - from initiation to completion, with blockchain-verified transparency at every stage."
                />
            </Head>
            <div className="bg-background flex min-h-screen flex-col">
                <Header />

                <main className="flex-1">
                    <div className="container mx-auto px-4 py-8 sm:px-8 sm:py-12 md:px-12 lg:px-16 lg:py-16 xl:px-20">
                        {/* Hero Section */}
                        <div className="mx-auto mb-10 max-w-4xl text-center sm:mb-16">
                            <h1 className="mb-3 text-3xl font-bold sm:mb-4 sm:text-4xl md:text-5xl lg:text-6xl">Procurement Workflow</h1>
                            <p className="text-muted-foreground text-sm sm:text-base lg:text-lg">
                                Explore the procurement stages based on the mode of procurement as defined in the NGPA IRR (RA 12009).
                            </p>
                        </div>

                        {/* Overview Section */}
                        <div className="mb-10 sm:mb-16">
                            <Card className="border">
                                <CardContent className="p-4 sm:p-6">
                                    <div className="mx-auto max-w-3xl">
                                        <h2 className="mb-3 text-xl font-semibold sm:mb-4 sm:text-2xl">Understanding Procurement Modes</h2>
                                        <p className="text-muted-foreground mb-3 text-sm sm:mb-4 sm:text-base">
                                            Under the New Government Procurement Act (RA 12009), government agencies can procure goods, services, and
                                            infrastructure through various procurement modes. Each mode has specific procedures and requirements
                                            designed to ensure transparency, competition, and value for money.
                                        </p>
                                        <p className="text-muted-foreground text-sm sm:text-base">
                                            Select a procurement mode below to see its specific workflow stages, required documents, and key
                                            activities at each step of the process.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Mode Selection with Tabs */}
                        <div className="mb-10 sm:mb-16">
                            <h2 className="mb-6 text-center text-2xl font-bold sm:mb-8 sm:text-3xl">Select Procurement Mode</h2>

                            <Tabs defaultValue="competitive" className="mx-auto flex w-full max-w-6xl flex-col gap-4 sm:gap-6">
                                <TabsList className="mx-auto grid w-full max-w-xl grid-cols-2">
                                    <TabsTrigger value="competitive">
                                        <Scale data-icon="inline-start" />
                                        Competitive
                                    </TabsTrigger>
                                    <TabsTrigger value="alternative">
                                        <Layers data-icon="inline-start" />
                                        Alternative
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent value="competitive">
                                    <Card className="bg-muted border-0">
                                        <CardContent className="p-4 sm:p-6">
                                            <p className="text-muted-foreground mb-4 text-center text-xs sm:mb-6 sm:text-sm">
                                                Full bidding process with open competition - the default method for government procurement
                                            </p>
                                            <div className="grid gap-3 sm:grid-cols-2 sm:gap-4">
                                                {workflows
                                                    .filter((w) => {
                                                        const meta = procurementModesMetadata[w.mode];
                                                        return meta && meta.category === 'competitive';
                                                    })
                                                    .map((workflow) => {
                                                        const meta = procurementModesMetadata[workflow.mode];
                                                        const ModeIcon = meta.icon;
                                                        return (
                                                            <button
                                                                key={workflow.mode}
                                                                onClick={() => setSelectedMode(workflow.mode)}
                                                                className={`bg-card cursor-pointer rounded-lg border p-3 text-left transition-all hover:shadow-md sm:p-4 ${
                                                                    selectedMode === workflow.mode
                                                                        ? 'border-primary ring-primary ring-2'
                                                                        : 'hover:border-primary/50'
                                                                }`}
                                                            >
                                                                <div className="mb-2 flex items-center gap-2 sm:mb-3 sm:gap-3">
                                                                    <div className="bg-primary/10 rounded-lg p-1.5 sm:p-2">
                                                                        <ModeIcon className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                                                    </div>
                                                                    <Badge variant="secondary" className="text-[10px] sm:text-xs">
                                                                        {meta.section}
                                                                    </Badge>
                                                                </div>
                                                                <h4 className="mb-1 text-sm font-semibold sm:text-base">{workflow.name}</h4>
                                                                <p className="text-muted-foreground text-xs sm:text-sm">{meta.description}</p>
                                                            </button>
                                                        );
                                                    })}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>

                                <TabsContent value="alternative">
                                    <Card className="bg-muted border-0">
                                        <CardContent className="p-4 sm:p-6">
                                            <p className="text-muted-foreground mb-4 text-center text-xs sm:mb-6 sm:text-sm">
                                                Simplified procedures for specific circumstances when competitive bidding is not feasible
                                            </p>
                                            <div className="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                                                {workflows
                                                    .filter((w) => {
                                                        const meta = procurementModesMetadata[w.mode];
                                                        return meta && meta.category === 'alternative';
                                                    })
                                                    .map((workflow) => {
                                                        const meta = procurementModesMetadata[workflow.mode];
                                                        const ModeIcon = meta.icon;
                                                        return (
                                                            <button
                                                                key={workflow.mode}
                                                                onClick={() => setSelectedMode(workflow.mode)}
                                                                className={`bg-card cursor-pointer rounded-lg border p-3 text-left transition-all hover:shadow-md sm:p-4 ${
                                                                    selectedMode === workflow.mode
                                                                        ? 'border-primary ring-primary ring-2'
                                                                        : 'hover:border-primary/50'
                                                                }`}
                                                            >
                                                                <div className="mb-2 flex items-center gap-2 sm:mb-3 sm:gap-3">
                                                                    <div className="rounded-lg bg-amber-500/10 p-1.5 sm:p-2">
                                                                        <ModeIcon className="h-4 w-4 text-amber-600 sm:h-5 sm:w-5 dark:text-amber-400" />
                                                                    </div>
                                                                    <Badge variant="secondary" className="text-[10px] sm:text-xs">
                                                                        {meta.section}
                                                                    </Badge>
                                                                </div>
                                                                <h4 className="mb-1 text-sm font-semibold sm:text-base">{workflow.name}</h4>
                                                                <p className="text-muted-foreground line-clamp-2 text-xs sm:text-sm">
                                                                    {meta.description}
                                                                </p>
                                                            </button>
                                                        );
                                                    })}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                            </Tabs>
                        </div>

                        {/* Selected Mode Summary */}
                        <div className="mb-6 sm:mb-8">
                            <Card className="border">
                                <CardContent className="p-4 sm:p-6">
                                    <div className="flex flex-col gap-3 sm:gap-4 md:flex-row md:items-center md:justify-between">
                                        <div className="flex items-center gap-3 sm:gap-4">
                                            <div
                                                className={`rounded-lg p-2 sm:p-3 ${currentModeMetadata.category === 'competitive' ? 'bg-primary/10' : 'bg-amber-500/10'}`}
                                            >
                                                <CurrentModeIcon
                                                    className={`h-5 w-5 sm:h-6 sm:w-6 ${currentModeMetadata.category === 'competitive' ? 'text-primary' : 'text-amber-600 dark:text-amber-400'}`}
                                                />
                                            </div>
                                            <div>
                                                <h3 className="text-lg font-semibold sm:text-xl">{currentWorkflow.name}</h3>
                                                <p className="text-muted-foreground text-xs sm:text-sm">{currentModeMetadata.description}</p>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                                            <Badge variant="outline" className="text-[10px] sm:text-xs">
                                                {currentModeMetadata.section}
                                            </Badge>
                                            <Badge variant="secondary" className="text-[10px] sm:text-xs">
                                                {currentWorkflow.stages.length} Stages
                                            </Badge>
                                            <Badge
                                                className={`text-[10px] sm:text-xs ${
                                                    currentModeMetadata.category === 'competitive'
                                                        ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                                        : 'bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 dark:text-amber-400'
                                                }`}
                                            >
                                                {currentModeMetadata.category === 'competitive' ? 'Competitive' : 'Alternative'}
                                            </Badge>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Flow Diagram */}
                        <div className="mb-10 sm:mb-16">
                            <Card className="border">
                                <CardHeader className="p-4 sm:p-6">
                                    <CardTitle className="text-lg sm:text-xl">Workflow Flow Diagram</CardTitle>
                                    <CardDescription className="text-xs sm:text-sm">
                                        Visual representation of the procurement stages for {currentWorkflow.name}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                                    <FlowDiagram stages={currentWorkflow.stages} />
                                </CardContent>
                            </Card>
                        </div>

                        {/* Detailed Stages */}
                        <div>
                            <h2 className="mb-6 text-center text-2xl font-bold sm:mb-8 sm:text-3xl">Detailed Stage Information</h2>
                            <WorkflowByMode stages={currentWorkflow.stages} />
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
