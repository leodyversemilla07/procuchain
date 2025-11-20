import { Head, router, usePoll } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Loader2, XCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { index as blockchainExplorerIndex } from '@/routes/admin/blockchain/explorer';

interface BlockchainPublishingStatusPageProps {
    procurement: {
        pr_number: string;
        title: string;
    };
    stage: string;
    returnUrl?: string;
    initialStatus?: StatusResponse;
}

interface StatusResponse {
    status: 'pending' | 'confirmed' | 'failed';
    summary: {
        pending: number;
        confirmed: number;
        failed: number;
        total: number;
    };
    documents: Array<{
        id: number;
        file_name: string;
        blockchain_status: 'pending' | 'confirmed' | 'failed';
        blockchain_error: string | null;
        blockchain_txid: string | null;
        blockchain_status_updated_at: string;
    }>;
}

export default function BlockchainPublishingStatusPage({ procurement, stage, returnUrl, initialStatus }: BlockchainPublishingStatusPageProps) {
    // Use initialStatus directly from props - Inertia will update it automatically
    const status = initialStatus;
    const [redirectCountdown, setRedirectCountdown] = useState(3);
    const [showDetails, setShowDetails] = useState(false);
    const [pollAttempts, setPollAttempts] = useState(0);

    const POLLING_INTERVAL = 2000; // 2 seconds
    const MAX_ATTEMPTS = 60; // 2 minutes total

    const isPending = status?.status === 'pending';
    const hasTimedOut = pollAttempts >= MAX_ATTEMPTS;

        const handleRedirect = useCallback(() => {
        const destination = returnUrl || `/procurements/${procurement.pr_number}`;
        router.visit(destination);
    }, [returnUrl, procurement.pr_number]);

    // Reload page data from server using Inertia
    const reloadStatus = useCallback(() => {
        router.reload({
            only: ['initialStatus'],
            onSuccess: () => {
                setPollAttempts((prev) => prev + 1);
            },
            onError: (error) => {
                console.error('Failed to check blockchain status:', error);
                setPollAttempts((prev) => prev + 1);
            },
        });
    }, []);

    // Initial reload if no initial status provided
    useEffect(() => {
        if (!initialStatus) {
            reloadStatus();
        }
    }, [initialStatus, reloadStatus]);

    // Use Inertia's built-in polling feature with router.reload()
    const { stop, start } = usePoll(
        POLLING_INTERVAL,
        {
            onStart: reloadStatus,
        },
        {
            autoStart: false,
            keepAlive: false, // Throttle by 90% when tab is in background
        },
    );

    // Start/stop polling based on status
    useEffect(() => {
        if (isPending && !hasTimedOut) {
            start();
        } else {
            stop();
        }

        return () => stop();
    }, [isPending, hasTimedOut, start, stop]);

    // Prevent navigation while publishing
    useEffect(() => {
        if (!isPending) return;

        // Prevent browser back button
        const handlePopState = (e: PopStateEvent) => {
            e.preventDefault();
            window.history.pushState(null, '', window.location.href);
            toast.warning('Please wait for blockchain publishing to complete');
        };

        // Prevent Inertia navigation
        const removeListener = router.on('before', () => {
            toast.warning('Please wait for blockchain publishing to complete');
            return false; // Cancel navigation
        });

        // Prevent browser tab close/reload
        const handleBeforeUnload = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            // Modern browsers will show a generic confirmation dialog
            // Custom messages are no longer supported for security reasons
        };

        // Set up history state
        window.history.pushState(null, '', window.location.href);
        window.addEventListener('popstate', handlePopState);
        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            window.removeEventListener('popstate', handlePopState);
            window.removeEventListener('beforeunload', handleBeforeUnload);
            removeListener();
        };
    }, [isPending]);

    // Show timeout warning
    useEffect(() => {
        if (hasTimedOut && isPending) {
            toast.warning('Blockchain publishing is taking longer than expected', {
                description: 'You can continue waiting or check the blockchain explorer for more details.',
            });
        }
    }, [hasTimedOut, isPending]);

    // Auto-redirect countdown on success
    useEffect(() => {
        if (status?.status === 'confirmed') {
            const countdown = setInterval(() => {
                setRedirectCountdown((prev) => {
                    if (prev <= 1) {
                        clearInterval(countdown);
                        handleRedirect();
                        return 0;
                    }
                    return prev - 1;
                });
            }, 1000);

            return () => clearInterval(countdown);
        }
    }, [status?.status, handleRedirect]);

    const handleRetry = () => {
        setPollAttempts(0);
        start();
        toast.info('Retrying blockchain status check...');
    };

    const handleCancel = () => {
        if (confirm('Are you sure you want to cancel? The blockchain publishing is still in progress.')) {
            handleRedirect();
        }
    };

    const handleCheckHealth = () => {
        router.visit(blockchainExplorerIndex.url());
    };

    const summary = status?.summary || { pending: 0, confirmed: 0, failed: 0, total: 0 };
    const documents = status?.documents || [];
    const progress = summary.total > 0 ? Math.round((summary.confirmed / summary.total) * 100) : 0;

    return (
        <>
            <Head title="Blockchain Publishing Status" />
            <div className="from-background to-muted/20 flex min-h-screen items-center justify-center bg-linear-to-b p-4">
                <Card className="border-sidebar-border/70 dark:border-sidebar-border w-full max-w-2xl shadow-2xl">
                    {/* CONFIRMED STATE */}
                    {status?.status === 'confirmed' && (
                        <>
                            <CardHeader className="space-y-3 text-center">
                                <div className="bg-success/10 border-success/20 mx-auto flex h-20 w-20 items-center justify-center rounded-full border-4">
                                    <CheckCircle className="text-success h-12 w-12" />
                                </div>
                                <CardTitle className="text-success text-2xl">Successfully Published!</CardTitle>
                                <CardDescription className="text-base">
                                    All {summary.confirmed} document{summary.confirmed !== 1 ? 's' : ''} have been confirmed on the blockchain
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="bg-success/5 border-success/20 rounded-lg border p-4 text-center">
                                    <div className="text-muted-foreground text-sm">
                                        Procurement: <span className="text-foreground font-medium">#{procurement.pr_number}</span>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        Stage: <span className="text-foreground font-medium">{stage}</span>
                                    </p>
                                    <p className="text-success mt-2 font-semibold">Redirecting in {redirectCountdown} seconds...</p>
                                </div>

                                {showDetails && (
                                    <div className="border-sidebar-border space-y-2 rounded-lg border p-4">
                                        <h4 className="text-sm font-semibold">Document Details:</h4>
                                        <div className="space-y-2">
                                            {documents.map((doc) => (
                                                <div key={doc.id} className="bg-muted/30 flex items-center justify-between rounded p-2 text-sm">
                                                    <span className="text-muted-foreground truncate">{doc.file_name}</span>
                                                    <CheckCircle className="text-success h-4 w-4 shrink-0" />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                            <CardFooter className="flex flex-col gap-3 border-t pt-6">
                                <Button onClick={handleRedirect} className="w-full" size="lg">
                                    Continue Now
                                </Button>
                                <Button onClick={() => setShowDetails(!showDetails)} variant="ghost" size="sm" className="w-full">
                                    {showDetails ? 'Hide' : 'Show'} Details
                                </Button>
                            </CardFooter>
                        </>
                    )}

                    {/* FAILED STATE */}
                    {status?.status === 'failed' && (
                        <>
                            <CardHeader className="space-y-3 text-center">
                                <div className="bg-destructive/10 border-destructive/20 mx-auto flex h-20 w-20 items-center justify-center rounded-full border-4">
                                    <XCircle className="text-destructive h-12 w-12" />
                                </div>
                                <CardTitle className="text-destructive text-2xl">Publishing Failed</CardTitle>
                                <CardDescription className="text-base">
                                    {summary.failed} document{summary.failed !== 1 ? 's' : ''} failed to publish to the blockchain
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="bg-destructive/5 border-destructive/20 rounded-lg border p-4">
                                    <p className="text-muted-foreground text-sm">
                                        Procurement: <span className="text-foreground font-medium">#{procurement.pr_number}</span>
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        Stage: <span className="text-foreground font-medium">{stage}</span>
                                    </p>
                                    <div className="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
                                        <div>
                                            <p className="text-success font-semibold">{summary.confirmed}</p>
                                            <p className="text-muted-foreground text-xs">Confirmed</p>
                                        </div>
                                        <div>
                                            <p className="text-destructive font-semibold">{summary.failed}</p>
                                            <p className="text-muted-foreground text-xs">Failed</p>
                                        </div>
                                        <div>
                                            <p className="text-warning font-semibold">{summary.pending}</p>
                                            <p className="text-muted-foreground text-xs">Pending</p>
                                        </div>
                                    </div>
                                </div>

                                {showDetails && (
                                    <div className="border-sidebar-border space-y-2 rounded-lg border p-4">
                                        <h4 className="text-sm font-semibold">Failed Documents:</h4>
                                        <div className="space-y-2">
                                            {documents
                                                .filter((doc) => doc.blockchain_status === 'failed')
                                                .map((doc) => (
                                                    <div key={doc.id} className="bg-destructive/5 space-y-1 rounded p-3">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <span className="text-foreground truncate text-sm font-medium">{doc.file_name}</span>
                                                            <XCircle className="text-destructive h-4 w-4 shrink-0" />
                                                        </div>
                                                        {doc.blockchain_error && <p className="text-destructive text-xs">{doc.blockchain_error}</p>}
                                                    </div>
                                                ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                            <CardFooter className="flex flex-col gap-3 border-t pt-6">
                                <div className="grid w-full grid-cols-2 gap-3">
                                    <Button onClick={handleRetry} variant="default" size="lg">
                                        Retry Check
                                    </Button>
                                    <Button onClick={handleCheckHealth} variant="outline" size="lg">
                                        Check Health
                                    </Button>
                                </div>
                                <Button onClick={handleCancel} variant="ghost" size="sm" className="w-full">
                                    Cancel &amp; Return
                                </Button>
                                <Button onClick={() => setShowDetails(!showDetails)} variant="ghost" size="sm" className="w-full">
                                    {showDetails ? 'Hide' : 'Show'} Error Details
                                </Button>
                            </CardFooter>
                        </>
                    )}

                    {/* PENDING STATE */}
                    {status?.status === 'pending' && (
                        <>
                            <CardHeader className="space-y-3 text-center">
                                <div className="bg-primary/10 border-primary/20 mx-auto flex h-20 w-20 items-center justify-center rounded-full border-4">
                                    {hasTimedOut ? (
                                        <AlertCircle className="text-warning h-12 w-12" />
                                    ) : (
                                        <Loader2 className="text-primary h-12 w-12 animate-spin" />
                                    )}
                                </div>
                                <CardTitle className="text-2xl">{hasTimedOut ? 'Taking Longer Than Expected' : 'Publishing to Blockchain'}</CardTitle>
                                <CardDescription className="text-base">
                                    {hasTimedOut
                                        ? 'The blockchain operation is still in progress. You can continue waiting or check the system health.'
                                        : 'Please wait while we confirm your documents on the blockchain. Do not close this page.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="bg-primary/5 border-primary/20 rounded-lg border p-4">
                                    <p className="text-muted-foreground mb-1 text-sm">
                                        Procurement: <span className="text-foreground font-medium">#{procurement.pr_number}</span>
                                    </p>
                                    <p className="text-muted-foreground mb-4 text-sm">
                                        Stage: <span className="text-foreground font-medium">{stage}</span>
                                    </p>

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Progress</span>
                                            <span className="text-foreground font-semibold">{progress}%</span>
                                        </div>
                                        <Progress value={progress} className="h-2" />
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="text-success">
                                                {summary.confirmed} of {summary.total} confirmed
                                            </span>
                                            {summary.pending > 0 && <span className="text-warning">{summary.pending} pending</span>}
                                        </div>
                                    </div>
                                </div>

                                {hasTimedOut && (
                                    <div className="bg-warning/10 border-warning/20 rounded-lg border p-4">
                                        <div className="flex items-start gap-3">
                                            <AlertCircle className="text-warning mt-0.5 h-5 w-5 shrink-0" />
                                            <div className="space-y-2">
                                                <p className="text-warning text-sm font-semibold">Operation Timeout</p>
                                                <p className="text-muted-foreground text-xs">
                                                    This operation has been running for over 2 minutes. This could indicate network issues or high
                                                    blockchain load. The operation will continue in the background.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {showDetails && summary.total > 0 && (
                                    <div className="border-sidebar-border space-y-2 rounded-lg border p-4">
                                        <h4 className="text-sm font-semibold">Document Status:</h4>
                                        <div className="max-h-48 space-y-2 overflow-y-auto">
                                            {documents.map((doc) => (
                                                <div key={doc.id} className="bg-muted/30 flex items-center justify-between rounded p-2 text-sm">
                                                    <span className="text-muted-foreground truncate">{doc.file_name}</span>
                                                    {doc.blockchain_status === 'confirmed' && (
                                                        <CheckCircle className="text-success h-4 w-4 shrink-0" />
                                                    )}
                                                    {doc.blockchain_status === 'pending' && (
                                                        <Loader2 className="text-warning h-4 w-4 shrink-0 animate-spin" />
                                                    )}
                                                    {doc.blockchain_status === 'failed' && <XCircle className="text-destructive h-4 w-4 shrink-0" />}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="bg-muted/30 rounded-lg p-3 text-center">
                                    <p className="text-muted-foreground text-xs">
                                        Attempt {pollAttempts} of {MAX_ATTEMPTS} • Checking every {POLLING_INTERVAL / 1000} seconds
                                    </p>
                                </div>
                            </CardContent>
                            <CardFooter className="flex flex-col gap-3 border-t pt-6">
                                {hasTimedOut && (
                                    <div className="grid w-full grid-cols-2 gap-3">
                                        <Button onClick={() => start()} variant="default" size="lg">
                                            Continue Waiting
                                        </Button>
                                        <Button onClick={handleCheckHealth} variant="outline" size="lg">
                                            Check Health
                                        </Button>
                                    </div>
                                )}
                                <Button onClick={() => setShowDetails(!showDetails)} variant="ghost" size="sm" className="w-full">
                                    {showDetails ? 'Hide' : 'Show'} Document Details
                                </Button>
                                {hasTimedOut && (
                                    <Button onClick={handleCancel} variant="ghost" size="sm" className="text-destructive w-full">
                                        Force Cancel (Not Recommended)
                                    </Button>
                                )}
                            </CardFooter>
                        </>
                    )}
                </Card>
            </div>
        </>
    );
}
