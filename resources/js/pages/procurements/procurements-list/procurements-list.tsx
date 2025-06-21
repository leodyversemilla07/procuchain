import { useState } from 'react';
import { Head, usePage, Link } from '@inertiajs/react';
import { FileText, Activity, Clock, Archive, RefreshCw, Plus, Search } from 'lucide-react';

import { ProcurementListItem, Status } from '@/types/blockchain';
import { SharedData } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { ErrorState } from '@/components/procurements-list/error-state';
import { PreBidConferenceModal } from '@/components/pre-bid-conference/pre-bid-conference-modal';
import { PreProcurementModal } from '@/components/pre-procurement-conference/pre-procurement-conference-modal';
import { SupplementalBidBulletinModal } from '@/components/supplemental-bid-bulletin/supplemental-bid-bulletin-modal';
import { useProcurementList } from '@/hooks/use-procurement-list';
import { getBreadcrumbs } from '@/lib/procurements-list-utils';
import { createColumns } from './columns';
import { ProcurementsDataTable } from './data-table';

interface ShowProps {
    procurements: ProcurementListItem[];
    error?: string;
}

export default function ProcurementsList({ procurements: initialProcurements, error: initialError }: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role || "guest";
    const breadcrumbs = getBreadcrumbs(userRole);

    // State management
    const [searchValue, setSearchValue] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [stageFilter, setStageFilter] = useState('all');    // Custom hook for procurement list logic
    const {
        procurements,
        loading,
        error,
        preProcurementModalOpen,
        preBidConferenceModalOpen,
        supplementalBidBulletinModalOpen,
        selectedProcurement,
        setSelectedRows,
        setPreProcurementModalOpen,
        setPreBidConferenceModalOpen,
        setSupplementalBidBulletinModalOpen,
        handleOpenPreProcurementModal,
        handleOpenPreBidModal,
        handleOpenSupplementalBidBulletinModal,
    } = useProcurementList({ initialProcurements, initialError });

    // Helper functions for categorizing procurements
    const getCompletedCount = () => {
        return procurements.filter(p => 
            p.current_status === Status.COMPLETED || 
            p.current_status === Status.COMPLETION_DOCUMENTS_UPLOADED
        ).length;
    };

    const getInProgressCount = () => {
        return procurements.filter(p => {
            const status = p.current_status;
            return status !== Status.COMPLETED && 
                   status !== Status.COMPLETION_DOCUMENTS_UPLOADED &&
                   status !== Status.PROCUREMENT_SUBMITTED;
        }).length;
    };

    const getTotalDocuments = () => {
        return procurements.reduce((sum, p) => {
            const count = Number(p.document_count) || 0;
            return sum + count;
        }, 0);
    };

    // Create columns with handlers
    const columns = createColumns({
        onOpenPreProcurementModal: handleOpenPreProcurementModal,
        onOpenPreBidModal: handleOpenPreBidModal,
        onOpenSupplementalBidBulletinModal: handleOpenSupplementalBidBulletinModal,
    });

    // Filter procurements based on search and filters
    const filteredProcurements = procurements.filter(proc => {
        if (!searchValue.trim() && statusFilter === 'all' && stageFilter === 'all') return true;
        
        const searchLower = searchValue.toLowerCase();
        const matchesSearch = !searchValue.trim() || (
            proc.id.toLowerCase().includes(searchLower) ||
            proc.title.toLowerCase().includes(searchLower) ||
            proc.stage.toLowerCase().includes(searchLower) ||
            proc.current_status.toLowerCase().includes(searchLower)
        );
        
        const matchesStatus = statusFilter === 'all' || proc.current_status === statusFilter;
        const matchesStage = stageFilter === 'all' || proc.stage === stageFilter;
        
        return matchesSearch && matchesStatus && matchesStage;
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement List" />
            <div className="w-full space-y-4 p-4 md:p-6 lg:p-8">
                {/* Header Section */}
                <div className="border-b pb-6">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 className="text-xl md:text-2xl lg:text-3xl font-bold tracking-tight flex items-center">
                                <FileText className="h-5 w-5 md:h-6 md:w-6 lg:h-8 lg:w-8 mr-2 md:mr-3 text-primary" />
                                Procurement List
                            </h1>
                            <p className="text-muted-foreground mt-1 md:mt-2 text-xs md:text-sm lg:text-base">
                                View and manage procurement items across all stages
                            </p>
                        </div>
                        <div className="flex items-center gap-2 md:gap-3">
                            {userRole === 'bac_secretariat' && (
                                <Button asChild>
                                    <Link href="/bac-secretariat/procurement/procurement-initiation" className="flex items-center space-x-2">
                                        <Plus className="h-4 w-4" />
                                        <span>New Procurement</span>
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </div>                  
                
                {/* Error Display */}
                {error && (
                    <Card className="border-destructive/50 bg-destructive/10 dark:border-destructive/20 dark:bg-destructive/5">
                        <CardContent className="p-4">
                            <ErrorState error={error} />
                        </CardContent>
                    </Card>
                )}

                {/* Statistics Cards */}
                <div className="grid gap-3 md:gap-4 grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs md:text-sm font-medium">Total</CardTitle>
                            <Archive className="h-3 w-3 md:h-4 md:w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg md:text-2xl font-bold">{procurements.length}</div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs md:text-sm font-medium">In Progress</CardTitle>
                            <Activity className="h-3 w-3 md:h-4 md:w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg md:text-2xl font-bold">{getInProgressCount()}</div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs md:text-sm font-medium">Completed</CardTitle>
                            <Clock className="h-3 w-3 md:h-4 md:w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg md:text-2xl font-bold">{getCompletedCount()}</div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs md:text-sm font-medium">Documents</CardTitle>
                            <FileText className="h-3 w-3 md:h-4 md:w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg md:text-2xl font-bold">{getTotalDocuments()}</div>
                        </CardContent>
                    </Card>
                </div>                {/* Procurements Table */}
                <Card>
                    <CardHeader className="pb-4">
                        <div className="space-y-4">
                            <div className="space-y-1">
                                <CardTitle className="flex items-center space-x-2 text-base md:text-lg">
                                    <FileText className="h-4 w-4 md:h-5 md:w-5" />
                                    <span>Procurement Records</span>
                                </CardTitle>
                                <CardDescription className="text-xs md:text-sm">
                                    All procurement records with their current status and stage information
                                </CardDescription>
                            </div>
                            
                            {/* Search and Filter Controls */}
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                                    {/* Search Input */}
                                    <div className="relative flex-1 max-w-md">
                                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            type="text"
                                            placeholder="Search procurements..."
                                            value={searchValue}
                                            onChange={(e) => setSearchValue(e.target.value)}
                                            className="pl-10 h-10"
                                        />
                                    </div>
                                    
                                    {/* Filters */}
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                        <Select value={statusFilter} onValueChange={setStatusFilter}>
                                            <SelectTrigger className="w-full sm:w-[180px] h-10">
                                                <SelectValue placeholder="All Status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All Status</SelectItem>
                                                <SelectItem value="PROCUREMENT_SUBMITTED">Submitted</SelectItem>
                                                <SelectItem value="PRE_PROCUREMENT_SCHEDULED">Pre-Procurement</SelectItem>
                                                <SelectItem value="BIDDING_DOCUMENTS_PREPARED">Bidding Docs</SelectItem>
                                                <SelectItem value="PRE_BID_CONFERENCE_SCHEDULED">Pre-Bid Conference</SelectItem>
                                                <SelectItem value="BID_SUBMISSION_ONGOING">Bid Submission</SelectItem>
                                                <SelectItem value="BID_OPENING_SCHEDULED">Bid Opening</SelectItem>
                                                <SelectItem value="BID_EVALUATION_ONGOING">Bid Evaluation</SelectItem>
                                                <SelectItem value="POST_QUALIFICATION_ONGOING">Post Qualification</SelectItem>
                                                <SelectItem value="NOTICE_OF_AWARD_ISSUED">Notice of Award</SelectItem>
                                                <SelectItem value="NOTICE_TO_PROCEED_ISSUED">Notice to Proceed</SelectItem>
                                                <SelectItem value="PERFORMANCE_BOND_RECEIVED">Performance Bond</SelectItem>
                                                <SelectItem value="MONITORING_ONGOING">Monitoring</SelectItem>
                                                <SelectItem value="COMPLETED">Completed</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        
                                        <Select value={stageFilter} onValueChange={setStageFilter}>
                                            <SelectTrigger className="w-full sm:w-[180px] h-10">
                                                <SelectValue placeholder="All Stages" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All Stages</SelectItem>
                                                <SelectItem value="PROCUREMENT_INITIATION">Initiation</SelectItem>
                                                <SelectItem value="PRE_PROCUREMENT_CONFERENCE">Pre-Procurement</SelectItem>
                                                <SelectItem value="BIDDING_DOCUMENTS">Bidding Documents</SelectItem>
                                                <SelectItem value="PRE_BID_CONFERENCE">Pre-Bid Conference</SelectItem>
                                                <SelectItem value="BID_SUBMISSION">Bid Submission</SelectItem>
                                                <SelectItem value="BID_OPENING">Bid Opening</SelectItem>
                                                <SelectItem value="BID_EVALUATION">Bid Evaluation</SelectItem>
                                                <SelectItem value="POST_QUALIFICATION">Post Qualification</SelectItem>
                                                <SelectItem value="NOTICE_OF_AWARD">Notice of Award</SelectItem>
                                                <SelectItem value="NOTICE_TO_PROCEED">Notice to Proceed</SelectItem>
                                                <SelectItem value="PERFORMANCE_BOND_CONTRACT_AND_PO">Performance Bond</SelectItem>
                                                <SelectItem value="MONITORING">Monitoring</SelectItem>
                                                <SelectItem value="COMPLETION">Completion</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                
                                {/* Refresh Button */}
                                <div className="flex justify-center sm:justify-end">
                                    <Button
                                        onClick={() => window.location.reload()}
                                        disabled={loading}
                                        variant="outline"
                                        size="default"
                                        className="flex items-center space-x-2 w-full sm:w-auto h-10"
                                    >
                                        <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                        <span>Refresh</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="px-3 md:px-6 pt-0 pb-3 md:pb-6">                        <ProcurementsDataTable
                            columns={columns}
                            data={filteredProcurements}
                            loading={loading}
                            error={error || null}
                            userRole={userRole}
                            searchValue={searchValue}
                            onRowSelectionChange={setSelectedRows}
                        />
                    </CardContent>
                </Card>
            </div>

            {/* Modals */}
            {preProcurementModalOpen && selectedProcurement && (
                <PreProcurementModal
                    open={preProcurementModalOpen}
                    onOpenChange={setPreProcurementModalOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => window.location.reload()}
                />
            )}
            {preBidConferenceModalOpen && selectedProcurement && (
                <PreBidConferenceModal
                    open={preBidConferenceModalOpen}
                    onOpenChange={setPreBidConferenceModalOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => window.location.reload()}
                />
            )}
            {supplementalBidBulletinModalOpen && selectedProcurement && (
                <SupplementalBidBulletinModal
                    open={supplementalBidBulletinModalOpen}
                    onOpenChange={setSupplementalBidBulletinModalOpen}
                    procurementId={selectedProcurement.id}
                    procurementTitle={selectedProcurement.title}
                    onComplete={() => window.location.reload()}
                />
            )}
        </AppLayout>
    );
}
