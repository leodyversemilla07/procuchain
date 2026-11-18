import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head } from '@inertiajs/react';
import { CheckCircle2, Circle, Loader2, LockKeyhole, Send } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type StageStatus = 'pending' | 'complete' | 'active';

type WorkflowState = {
    ppmpVersionId?: string;
    appVersionId?: string;
    contentHash?: string;
    signatureRequestId?: string;
    distributionId?: string;
    stage: number;
};

type Principal = { user_id: string; display_name: string; roles: string[] };
type CommitteeMember = { user_id: string; display_name: string; seat: string };

const stages = ['PPMP', 'Indicative APP', 'BAC resolution', 'HoPE approval', 'Signature', 'Issuance', 'Delivery', 'Acknowledgement'];

const defaultRulePackHash = '6f1ed002ab5595859014ebf0951522d7a5a3fdd1b2f3c7d7a8a0d8c6e1d6a5a5';
const defaultRulePackSnapshot = '018f0e2e-6f7a-7b6c-8d5e-4c3b2a190001';
const defaultCommitteeId = '018f0e2e-6f7a-7b6c-8d5e-4c3b2a190090';

export default function MvpWorkbench() {
    const [token, setToken] = useState(() => localStorage.getItem('procuchain_mvp_token') ?? '');
    const [title, setTitle] = useState('PPMP FY 2026');
    const [workflow, setWorkflow] = useState<WorkflowState>({ stage: 0 });
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('Start by creating a validated PPMP draft.');
    const [error, setError] = useState('');
    const [principal, setPrincipal] = useState<Principal>();
    const [committeeMembers, setCommitteeMembers] = useState<CommitteeMember[]>([]);

    const headers = useMemo(
        () => ({
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
        }),
        [token],
    );
    const apiUrl = (path: string) => `${import.meta.env.VITE_PROCUCHAIN_API_URL ?? ''}${path}`;

    useEffect(() => {
        if (!token) {
            setPrincipal(undefined);
            return;
        }
        fetch(apiUrl('/api/v1/me'), { headers })
            .then(async (response) => {
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message ?? 'Unable to resolve authenticated user.');
                setPrincipal(payload);
            })
            .catch((caught) => setError(caught instanceof Error ? caught.message : 'Unable to resolve authenticated user.'));
        fetch(apiUrl(`/api/v1/committees/${defaultCommitteeId}/members`), { headers })
            .then(async (response) => {
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message ?? 'Unable to load BAC membership.');
                setCommitteeMembers(payload);
            })
            .catch((caught) => setError(caught instanceof Error ? caught.message : 'Unable to load BAC membership.'));
    }, [headers, token]);

    async function post(path: string, body: Record<string, unknown> = {}) {
        setBusy(true);
        setError('');
        try {
            const response = await fetch(apiUrl(path), { method: 'POST', headers, body: JSON.stringify(body) });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.message ?? `Request failed (${response.status})`);
            }
            return payload;
        } finally {
            setBusy(false);
        }
    }

    async function createPpmp() {
        try {
            const payload = await post('/api/v1/ppmp', {
                title,
                content: {
                    fiscal_year: 2026,
                    end_user_unit: 'General Services',
                    project_title: 'Office supplies',
                    scope: 'Common office supplies',
                    market_scoping_ref: 'MVP-WORKBENCH-001',
                    abc_basis: 'Recent market scoping',
                    schedule: 'Q1 2026',
                    risks: [],
                    lines: [
                        {
                            line_id: '1',
                            description: 'Bond paper',
                            quantity: 10,
                            unit: 'ream',
                            estimated_cost: { centavos: 25000, currency: 'Php' },
                            schedule: 'January 2026',
                            mode_consideration: 'Small Value Procurement',
                        },
                    ],
                },
                template_id: 'ppmp-template',
                template_version: '2026.1',
                template_authority: 'PE template register',
                schema_id: 'ppmp-schema',
                schema_version: '1.0',
                rule_pack_snapshot_id: defaultRulePackSnapshot,
                rule_pack_id: 'rp-2026-a',
                rule_pack_hash: defaultRulePackHash,
            });
            localStorage.setItem('procuchain_mvp_token', token);
            setWorkflow({ stage: 1, ppmpVersionId: payload.version_id, contentHash: payload.content_hash });
            setMessage(`PPMP ${payload.version_id} created and durably stored.`);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Unable to create PPMP.');
        }
    }

    async function deriveApp() {
        if (!workflow.ppmpVersionId) return;
        try {
            const payload = await post(`/api/v1/ppmp/${workflow.ppmpVersionId}/indicative-app`, {
                title: 'Indicative APP FY 2026',
                template_id: 'app-template',
                template_version: '2026.1',
                template_authority: 'PE template register',
                schema_id: 'app-schema',
                schema_version: '1.0',
                rule_pack_snapshot_id: defaultRulePackSnapshot,
                rule_pack_id: 'rp-2026-a',
                rule_pack_hash: defaultRulePackHash,
            });
            setWorkflow((current) => ({ ...current, stage: 2, appVersionId: payload.version_id, contentHash: payload.content_hash }));
            setMessage('Indicative APP derived from the PPMP lineage.');
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Unable to derive APP.');
        }
    }

    async function submitApp() {
        if (!workflow.appVersionId) return;
        try {
            await post(`/api/v1/documents/${workflow.appVersionId}/submit`);
            setWorkflow((current) => ({ ...current, stage: 3 }));
            setMessage('APP submitted for BAC review.');
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Unable to submit APP.');
        }
    }

    async function advance(path: string, body: Record<string, unknown>, nextStage: number, update: Partial<WorkflowState> = {}) {
        try {
            const payload = await post(path, body);
            setWorkflow((current) => ({ ...current, ...update, stage: nextStage }));
            setMessage(`Workflow action completed: ${path}`);
            return payload;
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Workflow action failed.');
            return undefined;
        }
    }

    async function recordBacResolution() {
        if (!workflow.appVersionId) return;
        const ids = committeeMembers.map((member) => member.user_id);
        if (ids.length !== 5) {
            setError('The active BAC roster must contain exactly five designated members.');
            return;
        }
        await advance(
            `/api/v1/app/${workflow.appVersionId}/bac-resolution`,
            {
                resolution_number: `BAC-${Date.now()}`,
                membership: {
                    committee_id: defaultCommitteeId,
                    members: committeeMembers.map(({ user_id, display_name, seat }) => ({
                        user_id,
                        seat: seat === 'chairperson' ? 'Chairperson' : seat === 'vice_chairperson' ? 'ViceChairperson' : 'Member',
                        display_name,
                    })),
                },
                attendance: ids,
                votes: ids.slice(1).map((user_id) => ({ user_id, vote: 'Recommend' })),
            },
            4,
        );
    }

    async function approveAsHope() {
        if (workflow.appVersionId)
            await advance(
                `/api/v1/app/${workflow.appVersionId}/hope-approval`,
                { authority_ref: `HOPE-${Date.now()}`, grounds: 'BAC recommendation reviewed' },
                5,
            );
    }

    async function requestSignature() {
        if (workflow.appVersionId) {
            const payload = await advance(
                `/api/v1/app/${workflow.appVersionId}/signature-request`,
                { purpose: 'Approval', method: 'AdvancedElectronicSignature', declaration: 'I approve this APP', deadline: '2030-02-01T00:00:00Z' },
                5,
            );
            if (payload) setWorkflow((current) => ({ ...current, signatureRequestId: payload.signature_request_id }));
        }
    }

    async function completeSignature() {
        if (workflow.signatureRequestId && workflow.contentHash)
            await advance(
                `/api/v1/signature-requests/${workflow.signatureRequestId}/complete`,
                {
                    signed_at: '2030-01-02T00:00:00Z',
                    provider_transaction: `workbench-${Date.now()}`,
                    credential_ref: 'mvp-workbench',
                    signed_artifact_hash: workflow.contentHash,
                    validation: 'Valid',
                },
                6,
            );
    }

    async function issueApp() {
        if (workflow.appVersionId && workflow.contentHash) {
            const payload = await advance(
                `/api/v1/app/${workflow.appVersionId}/issue`,
                {
                    authority_ref: `ISSUE-${Date.now()}`,
                    artifacts: [{ artifact_id: '018f0e2e-6f7a-7b6c-8d5e-4c3b2a190096', media_type: 'application/pdf', hash: workflow.contentHash }],
                    recipients: [
                        {
                            recipient: {
                                id: '018f0e2e-6f7a-7b6c-8d5e-4c3b2a190095',
                                kind: 'ExternalParty',
                                name: 'Supplier',
                                address: 'supplier@example.test',
                            },
                            channel: 'Email',
                            acknowledgement_required: true,
                        },
                    ],
                },
                6,
            );
            if (payload) setWorkflow((current) => ({ ...current, distributionId: payload.distribution_ids?.[0] }));
        }
    }

    async function deliver() {
        if (workflow.distributionId)
            await advance(
                `/api/v1/distributions/${workflow.distributionId}/deliver`,
                { channel_ref: 'workbench-email', result: 'Delivered', attempted_at: '2030-01-03T00:00:00Z' },
                7,
            );
    }

    async function acknowledge() {
        if (workflow.distributionId && workflow.contentHash)
            await advance(
                `/api/v1/distributions/${workflow.distributionId}/acknowledge`,
                {
                    recipient_id: '018f0e2e-6f7a-7b6c-8d5e-4c3b2a190095',
                    artifact_hash: workflow.contentHash,
                    method: 'SignedReceipt',
                    declaration: 'Received',
                    acknowledged_at: '2030-01-04T00:00:00Z',
                },
                8,
            );
    }

    const actions = [
        { label: 'Create PPMP', onClick: createPpmp, disabled: workflow.stage > 0 },
        { label: 'Derive Indicative APP', onClick: deriveApp, disabled: workflow.stage !== 1 },
        { label: 'Submit APP for review', onClick: submitApp, disabled: workflow.stage !== 2 },
        { label: 'Record BAC resolution', onClick: recordBacResolution, disabled: workflow.stage !== 3 || committeeMembers.length !== 5 },
        { label: 'Approve as HoPE', onClick: approveAsHope, disabled: workflow.stage !== 4 },
        { label: 'Request signature', onClick: requestSignature, disabled: workflow.stage !== 5 },
        { label: 'Complete signature', onClick: completeSignature, disabled: workflow.stage !== 5 || !workflow.signatureRequestId },
        { label: 'Issue APP', onClick: issueApp, disabled: workflow.stage !== 6 },
        { label: 'Record delivery', onClick: deliver, disabled: workflow.stage !== 6 || !workflow.distributionId },
        { label: 'Acknowledge receipt', onClick: acknowledge, disabled: workflow.stage !== 7 },
    ];

    return (
        <>
            <Head title="MVP Workbench" />
            <main className="min-h-screen bg-slate-50 px-4 py-8 text-slate-950 sm:px-8">
                <div className="mx-auto flex max-w-7xl flex-col gap-6">
                    <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-sm font-semibold tracking-wide text-blue-700 uppercase">ProcuChain MVP</p>
                            <h1 className="mt-1 text-3xl font-semibold tracking-tight">AC-120 workflow workbench</h1>
                            <p className="mt-2 max-w-2xl text-sm text-slate-600">
                                A guided workspace for the first governed slice: planning, review, approval, signing, issuance, delivery, and
                                acknowledgement.
                            </p>
                        </div>
                        <Badge variant="outline" className="w-fit gap-2 bg-white py-2">
                            <LockKeyhole className="size-3.5" /> PostgreSQL-backed API
                        </Badge>
                    </header>

                    <Card>
                        <CardHeader>
                            <CardTitle>Authenticated session</CardTitle>
                            <CardDescription>
                                Use the MVP bearer token configured for the API. It is stored locally in this browser only.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                            <div className="grid gap-2">
                                <Label htmlFor="mvp-token">Bearer token</Label>
                                <Input
                                    id="mvp-token"
                                    type="password"
                                    value={token}
                                    onChange={(event) => setToken(event.target.value)}
                                    placeholder="Paste MVP token"
                                />
                            </div>
                            <Button variant="outline" onClick={() => localStorage.setItem('procuchain_mvp_token', token)}>
                                Save session
                            </Button>
                            {principal ? (
                                <p className="text-xs text-slate-600">
                                    {principal.display_name} · {principal.roles.join(', ')}
                                </p>
                            ) : null}
                            <p className="text-xs text-slate-600">
                                BAC roster: {committeeMembers.length ? `${committeeMembers.length} designated members loaded` : 'not loaded'}
                            </p>
                        </CardContent>
                    </Card>

                    <section className="grid gap-3 md:grid-cols-4 lg:grid-cols-8">
                        {stages.map((stage, index) => {
                            const status: StageStatus = index < workflow.stage ? 'complete' : index === workflow.stage ? 'active' : 'pending';
                            return (
                                <div key={stage} className="flex items-center gap-2 rounded-lg border bg-white p-3 md:block">
                                    {status === 'complete' ? (
                                        <CheckCircle2 className="size-4 text-emerald-600" />
                                    ) : status === 'active' ? (
                                        <Send className="size-4 text-blue-600" />
                                    ) : (
                                        <Circle className="size-4 text-slate-300" />
                                    )}
                                    <p className="mt-2 text-xs font-medium text-slate-700">{stage}</p>
                                </div>
                            );
                        })}
                    </section>

                    <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                        <Card>
                            <CardHeader>
                                <CardTitle>Planning workspace</CardTitle>
                                <CardDescription>
                                    Create the first immutable PPMP version, then advance it through the API-backed workflow.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="ppmp-title">PPMP title</Label>
                                    <Input id="ppmp-title" value={title} onChange={(event) => setTitle(event.target.value)} />
                                </div>
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="rounded-lg bg-slate-100 p-4">
                                        <p className="text-xs text-slate-500">Fiscal year</p>
                                        <p className="mt-1 font-semibold">2026</p>
                                    </div>
                                    <div className="rounded-lg bg-slate-100 p-4">
                                        <p className="text-xs text-slate-500">End-user unit</p>
                                        <p className="mt-1 font-semibold">General Services</p>
                                    </div>
                                    <div className="rounded-lg bg-slate-100 p-4">
                                        <p className="text-xs text-slate-500">Line items</p>
                                        <p className="mt-1 font-semibold">1 validated line</p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-3">
                                    {actions.map((action) => (
                                        <Button key={action.label} disabled={busy || action.disabled || !token} onClick={action.onClick}>
                                            {busy && !action.disabled ? <Loader2 className="animate-spin" /> : null}
                                            {action.label}
                                        </Button>
                                    ))}
                                </div>
                                {error ? (
                                    <p className="rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</p>
                                ) : (
                                    <p className="rounded-md bg-blue-50 p-3 text-sm text-blue-800">{message}</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Workflow evidence</CardTitle>
                                <CardDescription>Identifiers are retained as the workflow advances.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm">
                                <Evidence label="PPMP version" value={workflow.ppmpVersionId} />
                                <Evidence label="APP version" value={workflow.appVersionId} />
                                <Evidence label="Content hash" value={workflow.contentHash} />
                                <p className="text-xs leading-relaxed text-slate-500">
                                    BAC, HoPE, signature, issuance, delivery, and acknowledgement actions become available after the corresponding API
                                    records exist.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </main>
        </>
    );
}

function Evidence({ label, value }: { label: string; value?: string }) {
    return (
        <div>
            <p className="text-xs font-medium text-slate-500">{label}</p>
            <p className="mt-1 truncate font-mono text-xs text-slate-800">{value ?? '—'}</p>
        </div>
    );
}
