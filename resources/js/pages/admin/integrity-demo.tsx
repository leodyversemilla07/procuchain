import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import integrityBreaches from '@/routes/admin/integrity-breaches';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, Database, Download, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';

interface DemoRecord {
    id: number;
    stream: string;
    stream_key: string;
    txid: string;
    data_json: Record<string, unknown>;
    data_hash: string;
    breach_type: string | null;
    repaired_at: string | null;
}

interface IntegrityDemoPageProps {
    demoRecord: DemoRecord | null;
    blockchainData: Record<string, unknown> | null;
    status: 'initial' | 'deleted' | 'restored';
    message?: string;
}

const breadcrumbs = [
    { title: 'Admin Dashboard', href: dashboard.url() },
    { title: 'Integrity Demo', href: '#' },
];

export default function IntegrityDemoPage({ demoRecord, blockchainData, status, message }: IntegrityDemoPageProps) {
    const [processing, setProcessing] = useState(false);

    const handleAction = (action: string) => {
        setProcessing(true);
        router.post(
            '/admin/integrity-demo',
            { action },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrity System Demo" />

            <div className="p-4 sm:p-6">
                {/* Header */}
                <div className="mb-6 flex items-center gap-4">
                    <Button variant="ghost" size="icon" render={<Link href={dashboard.url()} />} nativeButton={false}>
                        <ArrowLeft data-icon="inline-start" />
                    </Button>
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold">
                            <Database className="h-6 w-6" />
                            Integrity System Demonstration
                        </h1>
                        <p className="text-muted-foreground text-sm">Test the blockchain-based data integrity and audit-tracking system</p>
                    </div>
                </div>

                {message && (
                    <Card className="mb-6 border-green-200 dark:border-green-900">
                        <CardContent className="p-4">
                            <p className="text-green-700 dark:text-green-400">{message}</p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Database Record */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database className="h-5 w-5" />
                                Database Mirror Record
                            </CardTitle>
                            <CardDescription>This is the mutable cache in MySQL</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {demoRecord ? (
                                <div className="space-y-3">
                                    <div className="grid grid-cols-2 gap-2 text-sm">
                                        <div>
                                            <span className="text-muted-foreground">Status:</span>
                                            <Badge variant={demoRecord.repaired_at ? 'default' : 'destructive'}>
                                                {demoRecord.repaired_at ? 'Repaired' : demoRecord.breach_type ? 'Breached' : 'OK'}
                                            </Badge>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">TXID:</span>
                                            <code className="text-xs">{demoRecord.txid}</code>
                                        </div>
                                    </div>
                                    <Separator />
                                    <pre className="bg-muted max-h-48 overflow-auto rounded p-3 text-xs">
                                        {JSON.stringify(demoRecord.data_json, null, 2)}
                                    </pre>
                                    <div className="bg-muted/50 rounded p-2">
                                        <span className="text-muted-foreground text-xs">Hash:</span>
                                        <code className="ml-2 font-mono text-xs">{demoRecord.data_hash}</code>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <AlertTriangle className="text-muted-foreground mb-3 h-12 w-12" />
                                    <p className="text-muted-foreground font-medium">Record DELETED from Database</p>
                                    <p className="text-muted-foreground text-sm">
                                        The attacker removed this record, but it still exists on the blockchain
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Blockchain Record */}
                    <Card className="border-green-200 dark:border-green-900">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-green-700 dark:text-green-400">
                                <CheckCircle2 className="h-5 w-5" />
                                Blockchain Record (Source of Truth)
                            </CardTitle>
                            <CardDescription>Immutable data on the blockchain</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {blockchainData ? (
                                <div className="space-y-3">
                                    <div className="flex items-center gap-2">
                                        <Badge className="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">✓ Immutable</Badge>
                                        <Badge className="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            ✓ Always Available
                                        </Badge>
                                    </div>
                                    <Separator />
                                    <pre className="max-h-48 overflow-auto rounded bg-green-50/50 p-3 text-xs dark:bg-green-950/20">
                                        {JSON.stringify(blockchainData, null, 2)}
                                    </pre>
                                    <p className="text-muted-foreground text-sm">
                                        This data cannot be modified or deleted - it's the source of truth
                                    </p>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <CheckCircle2 className="mb-3 h-12 w-12 text-green-500" />
                                    <p className="font-medium text-green-700 dark:text-green-400">Data exists on blockchain</p>
                                    <p className="text-muted-foreground text-sm">Ready to restore if needed</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Action Buttons */}
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Demo Actions</CardTitle>
                        <CardDescription>Simulate attacks and recovery</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-3">
                            {status === 'initial' && (
                                <>
                                    <Button variant="destructive" onClick={() => handleAction('delete')} disabled={processing}>
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        {processing ? 'Deleting...' : 'Delete from Database'}
                                    </Button>
                                    <Button variant="outline" onClick={() => handleAction('modify')} disabled={processing}>
                                        <AlertTriangle className="mr-2 h-4 w-4" />
                                        {processing ? 'Modifying...' : 'Modify Data (Tamper)'}
                                    </Button>
                                </>
                            )}
                            {status === 'deleted' && (
                                <Button onClick={() => handleAction('restore')} disabled={processing} className="bg-green-600 hover:bg-green-700">
                                    <Download className="mr-2 h-4 w-4" />
                                    {processing ? 'Restoring...' : 'Restore from Blockchain'}
                                </Button>
                            )}
                            {status === 'restored' && (
                                <Button variant="outline" onClick={() => handleAction('reset')} disabled={processing}>
                                    <Upload data-icon="inline-start" />
                                    Reset Demo
                                </Button>
                            )}
                            <Button variant="ghost" render={<Link href={integrityBreaches.index.url()} />} nativeButton={false}>
                                View Integrity Breaches
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* How It Works */}
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>How It Works</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="rounded-lg border p-4">
                                <h4 className="mb-2 font-medium">1. Delete from Database</h4>
                                <p className="text-muted-foreground text-sm">
                                    The attacker removes the record from MySQL. The database now shows no data.
                                </p>
                            </div>
                            <div className="rounded-lg border p-4">
                                <h4 className="mb-2 font-medium">2. Blockchain Still Has Data</h4>
                                <p className="text-muted-foreground text-sm">
                                    The blockchain is immutable - the original data is still there, untouched.
                                </p>
                            </div>
                            <div className="rounded-lg border p-4">
                                <h4 className="mb-2 font-medium">3. Restore from Blockchain</h4>
                                <p className="text-muted-foreground text-sm">
                                    The integrity service detects the deletion and restores the data from the blockchain.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
