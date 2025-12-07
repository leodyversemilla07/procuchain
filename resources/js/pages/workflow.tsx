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

// Stage definitions with mode applicability
interface Stage {
    id: string;
    name: string;
    phase: string;
    icon: LucideIcon;
    description: string;
    optional?: boolean;
    repeatable?: boolean;
    details: string[];
    documents: string[];
    modes: string[];
}

const stages: Stage[] = [
    // Pre-Procurement Phase
    {
        id: 'procurement_initiation',
        name: 'Procurement Initiation',
        phase: 'pre_procurement',
        icon: Play,
        description: 'Initial stage where procurement requirements are defined and approved',
        details: [
            'Preparation of Purchase Request (PR)',
            'Project Procurement Management Plan (PPMP)',
            'Annual Procurement Plan (APP) inclusion',
            'Budget allocation and certification',
            'Specification preparation and market study',
        ],
        documents: ['Purchase Request', 'PPMP', 'APP Entry', 'Budget Certification', 'Technical Specifications'],
        modes: [
            'competitive_bidding',
            'limited_source_bidding',
            'competitive_dialogue',
            'unsolicited_offer',
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_sti',
        ],
    },
    {
        id: 'pre_procurement_conference',
        name: 'Pre-Procurement Conference',
        phase: 'pre_procurement',
        icon: Users,
        description: 'Optional conference to discuss procurement requirements with stakeholders',
        optional: true,
        details: [
            'Review of procurement documents',
            'Validation of technical specifications',
            'Budget adequacy assessment',
            'Timeline and milestone setting',
            'Readiness confirmation by BAC',
        ],
        documents: ['Pre-Procurement Conference Minutes', 'Attendance Sheet', 'Readiness Checklist'],
        modes: ['competitive_bidding', 'limited_source_bidding', 'competitive_dialogue', 'unsolicited_offer'],
    },
    {
        id: 'bidding_documents',
        name: 'Bidding Documents',
        phase: 'pre_procurement',
        icon: FileText,
        description: 'Preparation and publication of official bidding documents',
        details: [
            'Preparation of Invitation to Bid (ITB)',
            'Instructions to Bidders (IB)',
            'Bid Data Sheet (BDS)',
            'General and Special Conditions of Contract',
            'Technical specifications and drawings',
            'Bill of Quantities / Schedule of Requirements',
        ],
        documents: ['Invitation to Bid', 'Bidding Documents Package', 'PhilGEPS Posting', 'Bid Bulletin (if any)'],
        modes: ['competitive_bidding', 'limited_source_bidding', 'competitive_dialogue', 'unsolicited_offer'],
    },
    {
        id: 'request_for_quotation',
        name: 'Request for Quotation',
        phase: 'pre_procurement',
        icon: ClipboardList,
        description: 'Preparation and sending of RFQ to suppliers',
        details: [
            'Preparation of RFQ documents',
            'Selection of suppliers to invite',
            'Distribution of RFQ to at least 3 suppliers',
            'Supplier inquiries and clarifications',
            'Setting of submission deadline',
        ],
        documents: ['Request for Quotation Form', 'Technical Specifications', 'Terms of Reference'],
        modes: [
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_sti',
        ],
    },

    // Procurement Phase
    {
        id: 'pre_bid_conference',
        name: 'Pre-Bid Conference',
        phase: 'procurement',
        icon: MessageSquare,
        description: 'Conference to clarify bidding requirements and answer bidder questions',
        details: [
            'Presentation of procurement requirements',
            'Response to bidder queries and clarifications',
            'Site visit arrangements (if applicable)',
            'Recording of all queries and responses',
            'Distribution of conference minutes',
        ],
        documents: ['Pre-Bid Conference Minutes', 'Attendance Sheet', 'Questions and Answers Summary'],
        modes: ['competitive_bidding', 'limited_source_bidding', 'competitive_dialogue', 'unsolicited_offer'],
    },
    {
        id: 'supplemental_bid_bulletin',
        name: 'Supplemental Bid Bulletin',
        phase: 'procurement',
        icon: FileCheck,
        description: 'Issuance of supplemental bulletins to modify or clarify bidding documents',
        optional: true,
        repeatable: true,
        details: [
            'Clarification of ambiguous specifications',
            'Correction of errors in bidding documents',
            'Response to written bidder queries',
            'Extension of bid submission deadline',
            'Amendment to terms and conditions',
        ],
        documents: ['Supplemental/Bid Bulletin', 'Amendment to Bidding Documents'],
        modes: ['competitive_bidding', 'limited_source_bidding', 'competitive_dialogue', 'unsolicited_offer'],
    },
    {
        id: 'bid_opening',
        name: 'Bid Opening',
        phase: 'procurement',
        icon: BookOpen,
        description: 'Public opening and recording of submitted bids',
        details: [
            'Verification of sealed bid envelopes',
            'Checking of bid security',
            'Opening of technical and financial proposals',
            'Recording of bid amounts',
            'Preliminary examination of bids',
        ],
        documents: ['Bid Opening Minutes', 'Attendance Sheet', 'Abstract of Bids', 'Checklist of Requirements'],
        modes: ['competitive_bidding', 'limited_source_bidding', 'competitive_dialogue', 'unsolicited_offer'],
    },
    {
        id: 'abstract_of_quotations',
        name: 'Abstract of Quotations',
        phase: 'procurement',
        icon: ListChecks,
        description: 'Compilation and evaluation of received quotations',
        details: [
            'Collection of all quotation submissions',
            'Comparison of prices and terms',
            'Verification of supplier eligibility',
            'Determination of lowest calculated quotation',
            'Documentation of evaluation process',
        ],
        documents: ['Abstract of Quotations', 'Quotation Evaluation Sheet', 'Supplier Comparison Matrix'],
        modes: [
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_sti',
        ],
    },
    {
        id: 'bid_evaluation',
        name: 'Bid Evaluation',
        phase: 'procurement',
        icon: FileSearch,
        description: 'Technical and financial evaluation of submitted bids',
        details: [
            'Detailed evaluation against specifications',
            'Verification of bid computation',
            'Assessment of technical compliance',
            'Financial capability evaluation',
            'Determination of Lowest Calculated Bid (LCB)',
        ],
        documents: ['Technical Evaluation Report', 'Financial Evaluation Report', 'Bid Evaluation Report'],
        modes: ['competitive_bidding', 'limited_source_bidding', 'competitive_dialogue', 'unsolicited_offer'],
    },
    {
        id: 'post_qualification',
        name: 'Post-Qualification',
        phase: 'procurement',
        icon: Search,
        description: "Verification of winning bidder's qualifications and capacity",
        details: [
            'Verification of legal requirements',
            'Technical capability assessment',
            'Financial capability verification',
            'Site inspection (if applicable)',
            'Reference checking',
        ],
        documents: ['Post-Qualification Report', 'Compliance Checklist', 'Site Inspection Report'],
        modes: ['competitive_bidding', 'limited_source_bidding', 'competitive_dialogue', 'unsolicited_offer'],
    },
    {
        id: 'bac_resolution',
        name: 'BAC Resolution',
        phase: 'procurement',
        icon: Gavel,
        description: 'Formal resolution by the Bids and Awards Committee',
        details: [
            'Declaration of Lowest Calculated Responsive Bid',
            'Recommendation for award',
            'Documentation of BAC decision',
            'Approval by Head of Procuring Entity',
            'Filing of motion for reconsideration period',
        ],
        documents: ['BAC Resolution', 'Minutes of BAC Meeting', 'Recommendation Letter'],
        modes: [
            'competitive_bidding',
            'limited_source_bidding',
            'competitive_dialogue',
            'unsolicited_offer',
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_sti',
        ],
    },

    // Post-Procurement Phase
    {
        id: 'notice_of_award',
        name: 'Notice of Award',
        phase: 'post_procurement',
        icon: Award,
        description: 'Official notification of contract award to winning bidder',
        details: [
            'Preparation and signing of NOA',
            'Notification to winning bidder',
            'Posting on PhilGEPS and agency website',
            'Notice to unsuccessful bidders',
            'Setting deadline for contract signing',
        ],
        documents: ['Notice of Award', 'Transmittal Letter', 'PhilGEPS Posting Confirmation'],
        modes: [
            'competitive_bidding',
            'limited_source_bidding',
            'competitive_dialogue',
            'unsolicited_offer',
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_sti',
        ],
    },
    {
        id: 'performance_bond_contract_and_po',
        name: 'Performance Bond, Contract & PO',
        phase: 'post_procurement',
        icon: ShieldCheck,
        description: 'Submission of performance bond, contract signing, and purchase order issuance',
        details: [
            'Submission of performance security',
            'Verification of bond authenticity',
            'Contract preparation and notarization',
            'Purchase order issuance',
            'PhilGEPS award notice posting',
        ],
        documents: ['Performance Bond', 'Contract Agreement', 'Purchase Order', 'Notice to Proceed'],
        modes: [
            'competitive_bidding',
            'limited_source_bidding',
            'competitive_dialogue',
            'unsolicited_offer',
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_sti',
        ],
    },
    {
        id: 'notice_to_proceed',
        name: 'Notice to Proceed',
        phase: 'post_procurement',
        icon: Target,
        description: 'Authorization for contractor to begin work or delivery',
        details: [
            'Issuance of NTP to winning bidder',
            'Setting of contract effectivity date',
            'Coordination with end-user unit',
            'Mobilization preparation',
            'Timeline confirmation',
        ],
        documents: ['Notice to Proceed', 'Acknowledgment Receipt'],
        modes: [
            'competitive_bidding',
            'limited_source_bidding',
            'competitive_dialogue',
            'unsolicited_offer',
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_sti',
        ],
    },
    {
        id: 'monitoring',
        name: 'Monitoring',
        phase: 'post_procurement',
        icon: ClipboardCheck,
        description: 'Active monitoring of contract implementation',
        details: [
            'Progress tracking and reporting',
            'Quality assurance inspections',
            'Delivery verification',
            'Issue resolution and documentation',
            'Milestone and payment processing',
        ],
        documents: ['Progress Reports', 'Inspection Reports', 'Delivery Receipts', 'Payment Vouchers'],
        modes: [
            'competitive_bidding',
            'limited_source_bidding',
            'competitive_dialogue',
            'unsolicited_offer',
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_sti',
        ],
    },
    {
        id: 'completion',
        name: 'Completion',
        phase: 'post_procurement',
        icon: HardHat,
        description: 'Final stage of contract completion and acceptance',
        details: [
            'Final inspection and acceptance',
            'Preparation of completion report',
            'Final payment processing',
            'Performance evaluation',
            'Contract closeout documentation',
        ],
        documents: ['Inspection and Acceptance Report', 'Certificate of Completion', 'Final Payment Voucher'],
        modes: [
            'competitive_bidding',
            'limited_source_bidding',
            'competitive_dialogue',
            'unsolicited_offer',
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_sti',
        ],
    },
    {
        id: 'completed',
        name: 'Completed',
        phase: 'post_procurement',
        icon: CheckCircle2,
        description: 'Procurement process fully completed and closed',
        details: [
            'All deliverables received and accepted',
            'All payments processed',
            'Contract formally closed',
            'Documentation archived',
            'Performance bond released (if applicable)',
        ],
        documents: ['Certificate of Final Acceptance', 'Release of Performance Bond', 'Final Documentation Package'],
        modes: [
            'competitive_bidding',
            'limited_source_bidding',
            'competitive_dialogue',
            'unsolicited_offer',
            'direct_contracting',
            'direct_acquisition',
            'repeat_order',
            'small_value_procurement',
            'negotiated_procurement',
            'direct_sales',
            'direct_procurement_sti',
        ],
    },
];

