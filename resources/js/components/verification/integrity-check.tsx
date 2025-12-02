import { CheckCircle, XCircle, AlertTriangle, Hash, FileCheck } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

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
        <Card className={cn(
            'transition-all',
            isValid
                ? 'border-green-200 dark:border-green-800'
                : 'border-red-200 dark:border-red-800'
        )}>
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        {isValid ? (
                            <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                        ) : (
                            <XCircle className="h-5 w-5 text-red-600 dark:text-red-400" />
                        )}
                        <CardTitle className="text-base">
                            {documentType || 'Document'}
                        </CardTitle>
                    </div>
                    <Badge variant={isValid ? 'default' : 'destructive'}>
                        {isValid ? 'Verified' : 'Failed'}
                    </Badge>
                </div>
                <CardDescription className="text-xs truncate" title={result.file_key}>
                    {result.file_key}
                </CardDescription>
            </CardHeader>

            {showDetails && (
                <CardContent className="pt-0">
                    <div className="space-y-3">
                        {/* Hash Information */}
                        <div className="rounded-lg bg-muted/50 p-3 space-y-2">
                            <div className="flex items-center gap-2 text-sm">
                                <Hash className="h-4 w-4 text-muted-foreground" />
                                <span className="font-medium">Hash Verification</span>
                            </div>

                            <div className="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span className="text-muted-foreground">Expected:</span>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <code className="ml-1 font-mono bg-background px-1 py-0.5 rounded cursor-help">
                                                    {shortenHash(result.expected_hash)}
                                                </code>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p className="font-mono text-xs">{result.expected_hash || 'N/A'}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Actual:</span>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <code className="ml-1 font-mono bg-background px-1 py-0.5 rounded cursor-help">
                                                    {shortenHash(result.actual_hash)}
                                                </code>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p className="font-mono text-xs">{result.actual_hash || 'N/A'}</p>
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
                                        <XCircle className="h-3 w-3 mt-0.5 shrink-0" />
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
                                        <AlertTriangle className="h-3 w-3 mt-0.5 shrink-0" />
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
