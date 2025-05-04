import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import {
    Shield,
    Activity,
    FileText,
    CheckCircle,
    User,
    Files,
    BarChart2,
    Lock,
    Database,
    Zap,
    ExternalLink
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs";

// Updated Stage enum based on PHP StageEnums
enum Stage {
    PROCUREMENT_INITIATION = 'Procurement Initiation',
    PRE_PROCUREMENT_CONFERENCE = 'Pre-Procurement Conference',
    BIDDING_DOCUMENTS = 'Bidding Documents',
    PRE_BID_CONFERENCE = 'Pre-Bid Conference',
    SUPPLEMENTAL_BID_BULLETIN = 'Supplemental Bid Bulletin',
    BID_OPENING = 'Bid Opening',
    BID_EVALUATION = 'Bid Evaluation',
    POST_QUALIFICATION = 'Post-Qualification',
    BAC_RESOLUTION = 'BAC Resolution',
    NOTICE_OF_AWARD = 'Notice of Award',
    PERFORMANCE_BOND_CONTRACT_AND_PO = 'Performance Bond, Contract and PO',
    NOTICE_TO_PROCEED = 'Notice to Proceed',
    MONITORING = 'Monitoring',
    COMPLETION = 'Completion', // Added Completion based on PHP enum
    // Note: The PHP enum has both COMPLETION and COMPLETED.
    // Assuming 'COMPLETED' in the original TS maps to 'Completion' or 'Completed' in PHP.
    // Adjust if 'Completed' needs a separate entry.
}


interface ProcurementStage {
    id: number;
    stage: Stage;
    description: string;
    documents: string[];
    icon: React.ReactNode;
}

export default function Features() {
    const [activeStage, setActiveStage] = useState<number | null>(1);
    const [activeFeature, setActiveFeature] = useState<string>("blockchain");

    const getStageShortName = (stageName: Stage): string => {
        switch (stageName) {
            case Stage.PROCUREMENT_INITIATION: return "PR";
            case Stage.PRE_PROCUREMENT_CONFERENCE: return "Pre-Proc";
            case Stage.BIDDING_DOCUMENTS: return "Bidding"; // Changed from Invitation
            case Stage.PRE_BID_CONFERENCE: return "Pre-Bid";
            case Stage.SUPPLEMENTAL_BID_BULLETIN: return "Bulletin";
            case Stage.BID_OPENING: return "Opening";
            case Stage.BID_EVALUATION: return "Evaluation";
            case Stage.POST_QUALIFICATION: return "Post-Qual";
            case Stage.BAC_RESOLUTION: return "BAC";
            case Stage.NOTICE_OF_AWARD: return "NOA";
            case Stage.PERFORMANCE_BOND_CONTRACT_AND_PO: return "Bond/PO";
            case Stage.NOTICE_TO_PROCEED: return "NTP";
            case Stage.MONITORING: return "Monitor"; // Changed from Monitoring
            case Stage.COMPLETION: return "Complete"; // Changed from Completion
            default: {
                // Fallback logic for unexpected stage values
                // This should ideally not be reached if all enum cases are handled.
                // We cast to string to handle the 'never' type, but log a warning.
                console.warn(`Unexpected stage value encountered in getStageShortName: ${stageName}`);
                const stageStr = stageName as string; // Cast to string to use split
                const words = stageStr.split(" ");
                return words.length > 1 ? words.map(w => w[0]).join("") : words[0]?.substring(0, 3) ?? "N/A";
            }
        }
    };

    // Updated procurement stages array using the new Stage enum
    const procurementStages: ProcurementStage[] = [
        {
            id: 1,
            stage: Stage.PROCUREMENT_INITIATION,
            description: "Record finalized procurement request and supporting documents with general and stage-specific metadata.",
            documents: ["Purchase Request", "Certificate of Availability of Funds", "Annual Investment Plan"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 2,
            stage: Stage.PRE_PROCUREMENT_CONFERENCE,
            description: "Record pre-procurement conference documents and decisions.",
            documents: ["Conference Minutes", "Attendance Sheet"],
            icon: <Activity className="w-6 h-6" />
        },
        {
            id: 3,
            stage: Stage.BIDDING_DOCUMENTS,
            description: "Record and publish finalized bidding documents.",
            documents: ["Bidding Documents"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 4,
            stage: Stage.PRE_BID_CONFERENCE,
            description: "Record pre-bid conference proceedings and clarifications.",
            documents: ["Conference Minutes", "Attendance Sheet", "Clarifications"],
            icon: <Activity className="w-6 h-6" />
        },
        {
            id: 5,
            stage: Stage.SUPPLEMENTAL_BID_BULLETIN,
            description: "Record and publish supplemental bulletins if any.",
            documents: ["Supplemental Bulletins", "Amendments"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 6,
            stage: Stage.BID_OPENING,
            description: "Record bid opening proceedings and submitted bids.",
            documents: ["Bid Opening Minutes", "Submitted Bids"],
            icon: <Activity className="w-6 h-6" />
        },
        {
            id: 7,
            stage: Stage.BID_EVALUATION,
            description: "Record bid evaluation results and recommendations.",
            documents: ["BER or Bid Evaluation Report", "Abstract of Bids"],
            icon: <CheckCircle className="w-6 h-6" />
        },
        {
            id: 8,
            stage: Stage.POST_QUALIFICATION,
            description: "Record post-qualification verification results.",
            documents: ["Post Qualification Report", "TWG certification for infra projects", "Notice of Post Qualification"],
            icon: <Activity className="w-6 h-6" />
        },
        {
            id: 9,
            stage: Stage.BAC_RESOLUTION,
            description: "Record BAC resolution and recommendations.",
            documents: ["BAC Resolution"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 10,
            stage: Stage.NOTICE_OF_AWARD,
            description: "Record and publish notice of award.",
            documents: ["Notice of Award"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 11,
            stage: Stage.PERFORMANCE_BOND_CONTRACT_AND_PO,
            description: "Record performance bond, contract, and purchase order documents.",
            documents: ["Performance Bond", "Contract", "Purchase Order"],
            icon: <Lock className="w-6 h-6" />
        },
        {
            id: 12,
            stage: Stage.NOTICE_TO_PROCEED,
            description: "Record and publish notice to proceed.",
            documents: ["Notice to Proceed"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 13,
            stage: Stage.MONITORING,
            description: "Record monitoring reports and updates.",
            documents: ["Monitoring Reports", "Progress Updates"],
            icon: <Activity className="w-6 h-6" />
        },
        {
            id: 14,
            stage: Stage.COMPLETION, // Updated to use Stage.COMPLETION
            description: "Record completion documents and close the procurement process.",
            documents: ["Completion Certificate", "Final Payment", "Inspection Report"],
            icon: <CheckCircle className="w-6 h-6" />
        }
    ];

    // System features (unchanged)
    const systemFeatures = [
        {
            id: "blockchain",
            title: "Blockchain Document Verification",
            description: "Every document uploaded to the system is hashed and stored on a blockchain, ensuring its integrity and immutability.",
            icon: <Lock className="w-10 h-10 text-teal-500" />,
            benefits: [
                "Tamper-proof document storage",
                "Cryptographic verification of document integrity",
                "Permanent audit trail of document changes",
                "Distributed ledger for enhanced security"
            ],
            imageUrl: "/images/blockchain-verification.png",
            fallbackImage: "https://via.placeholder.com/800x500?text=Blockchain+Verification"
        },
        {
            id: "tracking",
            title: "Real-time Tracking",
            description: "Monitor the progress of procurement activities in real-time with automated notifications and status updates.",
            icon: <Activity className="w-10 h-10 text-teal-500" />,
            benefits: [
                "Instant visibility into procurement status",
                "Automated notifications of stage transitions",
                "Time-stamped activity logs",
                "Customizable alerts for key stakeholders"
            ],
            imageUrl: "/images/realtime-tracking.png",
            fallbackImage: "https://via.placeholder.com/800x500?text=Real-time+Tracking"
        },
        {
            id: "audit",
            title: "Comprehensive Audit Trail",
            description: "Every action taken within the system is recorded and traceable, providing complete transparency.",
            icon: <FileText className="w-10 h-10 text-teal-500" />,
            benefits: [
                "Complete history of all system activities",
                "User attribution for every action",
                "Chronological record of document changes",
                "Evidence for compliance and audit purposes"
            ],
            imageUrl: "/images/audit-trail.png",
            fallbackImage: "https://via.placeholder.com/800x500?text=Audit+Trail"
        },
        {
            id: "access",
            title: "Role-based Access Control",
            description: "Different stakeholders have specific permissions ensuring proper segregation of duties.",
            icon: <User className="w-10 h-10 text-teal-500" />,
            benefits: [
                "Granular control over user permissions",
                "Segregation of duties for compliance",
                "Custom roles for different stakeholder needs",
                "Secure access management"
            ],
            imageUrl: "/images/role-based-access.png",
            fallbackImage: "https://via.placeholder.com/800x500?text=Role-based+Access"
        },
        {
            id: "document",
            title: "Document Management System",
            description: "Centralized repository for all procurement-related documents with version control.",
            icon: <Files className="w-10 h-10 text-teal-500" />,
            benefits: [
                "Centralized storage for all procurement documents",
                "Advanced search and filtering capabilities",
                "Document version history and comparisons",
                "Structured organization by procurement stages"
            ],
            imageUrl: "/images/document-management.png",
            fallbackImage: "https://via.placeholder.com/800x500?text=Document+Management"
        },
        {
            id: "analytics",
            title: "Analytics Dashboard",
            description: "Visualize procurement data and identify trends to make informed decisions.",
            icon: <BarChart2 className="w-10 h-10 text-teal-500" />,
            benefits: [
                "Visual representation of procurement metrics",
                "Customizable reports and dashboards",
                "Performance benchmarks and comparisons",
                "Data-driven insights for process improvement"
            ],
            imageUrl: "/images/analytics-dashboard.png",
            fallbackImage: "https://via.placeholder.com/800x500?text=Analytics+Dashboard"
        }
    ];

    // Comparison features (unchanged)
    const comparisonFeatures = [
        {
            feature: "Document Integrity",
            traditional: "Vulnerable to tampering and manipulation",
            procuchain: "Cryptographic verification using blockchain",
            advantage: "Enhanced security and trust"
        },
        {
            feature: "Audit Trail",
            traditional: "Limited, often manual record-keeping",
            procuchain: "Comprehensive, immutable audit trail",
            advantage: "Complete transparency and accountability"
        },
        {
            feature: "Access Control",
            traditional: "Basic user permissions",
            procuchain: "Granular role-based access with blockchain verification",
            advantage: "Secure, auditable access management"
        },
        {
            feature: "Process Transparency",
            traditional: "Limited visibility into process stages",
            procuchain: "Complete visibility with real-time updates",
            advantage: "Increased stakeholder confidence"
        },
        {
            feature: "Document Management",
            traditional: "Often fragmented across systems and physical storage",
            procuchain: "Centralized with blockchain verification",
            advantage: "Single source of truth for all documents"
        },
        {
            feature: "Data Analytics",
            traditional: "Limited reporting capabilities",
            procuchain: "Comprehensive analytics and visualization tools",
            advantage: "Data-driven procurement optimization"
        }
    ];

    return (
        <>
            <Head title="Features">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="Explore the key features of ProcuChain - a blockchain-powered procurement system designed for transparency, security, and efficiency in government procurement processes." />
            </Head>
            <div className="min-h-screen flex flex-col overflow-x-hidden bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative">
                <Header />

                <main className="flex-grow pt-24 pb-16">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {/* Hero Section */}
                        <div className="mb-16 text-center">
                            <div className="inline-block p-2 bg-teal-100/60 dark:bg-teal-900/30 rounded-lg text-teal-700 dark:text-teal-300 mb-4">
                                <Activity className="w-6 h-6" />
                            </div>
                            <h1 className="text-4xl md:text-5xl font-bold mb-6 leading-tight">
                                <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                    System Features
                                </span>
                            </h1>
                            <p className="text-lg text-gray-600 dark:text-gray-300 mb-6 max-w-3xl mx-auto">
                                ProcuChain revolutionizes government procurement with blockchain technology,
                                providing enhanced transparency, security, and efficiency throughout the entire process.
                            </p>
                            <div className="flex flex-wrap justify-center gap-4">
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-teal-50 dark:bg-teal-900/30 border-teal-200 dark:border-teal-800">
                                    <Shield className="w-3.5 h-3.5 mr-1" />
                                    Blockchain Secured
                                </Badge>
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800">
                                    <Database className="w-3.5 h-3.5 mr-1" />
                                    MultiChain Integration
                                </Badge>
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-purple-50 dark:bg-purple-900/30 border-purple-200 dark:border-purple-800">
                                    <User className="w-3.5 h-3.5 mr-1" />
                                    Role-Based Access
                                </Badge>
                            </div>
                        </div>

                        {/* Overview Section with Image */}
                        <div className="mb-16">
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
                                <div className="flex flex-col md:flex-row">
                                    <div className="md:w-1/2 p-8">
                                        <h2 className="text-3xl font-bold mb-4 bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                            Feature Overview
                                        </h2>
                                        <p className="text-gray-600 dark:text-gray-300 mb-4">
                                            ProcuChain combines blockchain technology with user-friendly interfaces to create a
                                            transparent, secure, and efficient procurement management system that meets the needs
                                            of government agencies and other stakeholders.
                                        </p>
                                        <div className="space-y-3 mb-6">
                                            <div className="flex items-center text-gray-700 dark:text-gray-300">
                                                <CheckCircle className="w-4 h-4 mr-2 text-teal-500" />
                                                <span>Transparent Process</span>
                                            </div>
                                            <div className="flex items-center text-gray-700 dark:text-gray-300">
                                                <CheckCircle className="w-4 h-4 mr-2 text-teal-500" />
                                                <span>Regulatory Compliance</span>
                                            </div>
                                            <div className="flex items-center text-gray-700 dark:text-gray-300">
                                                <CheckCircle className="w-4 h-4 mr-2 text-teal-500" />
                                                <span>Document Security</span>
                                            </div>
                                        </div>
                                        <Button asChild variant="default" className="bg-teal-600 hover:bg-teal-700 text-white">
                                            <a href="#key-features">
                                                Explore Features
                                            </a>
                                        </Button>
                                    </div>
                                    <div className="md:w-1/2 bg-gradient-to-br from-teal-400/10 to-blue-400/10 dark:from-teal-900/20 dark:to-blue-900/20 p-6 flex items-center justify-center">
                                        <div className="relative w-full h-full max-w-md flex items-center justify-center">
                                            <div className="relative">
                                                <div className="absolute inset-0 bg-teal-500/10 dark:bg-teal-500/5 rounded-full animate-pulse"></div>
                                                <div className="relative flex items-center justify-center p-6 rounded-full bg-white dark:bg-gray-800 shadow-md">
                                                    <BarChart2 className="w-16 h-16 text-teal-600 dark:text-teal-400" />
                                                </div>
                                                <div className="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/3">
                                                    <div className="bg-blue-100 dark:bg-blue-900/30 p-3 rounded-full shadow-sm">
                                                        <FileText className="w-6 h-6 text-blue-600 dark:text-blue-400" />
                                                    </div>
                                                </div>
                                                <div className="absolute bottom-0 left-0 transform -translate-x-1/3 translate-y-1/4">
                                                    <div className="bg-green-100 dark:bg-green-900/30 p-3 rounded-full shadow-sm">
                                                        <Lock className="w-6 h-6 text-green-600 dark:text-green-400" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Key Features Section */}
                        <div className="mb-16" id="key-features">
                            <h2 className="text-3xl font-bold mb-8 text-center">Key System Features</h2>

                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-10">
                                {systemFeatures.map((feature) => (
                                    <Card
                                        key={feature.id}
                                        className={`hover:shadow-lg transition-shadow cursor-pointer ${activeFeature === feature.id ? 'ring-2 ring-teal-500 dark:ring-teal-400' : ''
                                            }`}
                                        onClick={() => setActiveFeature(feature.id)}
                                    >
                                        <CardContent className="pt-6">
                                            <div className="mb-4 bg-teal-50 dark:bg-teal-900/30 p-3 rounded-full w-16 h-16 flex items-center justify-center">
                                                {feature.icon}
                                            </div>
                                            <h3 className="text-xl font-bold mb-2">{feature.title}</h3>
                                            <p className="text-gray-600 dark:text-gray-300 text-sm">{feature.description}</p>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>

                            {/* Feature Details Section */}
                            <Card className="mb-10">
                                <CardHeader>
                                    <CardTitle className="text-2xl">
                                        {systemFeatures.find(f => f.id === activeFeature)?.title}
                                    </CardTitle>
                                    <CardDescription>
                                        {systemFeatures.find(f => f.id === activeFeature)?.description}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <div>
                                            <h4 className="font-semibold mb-4">Key Benefits</h4>
                                            <ul className="space-y-2">
                                                {systemFeatures.find(f => f.id === activeFeature)?.benefits.map((benefit, idx) => (
                                                    <li key={idx} className="flex items-start">
                                                        <CheckCircle className="w-5 h-5 text-teal-500 mr-3 flex-shrink-0 mt-0.5" />
                                                        <span className="text-gray-700 dark:text-gray-300">{benefit}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                        <div className="bg-gray-50 dark:bg-gray-800 rounded-lg overflow-hidden">
                                            <img
                                                src={systemFeatures.find(f => f.id === activeFeature)?.imageUrl}
                                                alt={systemFeatures.find(f => f.id === activeFeature)?.title}
                                                className="w-full h-full object-cover"
                                                onError={(e) => {
                                                    e.currentTarget.src = systemFeatures.find(f => f.id === activeFeature)?.fallbackImage || "";
                                                }}
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Procurement Workflow Visualization */}
                        <Card className="mb-16">
                            <CardHeader>
                                <CardTitle className="text-2xl md:text-3xl font-bold text-center">Procurement Process Flow</CardTitle>
                                <CardDescription className="text-center max-w-3xl mx-auto">
                                    ProcuChain manages the entire procurement lifecycle from initiation to completion,
                                    ensuring transparency and compliance at every stage.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {/* Desktop Timeline */}
                                <div className="hidden md:block">
                                    <div className="relative">
                                        <div className="absolute top-5 left-0 w-full h-1 bg-gray-200 dark:bg-gray-700"></div>

                                        <div className="flex justify-between relative">
                                            {procurementStages.map((stage, index) => (
                                                <div key={stage.id} className={`flex flex-col items-center relative w-8 ${index === 0 ? 'ml-0' : ''} ${index === procurementStages.length - 1 ? 'mr-0' : ''}`}>
                                                    <Button
                                                        variant={activeStage === stage.id ? "default" : "outline"}
                                                        size="icon"
                                                        className={`rounded-full z-10 transition-all duration-300 ${activeStage === stage.id
                                                            ? 'bg-teal-600 hover:bg-teal-700 text-white scale-110'
                                                            : 'bg-white dark:bg-gray-800 hover:border-teal-400 dark:hover:border-teal-400'}`}
                                                        onClick={() => setActiveStage(stage.id)}
                                                    >
                                                        {stage.id}
                                                    </Button>
                                                    <div className="absolute -bottom-7 whitespace-nowrap text-xs font-medium text-gray-500 dark:text-gray-400 transform -translate-x-1/2 left-1/2">
                                                        {getStageShortName(stage.stage)}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="mt-16 pt-4 border-t border-gray-200 dark:border-gray-700">
                                        {activeStage ? (
                                            <div className="animate-fadeIn">
                                                <div className="flex items-start">
                                                    <div className="flex-shrink-0 mr-4">
                                                        <div className="w-12 h-12 bg-teal-100 dark:bg-teal-900/30 rounded-full flex items-center justify-center text-teal-600 dark:text-teal-400">
                                                            {procurementStages.find(p => p.id === activeStage)?.icon}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
                                                            Stage {activeStage}: {procurementStages.find(p => p.id === activeStage)?.stage}
                                                        </h3>
                                                        <p className="text-gray-600 dark:text-gray-300 mb-4">
                                                            {procurementStages.find(p => p.id === activeStage)?.description}
                                                        </p>
                                                        <div>
                                                            <h4 className="font-semibold text-gray-800 dark:text-gray-200 mb-2">Required Documents:</h4>
                                                            <ul className="list-disc pl-5 text-gray-600 dark:text-gray-300">
                                                                {procurementStages.find(p => p.id === activeStage)?.documents.map((doc, index) => (
                                                                    <li key={index}>{doc}</li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="text-center py-6 text-gray-500 dark:text-gray-400">
                                                <p>Select a stage above to view details</p>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Mobile Accordion */}
                                <div className="md:hidden">
                                    <Accordion type="single" collapsible className="w-full">
                                        {procurementStages.map((stage) => (
                                            <AccordionItem key={stage.id} value={`stage-${stage.id}`}>
                                                <AccordionTrigger className="hover:no-underline">
                                                    <div className="flex items-center">
                                                        <div className={`w-8 h-8 rounded-full mr-3 flex items-center justify-center
                                                            ${stage.id === activeStage
                                                                ? 'bg-teal-600 text-white'
                                                                : 'bg-teal-100 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400'}`}
                                                        >
                                                            {stage.id}
                                                        </div>
                                                        <span className="font-medium text-left">{stage.stage}</span> {/* Added text-left */}
                                                    </div>
                                                </AccordionTrigger>
                                                <AccordionContent>
                                                    <p className="text-gray-600 dark:text-gray-300 mb-4">
                                                        {stage.description}
                                                    </p>
                                                    <div>
                                                        <h4 className="font-semibold text-gray-800 dark:text-gray-200 mb-2">Required Documents:</h4>
                                                        <ul className="list-disc pl-5 text-gray-600 dark:text-gray-300">
                                                            {stage.documents.map((doc, index) => (
                                                                <li key={index}>{doc}</li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                </AccordionContent>
                                            </AccordionItem>
                                        ))}
                                    </Accordion>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Feature Comparison */}
                        <div className="mb-16">
                            <h2 className="text-3xl font-bold mb-8 text-center">
                                Comparison with Traditional Systems
                            </h2>
                            <p className="text-gray-600 dark:text-gray-300 max-w-3xl mx-auto mb-10 text-center">
                                See how ProcuChain's blockchain-powered approach compares to traditional procurement systems
                            </p>

                            <div className="overflow-hidden">
                                <div className="overflow-x-auto">
                                    <Table className="w-full">
                                        <TableHeader>
                                            <TableRow className="bg-gray-50 dark:bg-gray-800/50">
                                                <TableHead className="font-semibold">Feature</TableHead>
                                                <TableHead className="font-semibold">Traditional Systems</TableHead>
                                                <TableHead className="font-semibold">ProcuChain</TableHead>
                                                <TableHead className="font-semibold">Advantage</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {comparisonFeatures.map((item, index) => (
                                                <TableRow key={index} className={index % 2 === 0 ? "bg-white dark:bg-gray-900" : "bg-gray-50/50 dark:bg-gray-800/30"}>
                                                    <TableCell className="font-medium">{item.feature}</TableCell>
                                                    <TableCell className="text-gray-600 dark:text-gray-300">{item.traditional}</TableCell>
                                                    <TableCell className="text-teal-600 dark:text-teal-400 font-medium">{item.procuchain}</TableCell>
                                                    <TableCell>{item.advantage}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        </div>

                        {/* Technical Specifications */}
                        <div className="mb-16">
                            <h2 className="text-3xl font-bold mb-8 text-center">
                                Technical Specifications
                            </h2>

                            <Tabs defaultValue="blockchain" className="w-full">
                                <TabsList className="grid w-full grid-cols-3 mb-8">
                                    <TabsTrigger value="blockchain">Blockchain Integration</TabsTrigger>
                                    <TabsTrigger value="security">Security Features</TabsTrigger>
                                    <TabsTrigger value="scalability">Scalability & Performance</TabsTrigger>
                                </TabsList>

                                <TabsContent value="blockchain">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>MultiChain Integration</CardTitle>
                                            <CardDescription>
                                                ProcuChain leverages the MultiChain blockchain platform to provide secure, permissioned document verification.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                                <div className="space-y-4">
                                                    <h3 className="font-semibold text-lg">Key Technical Details</h3>
                                                    <ul className="space-y-2">
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <div>
                                                                <span className="font-medium">SHA-256 Document Hashing</span>
                                                                <p className="text-sm text-gray-600 dark:text-gray-300">Each document is hashed using the SHA-256 algorithm before being stored on-chain</p>
                                                            </div>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <div>
                                                                <span className="font-medium">Permissioned Blockchain</span>
                                                                <p className="text-sm text-gray-600 dark:text-gray-300">MultiChain provides a permissioned environment where only authorized nodes can participate</p>
                                                            </div>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <div>
                                                                <span className="font-medium">Proof of Authority Consensus</span>
                                                                <p className="text-sm text-gray-600 dark:text-gray-300">Energy-efficient consensus mechanism ideal for government applications</p>
                                                            </div>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <div>
                                                                <span className="font-medium">Stream-based Data Storage</span>
                                                                <p className="text-sm text-gray-600 dark:text-gray-300">Organizes procurement data in blockchain streams for efficient retrieval</p>
                                                            </div>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div className="bg-gray-50 dark:bg-gray-800 p-6 rounded-lg">
                                                    <h3 className="font-semibold text-lg mb-4">Document Verification Process</h3>
                                                    <ol className="space-y-4 list-decimal pl-5">
                                                        <li className="text-gray-600 dark:text-gray-300">Document is uploaded to the system</li>
                                                        <li className="text-gray-600 dark:text-gray-300">System generates SHA-256 hash of the document</li>
                                                        <li className="text-gray-600 dark:text-gray-300">Hash is stored in the appropriate blockchain stream</li>
                                                        <li className="text-gray-600 dark:text-gray-300">Document metadata is recorded with the hash</li>
                                                        <li className="text-gray-600 dark:text-gray-300">System provides verification certificate</li>
                                                        <li className="text-gray-600 dark:text-gray-300">Future verification compares new hash against blockchain record</li>
                                                    </ol>
                                                </div>
                                            </div>
                                            <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                                <Button asChild variant="outline">
                                                    <a href={route('documentation')} className="flex items-center">
                                                        <FileText className="mr-2 h-4 w-4" />
                                                        View Technical Documentation
                                                        <ExternalLink className="ml-2 h-4 w-4" />
                                                    </a>
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>

                                <TabsContent value="security">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Security Features</CardTitle>
                                            <CardDescription>
                                                ProcuChain implements multiple layers of security to protect procurement data and processes.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                                <div className="bg-gray-50 dark:bg-gray-800/50 p-6 rounded-lg">
                                                    <Shield className="w-8 h-8 text-teal-500 mb-3" />
                                                    <h3 className="font-semibold text-lg mb-2">Data Protection</h3>
                                                    <ul className="space-y-2 text-gray-600 dark:text-gray-300 text-sm">
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>End-to-end encryption</span>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Secure data storage</span>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Encrypted document transfer</span>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Data anonymization when needed</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div className="bg-gray-50 dark:bg-gray-800/50 p-6 rounded-lg">
                                                    <User className="w-8 h-8 text-teal-500 mb-3" />
                                                    <h3 className="font-semibold text-lg mb-2">Access Security</h3>
                                                    <ul className="space-y-2 text-gray-600 dark:text-gray-300 text-sm">
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Multi-factor authentication</span>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Role-based access control</span>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Session timeout controls</span>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Activity logging and monitoring</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div className="bg-gray-50 dark:bg-gray-800/50 p-6 rounded-lg">
                                                    <Zap className="w-8 h-8 text-teal-500 mb-3" />
                                                    <h3 className="font-semibold text-lg mb-2">System Security</h3>
                                                    <ul className="space-y-2 text-gray-600 dark:text-gray-300 text-sm">
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Regular security audits</span>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Vulnerability scanning</span>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Intrusion detection systems</span>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2 mt-0.5 flex-shrink-0" />
                                                            <span>Automated security patching</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>

                                <TabsContent value="scalability">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Scalability & Performance</CardTitle>
                                            <CardDescription>
                                                ProcuChain is designed to handle growing procurement needs while maintaining optimal performance.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-6">
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div>
                                                        <h3 className="font-semibold text-lg mb-3">Performance Metrics</h3>
                                                        <Table>
                                                            <TableHeader>
                                                                <TableRow>
                                                                    <TableHead>Metric</TableHead>
                                                                    <TableHead>Value</TableHead>
                                                                </TableRow>
                                                            </TableHeader>
                                                            <TableBody>
                                                                <TableRow>
                                                                    <TableCell>Document Verification Time</TableCell>
                                                                    <TableCell>&lt; 2 seconds</TableCell>
                                                                </TableRow>
                                                                <TableRow>
                                                                    <TableCell>Maximum Document Size</TableCell>
                                                                    <TableCell>100 MB</TableCell>
                                                                </TableRow>
                                                                <TableRow>
                                                                    <TableCell>Transaction Throughput</TableCell>
                                                                    <TableCell>200 tx/min</TableCell>
                                                                </TableRow>
                                                                <TableRow>
                                                                    <TableCell>Concurrent Users</TableCell>
                                                                    <TableCell>1000+</TableCell>
                                                                </TableRow>
                                                                <TableRow>
                                                                    <TableCell>System Availability</TableCell>
                                                                    <TableCell>99.95%</TableCell>
                                                                </TableRow>
                                                            </TableBody>
                                                        </Table>
                                                    </div>
                                                    <div>
                                                        <h3 className="font-semibold text-lg mb-3">Scalability Features</h3>
                                                        <ul className="space-y-3">
                                                            <li className="flex items-start">
                                                                <CheckCircle className="w-5 h-5 text-teal-500 mr-3 flex-shrink-0 mt-0.5" />
                                                                <div>
                                                                    <span className="font-medium">Horizontal Scaling</span>
                                                                    <p className="text-sm text-gray-600 dark:text-gray-300">
                                                                        Application servers can be scaled horizontally to handle increased load
                                                                    </p>
                                                                </div>
                                                            </li>
                                                            <li className="flex items-start">
                                                                <CheckCircle className="w-5 h-5 text-teal-500 mr-3 flex-shrink-0 mt-0.5" />
                                                                <div>
                                                                    <span className="font-medium">Database Partitioning</span>
                                                                    <p className="text-sm text-gray-600 dark:text-gray-300">
                                                                        Data is partitioned for efficient storage and retrieval
                                                                    </p>
                                                                </div>
                                                            </li>
                                                            <li className="flex items-start">
                                                                <CheckCircle className="w-5 h-5 text-teal-500 mr-3 flex-shrink-0 mt-0.5" />
                                                                <div>
                                                                    <span className="font-medium">Caching Layer</span>
                                                                    <p className="text-sm text-gray-600 dark:text-gray-300">
                                                                        Redis cache for frequently accessed data and session management
                                                                    </p>
                                                                </div>
                                                            </li>
                                                            <li className="flex items-start">
                                                                <CheckCircle className="w-5 h-5 text-teal-500 mr-3 flex-shrink-0 mt-0.5" />
                                                                <div>
                                                                    <span className="font-medium">Background Processing</span>
                                                                    <p className="text-sm text-gray-600 dark:text-gray-300">
                                                                        Queue-based processing for resource-intensive operations
                                                                    </p>
                                                                </div>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                            </Tabs>
                        </div>

                        {/* Call to Action */}
                        <div className="bg-gradient-to-r from-teal-600 to-teal-500 rounded-xl text-white p-8 md:p-12 text-center">
                            <h2 className="text-3xl font-bold mb-4">Ready to Experience ProcuChain?</h2>
                            <p className="text-xl opacity-90 max-w-2xl mx-auto mb-8">
                                Discover how our blockchain-powered procurement system can transform your organization's procurement processes
                            </p>
                            <div className="flex flex-wrap justify-center gap-4">
                                <Button asChild size="lg" className="bg-white text-teal-600 hover:bg-gray-100">
                                    <a href={route('documentation')}>
                                        <FileText className="mr-2 h-5 w-5" />
                                        View Documentation
                                    </a>
                                </Button>
                                <Button asChild size="lg" variant="outline" className="border-white text-white hover:bg-teal-700">
                                    <a href={route('contact')}>
                                        Contact Us
                                        <ExternalLink className="ml-2 h-5 w-5" />
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}