// Procurement modes based on NGPA IRR Section 26
const procurementModes = [
    // Competitive Modes
    {
        id: 'competitive_bidding',
        name: 'Competitive Bidding',
        category: 'competitive',
        section: 'Section 27',
        description: 'Open to participation by any eligible bidder through full bidding process',
        icon: Briefcase,
    },
    {
        id: 'limited_source_bidding',
        name: 'Limited Source Bidding',
        category: 'competitive',
        section: 'Section 28',
        description: 'Direct invitation to pre-selected suppliers with known experience and proven capability',
        icon: Users,
    },
    {
        id: 'competitive_dialogue',
        name: 'Competitive Dialogue',
        category: 'competitive',
        section: 'Section 29',
        description: 'Two-stage process for complex or innovative procurement needs',
        icon: MessageSquare,
    },
    {
        id: 'unsolicited_offer',
        name: 'Unsolicited Offer with Bid Matching',
        category: 'competitive',
        section: 'Section 30',
        description: 'Consideration of unsolicited offers for new concepts or technology with bid matching',
        icon: Lightbulb,
    },
    // Alternative Modes
    {
        id: 'direct_contracting',
        name: 'Direct Contracting',
        category: 'alternative',
        section: 'Section 31',
        description: 'Procurement of proprietary goods or from exclusive dealer/manufacturer',
        icon: ShoppingCart,
    },
    {
        id: 'direct_acquisition',
        name: 'Direct Acquisition',
        category: 'alternative',
        section: 'Section 32',
        description: 'Procurement of goods/services with ABC not exceeding ₱200,000',
        icon: Package,
    },
    {
        id: 'repeat_order',
        name: 'Repeat Order',
        category: 'alternative',
        section: 'Section 33',
        description: 'Replenishment from previous winning bidder within 6 months (max 25% of original)',
        icon: RefreshCw,
    },
    {
        id: 'small_value_procurement',
        name: 'Small Value Procurement',
        category: 'alternative',
        section: 'Section 34',
        description: 'Request for at least 3 price quotations (up to ₱2,000,000 threshold)',
        icon: ClipboardList,
    },
    {
        id: 'negotiated_procurement',
        name: 'Negotiated Procurement',
        category: 'alternative',
        section: 'Section 35',
        description: 'Direct negotiation for failed biddings, emergencies, or special cases',
        icon: Gavel,
    },
    {
        id: 'direct_sales',
        name: 'Direct Sales',
        category: 'alternative',
        section: 'Section 36',
        description: 'Purchase from supplier that satisfactorily delivered to another government agency',
        icon: Store,
    },
    {
        id: 'direct_procurement_sti',
        name: 'Direct Procurement for STI',
        category: 'alternative',
        section: 'Section 37',
        description: 'Procurement for science, technology, innovation, and research & development',
        icon: Zap,
    },
];

