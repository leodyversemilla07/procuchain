import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { BarChart2, CheckCircle, ChevronRight, FileText, Lock, Search, Activity } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";

interface ProcurementPhase {
    id: number;
    phase: string;
    description: string;
    documents: string[];
    icon: React.ReactNode;
}

export default function Procurement() {
    const [isMobile, setIsMobile] = useState(false);
    const [activePhase, setActivePhase] = useState<number | null>(null);

    // Helper function to get short names for phases
    const getPhaseShortName = (phaseName: string): string => {
        if (phaseName.includes("Purchase Request")) return "PR";
        if (phaseName.includes("Pre-Procurement")) return "Pre-Proc";
        if (phaseName.includes("Bid Invitation")) return "Invitation";
        if (phaseName.includes("Bid Submission")) return "Submission";
        if (phaseName.includes("Bid Evaluation")) return "Evaluation";
        if (phaseName.includes("Post-Qualification")) return "Post-Qual";
        if (phaseName.includes("BAC Resolution")) return "BAC";
        if (phaseName.includes("Notice of Award")) return "NOA";
        if (phaseName.includes("Performance Bond")) return "Bond";
        if (phaseName.includes("Contract")) return "Contract/PO";
        if (phaseName.includes("Notice to Proceed")) return "NTP";
        if (phaseName.includes("Monitoring")) return "Monitoring";
        if (phaseName.includes("Completion")) return "Completion";
        return phaseName.split(" ")[0];
    };

    const procurementPhases: ProcurementPhase[] = [
        {
            id: 1,
            phase: "Purchase Request (PR) Initiation",
            description: "Record finalized PR and supporting documents with general and phase-specific metadata.",
            documents: ["Purchase Request", "Certificate of Availability of Funds", "Annual Investment Plan"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 2,
            phase: "Pre-Procurement Conference Decision",
            description: "Record optional finalized conference documents or decision to skip.",
            documents: ["Conference Minutes", "Attendance Sheet"],
            icon: <Activity className="w-6 h-6" />
        },
        {
            id: 3,
            phase: "Bid Invitation Publication",
            description: "Record and publish finalized bid invitation document(s).",
            documents: ["Bid Invitation"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 4,
            phase: "Bid Submission and Opening",
            description: "Record multiple finalized bid documents post-opening.",
            documents: ["Bid Documents", "Opening Minutes"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 5,
            phase: "Bid Evaluation",
            description: "Record finalized evaluation reports.",
            documents: ["Evaluation Summary", "Abstract"],
            icon: <Activity className="w-6 h-6" />
        },
        {
            id: 6,
            phase: "Post-Qualification",
            description: "Record finalized post-qualification documents and outcome.",
            documents: ["Tax Return", "Financial Statement", "Verification Report"],
            icon: <CheckCircle className="w-6 h-6" />
        },
        {
            id: 7,
            phase: "BAC Resolution",
            description: "Record finalized BAC resolution document(s).",
            documents: ["BAC Resolution"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 8,
            phase: "Notice of Award (NOA)",
            description: "Record and publish finalized NOA document(s).",
            documents: ["Notice of Award"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 9,
            phase: "Performance Bond",
            description: "Record finalized performance bond document(s).",
            documents: ["Performance Bond"],
            icon: <Lock className="w-6 h-6" />
        },
        {
            id: 10,
            phase: "Contract and Purchase Order (PO)",
            description: "Record finalized contract and PO documents.",
            documents: ["Contract", "Purchase Order"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 11,
            phase: "Notice to Proceed (NTP)",
            description: "Record and publish finalized NTP document(s).",
            documents: ["Notice to Proceed"],
            icon: <FileText className="w-6 h-6" />
        },
        {
            id: 12,
            phase: "Monitoring and Compliance",
            description: "Record optional finalized compliance documents and enable monitoring.",
            documents: ["Compliance Report"],
            icon: <Activity className="w-6 h-6" />
        }
        ,
        {
            id: 13,
            phase: "Completion and Closure",
            description: "Record project completion documents and close the procurement process.",
            documents: ["Completion Certificate", "Final Payment", "Inspection Report"],
            icon: <CheckCircle className="w-6 h-6" />
        }
    ];

    useEffect(() => {
        const checkMobile = () => {
            setIsMobile(window.innerWidth < 768);
        };

        // Check on initial load
        checkMobile();

        // Add listener for window resize
        window.addEventListener('resize', checkMobile);

        // Clean up
        return () => window.removeEventListener('resize', checkMobile);
    }, []);

    return (
        <>
            <Head title="Procurement System">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|inter:400,500,600&display=swap" rel="stylesheet" />
            </Head>
            <div className={`min-h-screen flex flex-col overflow-x-hidden bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative`}>
                <Header />

                <main className="flex-grow pt-24 pb-12">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {/* Hero Section - Improved */}
                        <div className="mb-16 bg-white dark:bg-gray-800/50 rounded-xl shadow-sm overflow-hidden">
                            <div className="flex flex-col md:flex-row">
                                {/* Content Side */}
                                <div className="p-8 md:p-10 md:w-3/5">
                                    <div className="inline-block p-2 bg-teal-100/60 dark:bg-teal-900/30 rounded-lg text-teal-700 dark:text-teal-300 mb-4">
                                        <Activity className="w-5 h-5" />
                                    </div>
                                    <h1 className="text-4xl md:text-5xl font-bold mb-4 leading-tight">
                                        <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                            Procurement Workflow
                                        </span>
                                    </h1>
                                    <p className="text-lg text-gray-600 dark:text-gray-300 mb-6 max-w-2xl">
                                        Explore the complete procurement process from request initiation to monitoring, 
                                        ensuring transparency and compliance at every step of your procurement journey.
                                    </p>
                                    <div className="flex flex-wrap gap-4 mb-6">
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
                                            <span>Document Management</span>
                                        </div>
                                    </div>
                                </div>
                                
                                {/* Visual Side */}
                                <div className="md:w-2/5 bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/20 dark:to-blue-900/20 p-8 flex items-center justify-center">
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

                        {/* Procurement Workflow Visualization */}
                        <Card className="mb-16">
                            <CardHeader>
                                <CardTitle className="text-2xl md:text-3xl font-bold text-center">Procurement Process Flow</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {/* Desktop Timeline */}
                                <div className="hidden md:block">
                                    <div className="relative">
                                        {/* Timeline line */}
                                        <div className="absolute top-5 left-0 w-full h-1 bg-gray-200 dark:bg-gray-700"></div>

                                        {/* Timeline points */}
                                        <div className="flex justify-between relative">
                                            {procurementPhases.map((phase, index) => (
                                                <div key={phase.id} className={`flex flex-col items-center relative w-8 ${index === 0 ? 'ml-0' : ''} ${index === procurementPhases.length - 1 ? 'mr-0' : ''}`}>
                                                    <Button
                                                        variant={activePhase === phase.id ? "default" : "outline"}
                                                        size="icon"
                                                        className={`rounded-full z-10 transition-all duration-300 ${activePhase === phase.id ? 'scale-110' : ''}`}
                                                        onClick={() => setActivePhase(phase.id)}
                                                    >
                                                        {phase.id}
                                                    </Button>
                                                    <div className="absolute -bottom-7 whitespace-nowrap text-xs font-medium text-gray-500 dark:text-gray-400 transform -translate-x-1/2 left-1/2">
                                                        {getPhaseShortName(phase.phase)}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* Phase details */}
                                    <div className="mt-16 pt-4 border-t border-gray-200 dark:border-gray-700">
                                        {activePhase ? (
                                            <div className="animate-fadeIn">
                                                <div className="flex items-start">
                                                    <div className="flex-shrink-0 mr-4">
                                                        <div className="w-12 h-12 bg-teal-100 dark:bg-teal-900/30 rounded-full flex items-center justify-center text-teal-600 dark:text-teal-400">
                                                            {procurementPhases.find(p => p.id === activePhase)?.icon}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
                                                            Phase {activePhase}: {procurementPhases.find(p => p.id === activePhase)?.phase}
                                                        </h3>
                                                        <p className="text-gray-600 dark:text-gray-300 mb-4">
                                                            {procurementPhases.find(p => p.id === activePhase)?.description}
                                                        </p>
                                                        <div>
                                                            <h4 className="font-semibold text-gray-800 dark:text-gray-200 mb-2">Required Documents:</h4>
                                                            <Table>
                                                                <TableHeader>
                                                                    <TableRow>
                                                                        <TableHead className="text-xs">Document Type</TableHead>
                                                                    </TableRow>
                                                                </TableHeader>
                                                                <TableBody>
                                                                    {procurementPhases.find(p => p.id === activePhase)?.documents.map((doc, index) => (
                                                                        <TableRow key={index}>
                                                                            <TableCell className="text-gray-600 dark:text-gray-300">{doc}</TableCell>
                                                                        </TableRow>
                                                                    ))}
                                                                </TableBody>
                                                            </Table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="text-center py-6 text-gray-500 dark:text-gray-400">
                                                <p>Select a phase above to view details</p>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Mobile Accordion */}
                                <div className="md:hidden">
                                    <Accordion type="single" collapsible className="w-full">
                                        {procurementPhases.map((phase) => (
                                            <AccordionItem key={phase.id} value={`phase-${phase.id}`}>
                                                <AccordionTrigger className="hover:no-underline">
                                                    <div className="flex items-center">
                                                        <div className={`w-8 h-8 rounded-full mr-3 flex items-center justify-center bg-teal-100 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400`}>
                                                            {phase.id}
                                                        </div>
                                                        <span className="font-medium">{phase.phase}</span>
                                                    </div>
                                                </AccordionTrigger>
                                                <AccordionContent>
                                                    <p className="text-gray-600 dark:text-gray-300 mb-4">
                                                        {phase.description}
                                                    </p>
                                                    <div>
                                                        <h4 className="font-semibold text-gray-800 dark:text-gray-200 mb-2">Required Documents:</h4>
                                                        <ul className="list-disc pl-5 text-gray-600 dark:text-gray-300">
                                                            {phase.documents.map((doc, index) => (
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
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
