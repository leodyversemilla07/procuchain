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
                <div className="flex w-full items-center justify-between rounded-xl border border-green-500/20 bg-primary/100/10 p-4">
                    <div className="flex items-center gap-3 text-primary">
                        <CheckCircle2 className="h-5 w-5" />
                        <span className="text-xs font-bold tracking-tight uppercase">Stage Complete</span>
                    </div>
                    {nextStageInfo && (
                        <Button variant="outline" className="border-green-500/20 bg-white text-primary" render={<Link href={nextStageInfo.url} />}>
                            NEXT: {nextStageInfo.name} <ArrowRight />
                        </Button>
                    )}
                </div>
            ) : isStageFuture ? (
                <Button disabled className="h-12 w-full font-black uppercase">
                    <Lock /> Locked
                </Button>
            ) : (
                <Button
                    disabled={!allRequiredUploaded || isUploading || isMarkingComplete}
                    onClick={onMarkComplete}
                    className="h-12 w-full text-sm font-bold tracking-tight uppercase shadow-lg transition-all hover:-translate-y-px active:translate-y-0"
                >
                    {isMarkingComplete ? <Spinner /> : <CheckCircle2 />}
                    Mark as Complete
                </Button>
            )}
        </CardFooter>
    );
}