function StageCard({ stage, index }: { stage: Stage; index: number }) {
    const Icon = stage.icon;

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
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function FlowDiagram({ selectedMode }: { selectedMode: string }) {
    const modeStages = stages.filter((s) => s.modes.includes(selectedMode));

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
                        const phaseStages = modeStages.filter((s) => s.phase === phase.id);
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
                                        const Icon = stage.icon;
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

function WorkflowByMode({ selectedMode }: { selectedMode: string }) {
    const modeStages = stages.filter((s) => s.modes.includes(selectedMode));

    const phaseIcons: Record<string, LucideIcon> = {
        pre_procurement: ClipboardList,
        procurement: FileSearch,
        post_procurement: Award,
    };

    let stageNumber = 0;
    return (
        <div className="space-y-12">
            {phases.map((phase) => {
                const phaseStages = modeStages.filter((s) => s.phase === phase.id);
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

export default function Workflow() {
    const [selectedMode, setSelectedMode] = useState('competitive_bidding');
    const currentMode = procurementModes.find((m) => m.id === selectedMode)!;
    const modeStages = stages.filter((s) => s.modes.includes(selectedMode));
    const CurrentModeIcon = currentMode.icon;

    return (
        <>
            <Head title="Procurement Workflow">
                <meta
                    name="description"
                    content="Explore the complete government procurement workflow in ProcuChain - from initiation to completion, with blockchain-verified transparency at every stage."
                />
                <meta
                    name="keywords"
                    content="procurement workflow, government procurement, RA 9184, RA 12009, NGPA, competitive bidding, BAC, blockchain procurement"
                />

                {/* Open Graph / Facebook */}
                <meta property="og:type" content="website" />
                <meta property="og:url" content={window.location.href} />
                <meta property="og:title" content="Procurement Workflow - ProcuChain" />
                <meta
                    property="og:description"
                    content="Explore the complete government procurement workflow in ProcuChain - from initiation to completion, with blockchain-verified transparency."
                />
                <meta property="og:image" content="/logo.png" />

                {/* Twitter */}
                <meta property="twitter:card" content="summary_large_image" />
                <meta property="twitter:url" content={window.location.href} />
                <meta property="twitter:title" content="Procurement Workflow - ProcuChain" />
                <meta
                    property="twitter:description"
                    content="Explore the complete government procurement workflow in ProcuChain - from initiation to completion."
                />
                <meta property="twitter:image" content="/logo.png" />
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

                            <Tabs defaultValue="competitive" className="mx-auto w-full max-w-6xl">
                                <TabsList className="mb-4 grid h-auto w-full grid-cols-2 sm:mb-6">
                                    <TabsTrigger value="competitive" className="gap-1.5 px-2 py-2 text-xs sm:gap-2 sm:px-4 sm:py-2.5 sm:text-sm">
                                        <Scale className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                        <span className="xs:inline hidden">Competitive</span>
                                        <span className="xs:hidden">Competitive</span>
                                    </TabsTrigger>
                                    <TabsTrigger value="alternative" className="gap-1.5 px-2 py-2 text-xs sm:gap-2 sm:px-4 sm:py-2.5 sm:text-sm">
                                        <Layers className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                        <span className="xs:inline hidden">Alternative</span>
                                        <span className="xs:hidden">Alternative</span>
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent value="competitive">
                                    <Card className="bg-muted border-0">
                                        <CardContent className="p-4 sm:p-6">
                                            <p className="text-muted-foreground mb-4 text-center text-xs sm:mb-6 sm:text-sm">
                                                Full bidding process with open competition - the default method for government procurement
                                            </p>
                                            <div className="grid gap-3 sm:grid-cols-2 sm:gap-4">
                                                {procurementModes
                                                    .filter((m) => m.category === 'competitive')
                                                    .map((mode) => {
                                                        const ModeIcon = mode.icon;
                                                        return (
                                                            <button
                                                                key={mode.id}
                                                                onClick={() => setSelectedMode(mode.id)}
                                                                className={`bg-card cursor-pointer rounded-lg border p-3 text-left transition-all hover:shadow-md sm:p-4 ${
                                                                    selectedMode === mode.id
                                                                        ? 'border-primary ring-primary ring-2'
                                                                        : 'hover:border-primary/50'
                                                                }`}
                                                            >
                                                                <div className="mb-2 flex items-center gap-2 sm:mb-3 sm:gap-3">
                                                                    <div className="bg-primary/10 rounded-lg p-1.5 sm:p-2">
                                                                        <ModeIcon className="text-primary h-4 w-4 sm:h-5 sm:w-5" />
                                                                    </div>
                                                                    <Badge variant="secondary" className="text-[10px] sm:text-xs">
                                                                        {mode.section}
                                                                    </Badge>
                                                                </div>
                                                                <h4 className="mb-1 text-sm font-semibold sm:text-base">{mode.name}</h4>
                                                                <p className="text-muted-foreground text-xs sm:text-sm">{mode.description}</p>
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
                                                {procurementModes
                                                    .filter((m) => m.category === 'alternative')
                                                    .map((mode) => {
                                                        const ModeIcon = mode.icon;
                                                        return (
                                                            <button
                                                                key={mode.id}
                                                                onClick={() => setSelectedMode(mode.id)}
                                                                className={`bg-card cursor-pointer rounded-lg border p-3 text-left transition-all hover:shadow-md sm:p-4 ${
                                                                    selectedMode === mode.id
                                                                        ? 'border-primary ring-primary ring-2'
                                                                        : 'hover:border-primary/50'
                                                                }`}
                                                            >
                                                                <div className="mb-2 flex items-center gap-2 sm:mb-3 sm:gap-3">
                                                                    <div className="rounded-lg bg-amber-500/10 p-1.5 sm:p-2">
                                                                        <ModeIcon className="h-4 w-4 text-amber-600 sm:h-5 sm:w-5 dark:text-amber-400" />
                                                                    </div>
                                                                    <Badge variant="secondary" className="text-[10px] sm:text-xs">
                                                                        {mode.section}
                                                                    </Badge>
                                                                </div>
                                                                <h4 className="mb-1 text-sm font-semibold sm:text-base">{mode.name}</h4>
                                                                <p className="text-muted-foreground line-clamp-2 text-xs sm:text-sm">
                                                                    {mode.description}
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
                                                className={`rounded-lg p-2 sm:p-3 ${currentMode.category === 'competitive' ? 'bg-primary/10' : 'bg-amber-500/10'}`}
                                            >
                                                <CurrentModeIcon
                                                    className={`h-5 w-5 sm:h-6 sm:w-6 ${currentMode.category === 'competitive' ? 'text-primary' : 'text-amber-600 dark:text-amber-400'}`}
                                                />
                                            </div>
                                            <div>
                                                <h3 className="text-lg font-semibold sm:text-xl">{currentMode.name}</h3>
                                                <p className="text-muted-foreground text-xs sm:text-sm">{currentMode.description}</p>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                                            <Badge variant="outline" className="text-[10px] sm:text-xs">
                                                {currentMode.section}
                                            </Badge>
                                            <Badge variant="secondary" className="text-[10px] sm:text-xs">
                                                {modeStages.length} Stages
                                            </Badge>
                                            <Badge
                                                className={`text-[10px] sm:text-xs ${
                                                    currentMode.category === 'competitive'
                                                        ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                                        : 'bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 dark:text-amber-400'
                                                }`}
                                            >
                                                {currentMode.category === 'competitive' ? 'Competitive' : 'Alternative'}
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
                                        Visual representation of the procurement stages for {currentMode.name}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="p-4 pt-0 sm:p-6 sm:pt-0">
                                    <FlowDiagram selectedMode={selectedMode} />
                                </CardContent>
                            </Card>
                        </div>

                        {/* Detailed Stages */}
                        <div>
                            <h2 className="mb-6 text-center text-2xl font-bold sm:mb-8 sm:text-3xl">Detailed Stage Information</h2>
                            <WorkflowByMode selectedMode={selectedMode} />
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
