import React, { useState, useEffect, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { 
    Shield, 
    CheckCircle, 
    AlertCircle, 
    XCircle, 
    Clock, 
    FileText, 
    Database,
    RefreshCw,
    TrendingUp,
    Calendar,
    Hash
} from 'lucide-react';
import { toast } from 'sonner';
import { useSmartContractValidation, useBatchValidation } from '@/hooks/use-smart-contract-validation';
import {
    AuditTrailResult,
    StorageConsistencyResult,
    SmartContractSystemStatus,
    AuditTrailEntry
} from '@/types/smart-contracts';

interface SmartContractDashboardProps {
    procurementId: string;
    documentHashes?: string[];
    autoRefresh?: boolean;
    refreshInterval?: number;
}

const SmartContractDashboard: React.FC<SmartContractDashboardProps> = ({
    procurementId,
    documentHashes = [],
    autoRefresh = false,
    refreshInterval = 30000
}) => {
    const { getAuditTrail, getSystemStatus, validateStorage, isLoading } = useSmartContractValidation();
    const { validateMultipleDocuments, batchResults, isProcessing } = useBatchValidation();
    
    const [auditTrail, setAuditTrail] = useState<AuditTrailResult | null>(null);
    const [storageConsistency, setStorageConsistency] = useState<StorageConsistencyResult | null>(null);
    const [systemStatus, setSystemStatus] = useState<SmartContractSystemStatus | null>(null);
    const [lastRefresh, setLastRefresh] = useState<Date>(new Date());

    const loadData = useCallback(async () => {
        try {
            // Load audit trail
            const trail = await getAuditTrail(procurementId);
            setAuditTrail(trail);

            // Load storage consistency
            const consistency = await validateStorage(procurementId);
            setStorageConsistency(consistency);

            // Load system status
            const status = await getSystemStatus();
            setSystemStatus(status);

            // Validate multiple documents if hashes provided
            if (documentHashes.length > 0) {
                await validateMultipleDocuments(procurementId, documentHashes);
            }

            setLastRefresh(new Date());
        } catch (error) {
            toast.error('Failed to load dashboard data', {
                description: error instanceof Error ? error.message : 'Unknown error'
            });
        }
    }, [procurementId, documentHashes, getAuditTrail, validateStorage, getSystemStatus, validateMultipleDocuments]);

    useEffect(() => {
        loadData();
    }, [procurementId]);

    useEffect(() => {
        if (autoRefresh && refreshInterval > 0) {
            const interval = setInterval(loadData, refreshInterval);
            return () => clearInterval(interval);
        }
    }, [autoRefresh, refreshInterval]);

    const getStatusIcon = (status: boolean | undefined) => {
        if (status === true) return <CheckCircle className="w-4 h-4 text-green-600" />;
        if (status === false) return <XCircle className="w-4 h-4 text-red-600" />;
        return <AlertCircle className="w-4 h-4 text-yellow-600" />;
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleString();
    };

    const getActionIcon = (action: string) => {
        switch (action) {
            case 'document_upload': return <FileText className="w-4 h-4" />;
            case 'status_update': return <TrendingUp className="w-4 h-4" />;
            case 'event_log': return <Calendar className="w-4 h-4" />;
            default: return <Clock className="w-4 h-4" />;
        }
    };

    const getStreamTypeColor = (streamType: string) => {
        switch (streamType) {
            case 'documents': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
            case 'status': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            case 'events': return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
            default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
        }
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">Smart Contract Dashboard</h2>
                    <p className="text-muted-foreground">
                        Procurement: {procurementId} • Last updated: {lastRefresh.toLocaleTimeString()}
                    </p>
                </div>
                <Button 
                    onClick={loadData} 
                    disabled={isLoading || isProcessing}
                    variant="outline"
                    size="sm"
                >
                    <RefreshCw className={`w-4 h-4 mr-2 ${isLoading ? 'animate-spin' : ''}`} />
                    Refresh
                </Button>
            </div>

            {/* Status Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">System Status</CardTitle>
                        <Shield className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <span>PHP Validation</span>
                                {getStatusIcon(systemStatus?.php_validation_ready)}
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span>Configuration</span>
                                {getStatusIcon(systemStatus?.configuration_set)}
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span>System Ready</span>
                                {getStatusIcon(systemStatus?.success)}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Storage Consistency</CardTitle>
                        <Database className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            <div className="text-2xl font-bold">
                                {storageConsistency?.consistency_percentage?.toFixed(1) || '0'}%
                            </div>
                            <Progress 
                                value={storageConsistency?.consistency_percentage || 0} 
                                className="h-2"
                            />
                            <div className="text-xs text-muted-foreground">
                                {storageConsistency?.validated_documents || 0} of {storageConsistency?.total_documents || 0} documents
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Audit Trail</CardTitle>
                        <Clock className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-1">
                            <div className="text-2xl font-bold">
                                {auditTrail?.total_entries || 0}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Total blockchain entries
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Generated: {auditTrail ? formatDate(auditTrail.generated_at) : 'Not loaded'}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Main Content Tabs */}
            <Tabs defaultValue="audit" className="space-y-4">
                <TabsList>
                    <TabsTrigger value="audit">Audit Trail</TabsTrigger>
                    <TabsTrigger value="consistency">Storage Consistency</TabsTrigger>
                    <TabsTrigger value="validation">Document Validation</TabsTrigger>
                </TabsList>

                <TabsContent value="audit" className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Blockchain Audit Trail</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ScrollArea className="h-96">
                                {auditTrail?.audit_trail.map((entry: AuditTrailEntry, index: number) => (
                                    <div key={index} className="flex items-start space-x-4 p-4 border-b last:border-b-0">
                                        <div className="flex-shrink-0">
                                            {getActionIcon(entry.action)}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 mb-1">
                                                <Badge 
                                                    variant="outline" 
                                                    className={getStreamTypeColor(entry.stream_type)}
                                                >
                                                    {entry.stream_type}
                                                </Badge>
                                                <Badge variant="secondary">
                                                    {entry.action.replace('_', ' ')}
                                                </Badge>
                                            </div>
                                            <p className="text-sm font-medium">
                                                Transaction: {entry.txid}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDate(entry.formatted_time)} • User: {entry.user_address}
                                            </p>
                                            {entry.document_hash && (
                                                <div className="mt-2 text-xs space-y-1">
                                                    <div className="flex items-center gap-1">
                                                        <Hash className="w-3 h-3" />
                                                        <span className="font-mono">{entry.document_hash}</span>
                                                    </div>
                                                    {entry.document_type && (
                                                        <div>Type: {entry.document_type}</div>
                                                    )}
                                                    {entry.file_size && (
                                                        <div>Size: {(entry.file_size / 1024 / 1024).toFixed(2)} MB</div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )) || (
                                    <div className="text-center py-8 text-muted-foreground">
                                        No audit trail entries found
                                    </div>
                                )}
                            </ScrollArea>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="consistency" className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Storage Consistency Report</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {storageConsistency ? (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-sm font-medium">Overall Status</p>
                                            <div className="flex items-center gap-2 mt-1">
                                                {getStatusIcon(storageConsistency.consistent)}
                                                <span className={storageConsistency.consistent ? 'text-green-600' : 'text-red-600'}>
                                                    {storageConsistency.consistent ? 'Consistent' : 'Inconsistencies Found'}
                                                </span>
                                            </div>
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">Consistency Rate</p>
                                            <p className="text-2xl font-bold mt-1">
                                                {storageConsistency.consistency_percentage.toFixed(1)}%
                                            </p>
                                        </div>
                                    </div>

                                    {storageConsistency.inconsistencies.length > 0 && (
                                        <div>
                                            <h4 className="font-medium text-sm mb-2">Inconsistencies Found:</h4>
                                            <ScrollArea className="h-48">
                                                {storageConsistency.inconsistencies.map((inconsistency, index) => (
                                                    <div key={index} className="p-3 border rounded mb-2">
                                                        <p className="text-sm font-medium">
                                                            Document: {inconsistency.document_hash}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Transaction: {inconsistency.txid}
                                                        </p>
                                                        <div className="mt-2">
                                                            <p className="text-xs font-medium text-red-600">Errors:</p>
                                                            <ul className="text-xs text-red-600 ml-4">
                                                                {inconsistency.errors.map((error, errorIndex) => (
                                                                    <li key={errorIndex}>• {error}</li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    </div>
                                                ))}
                                            </ScrollArea>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="text-center py-8 text-muted-foreground">
                                    No consistency data available
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="validation" className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Document Validation Results</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {batchResults.length > 0 ? (
                                <ScrollArea className="h-96">
                                    {batchResults.map(([key, result], index) => (
                                        <div key={index} className="p-4 border-b last:border-b-0">
                                            <div className="flex items-center justify-between mb-2">
                                                <p className="font-medium text-sm">{key}</p>
                                                {getStatusIcon('valid' in result ? result.valid : result.consistent)}
                                            </div>
                                            {'valid' in result ? (
                                                <div className="text-xs space-y-1">
                                                    <p>Hash: <span className="font-mono">{result.blockchain_hash}</span></p>
                                                    <p>Type: {result.document_type}</p>
                                                    <p>Size: {result.file_size ? (result.file_size / 1024 / 1024).toFixed(2) + ' MB' : 'N/A'}</p>
                                                </div>
                                            ) : (
                                                <div className="text-xs">
                                                    <p>Consistency: {result.consistency_percentage?.toFixed(1)}%</p>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </ScrollArea>
                            ) : (
                                <div className="text-center py-8 text-muted-foreground">
                                    No validation results available
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    );
};

export default SmartContractDashboard;
