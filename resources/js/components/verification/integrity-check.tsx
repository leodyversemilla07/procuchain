import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { AlertTriangle, CheckCircle, FileCheck, Hash, XCircle } from 'lucide-react';

interface IntegrityResult {
    is_valid: boolean;
    verification_type: string;
    file_key: string;
    expected_hash: string | null;
    actual_hash: string | null;
    hash_match: boolean;
    errors: string[];
    warnings: string[];
    verified_at: string;
}

interface IntegrityCheckProps {
    result: IntegrityResult;
    documentType?: string;
    showDetails?: boolean;
}

function shortenHash(hash: string | null, length: number = 8): string {
    if (!hash) return 'N/A';
    if (hash.length <= length * 2) return hash;
    return `${hash.substring(0, length)}...${hash.substring(hash.length - length)}`;
}

export function IntegrityCheck({ result, documentType, showDetails = true }: IntegrityCheckProps) {
    const isValid = result.is_valid && result.hash_match;

    return (
        <Card className={cn('transition-all', isValid ? 'border-green-200 dark:border-green-800' : 'border-red-200 dark:border-red-800')}>
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        {isValid ? (
                            <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                        ) : (
                            <XCircle className="h-5 w-5 text-red-600 dark:text-red-400" />
                        )}
                        <CardTitle className="text-base">{documentType || 'Document'}</CardTitle>
                    </div>
                    <Badge variant={isValid ? 'default' : 'destructive'}>{isValid ? 'Verified' : 'Failed'}</Badge>
                </div>
                <CardDescription className="truncate text-xs" title={result.file_key}>
                    {result.file_key}
                </CardDescription>
            </CardHeader>

            {showDetails && (
                <CardContent className="pt-0">
                    <div className="space-y-3">
                        {/* Hash Information */}
                        <div className="bg-muted/50 space-y-2 rounded-lg p-2 sm:p-3">
                            <div className="flex items-center gap-2 text-xs sm:text-sm">
                                <Hash className="text-muted-foreground h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                <span className="font-medium">Hash Verification</span>
                            </div>

                            <div className="grid grid-cols-1 gap-2 text-[10px] sm:grid-cols-2 sm:text-xs">
                                <div className="flex flex-col gap-0.5">
                                    <span className="text-muted-foreground">Expected:</span>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger
                                                render={
                                                    <code className="bg-background block max-w-full cursor-help truncate rounded px-1.5 py-1 font-mono">
                                                        {shortenHash(result.expected_hash, 6)}
                                                    </code>
                                                }
                                            />
                                            <TooltipContent side="bottom" className="max-w-[280px] break-all">
                                                <p className="font-mono text-[10px] sm:text-xs">{result.expected_hash || 'N/A'}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    <span className="text-muted-foreground">Actual:</span>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger
                                                render={
                                                    <code className="bg-background block max-w-full cursor-help truncate rounded px-1.5 py-1 font-mono">
                                                        {shortenHash(result.actual_hash, 6)}
                                                    </code>
                                                }
                                            />
                                            <TooltipContent side="bottom" className="max-w-[280px] break-all">
                                                <p className="font-mono text-[10px] sm:text-xs">{result.actual_hash || 'N/A'}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </div>

                            <div className="flex items-center gap-1 text-xs">
                                {result.hash_match ? (
                                    <>
                                        <FileCheck className="h-3 w-3 text-green-600" />
                                        <span className="text-green-600 dark:text-green-400">Hashes match</span>
                                    </>
                                ) : (
                                    <>
                                        <AlertTriangle className="h-3 w-3 text-red-600" />
                                        <span className="text-red-600 dark:text-red-400">Hash mismatch detected</span>
                                    </>
                                )}
                            </div>
                        </div>

                        {/* Errors */}
                        {result.errors.length > 0 && (
                            <div className="space-y-1">
                                {result.errors.map((error, index) => (
                                    <div key={index} className="flex items-start gap-2 text-xs text-red-600 dark:text-red-400">
                                        <XCircle className="mt-0.5 h-3 w-3 shrink-0" />
                                        <span>{error}</span>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Warnings */}
                        {result.warnings.length > 0 && (
                            <div className="space-y-1">
                                {result.warnings.map((warning, index) => (
                                    <div key={index} className="flex items-start gap-2 text-xs text-yellow-600 dark:text-yellow-400">
                                        <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0" />
                                        <span>{warning}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </CardContent>
            )}
        </Card>
    );
}

export default IntegrityCheck;
