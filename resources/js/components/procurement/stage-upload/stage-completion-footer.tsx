import { Button } from '@/components/ui/button';
import { CardFooter } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Lock } from 'lucide-react';

interface StageCompletionFooterProps {
    isStageCompleted: boolean;
    isStageFuture: boolean;
    nextStageInfo: { name: string; url: string } | null;
    allRequiredUploaded: boolean;
    isUploading: boolean;
    isMarkingComplete: boolean;
    onMarkComplete: () => void;
}

export function StageCompletionFooter({
    isStageCompleted,
    isStageFuture,
    nextStageInfo,
    allRequiredUploaded,
    isUploading,
    isMarkingComplete,
    onMarkComplete,
}: StageCompletionFooterProps) {
    return (
        <CardFooter className="bg-muted/5 flex flex-col gap-4 rounded-b-xl border-t p-6 lg:col-span-2">
            {isStageCompleted ? (
                <div className="flex w-full items-center justify-between rounded-xl border border-green-500/20 bg-green-500/10 p-4">
                    <div className="flex items-center gap-3 text-green-700">
                        <div className="rounded-full bg-green-500 p-1">
                            <CheckCircle2 className="h-4 w-4 text-white" />
                        </div>
                        <span className="text-xs font-bold tracking-tight uppercase">Stage Complete</span>
                    </div>
                    {nextStageInfo && (
                        <Button variant="outline" className="border-green-500/20 bg-white text-green-700" render={<Link href={nextStageInfo.url} />}>
                            NEXT: {nextStageInfo.name} <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    )}
                </div>
            ) : isStageFuture ? (
                <Button disabled className="h-12 w-full font-black uppercase">
                    <Lock className="mr-2 h-4 w-4" /> Locked
                </Button>
            ) : (
                <Button
                    disabled={!allRequiredUploaded || isUploading || isMarkingComplete}
                    onClick={onMarkComplete}
                    className="h-12 w-full text-sm font-bold tracking-tight uppercase shadow-lg transition-all hover:-translate-y-px active:translate-y-0"
                >
                    {isMarkingComplete ? <Spinner className="mr-2 h-4 w-4" /> : <CheckCircle2 className="mr-2 h-5 w-5" />}
                    Mark as Complete
                </Button>
            )}
        </CardFooter>
    );
}
