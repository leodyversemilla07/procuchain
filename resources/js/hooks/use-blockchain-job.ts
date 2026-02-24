import { useCallback, useRef } from 'react';

export type BlockchainJobStatus = 'pending' | 'done' | 'failed';

export interface BlockchainJobResult {
    status: BlockchainJobStatus;
    result?: Record<string, unknown>;
    error?: string;
}

const POLL_INTERVAL_MS = 2_000;
const MAX_POLLS = 60; // 2 minutes

function getCsrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

/**
 * Dispatches a multipart form POST to a blockchain write endpoint, then
 * polls /blockchain-job/{jobId}/status every 2 s until the job resolves.
 *
 * Usage:
 *   const { submitAndPoll } = useBlockchainJob();
 *   await submitAndPoll(url, formData);   // throws on failure/timeout
 */
export function useBlockchainJob() {
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopPolling = useCallback(() => {
        if (intervalRef.current !== null) {
            clearInterval(intervalRef.current);
            intervalRef.current = null;
        }
    }, []);

    /** Poll the status endpoint until done/failed/timeout. */
    const pollUntilDone = useCallback(
        (jobId: string): Promise<BlockchainJobResult> =>
            new Promise((resolve, reject) => {
                let polls = 0;

                intervalRef.current = setInterval(async () => {
                    polls++;

                    try {
                        const res = await fetch(`/blockchain-job/${jobId}/status`, {
                            headers: { Accept: 'application/json' },
                        });

                        const data: BlockchainJobResult = await res.json();

                        if (data.status === 'done') {
                            stopPolling();
                            resolve(data);
                        } else if (data.status === 'failed') {
                            stopPolling();
                            reject(new Error(data.error ?? 'Blockchain write failed'));
                        } else if (polls >= MAX_POLLS) {
                            stopPolling();
                            reject(new Error('Blockchain write timed out. The operation may still complete in the background.'));
                        }
                    } catch (err) {
                        stopPolling();
                        reject(err);
                    }
                }, POLL_INTERVAL_MS);
            }),
        [stopPolling],
    );

    /**
     * POST formData to url, get job_id, then poll until done.
     * Returns the final BlockchainJobResult on success.
     * Throws on HTTP error, blockchain failure, or timeout.
     */
    const submitAndPoll = useCallback(
        async (url: string, formData: FormData): Promise<BlockchainJobResult> => {
            formData.append('_token', getCsrfToken());

            const res = await fetch(url, {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                const body = await res.json().catch(() => ({}));
                throw new Error((body as { message?: string }).message ?? `HTTP ${res.status}`);
            }

            const { job_id } = (await res.json()) as { job_id: string };

            return pollUntilDone(job_id);
        },
        [pollUntilDone],
    );

    return { submitAndPoll, pollUntilDone, stopPolling };
}
