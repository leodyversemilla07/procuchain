import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowDownUp } from 'lucide-react';

interface DiffViewProps {
    oldValues: Record<string, unknown>;
    newValues: Record<string, unknown>;
    className?: string;
}

/**
 * Compute the diff fields between old and new values.
 */
function computeDiff(oldValues: Record<string, unknown>, newValues: Record<string, unknown>): Array<{ key: string; old: string; new: string }> {
    const diff: Array<{ key: string; old: string; new: string }> = [];
    const allKeys = new Set([...Object.keys(oldValues), ...Object.keys(newValues)]);

    for (const key of allKeys) {
        const oldVal = oldValues[key];
        const newVal = newValues[key];
        const oldStr = oldVal !== undefined && oldVal !== null ? String(oldVal) : '';
        const newStr = newVal !== undefined && newVal !== null ? String(newVal) : '';

        if (oldStr !== newStr) {
            diff.push({ key, old: oldStr, new: newStr });
        }
    }

    return diff;
}

/**
 * Shows a side-by-side diff of old vs new values.
 */
export function DiffView({ oldValues, newValues, className }: DiffViewProps) {
    const diff = computeDiff(oldValues, newValues);

    if (diff.length === 0) {
        return null;
    }

    return (
        <div className={className}>
            <div className="text-muted-foreground mb-1 flex items-center gap-1 text-xs">
                <ArrowDownUp />
                <span>
                    {diff.length} change{diff.length !== 1 ? 's' : ''}
                </span>
            </div>
            <div className="overflow-x-auto rounded border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="p-1 text-[10px]">Field</TableHead>
                            <TableHead className="p-1 text-[10px]">Old</TableHead>
                            <TableHead className="p-1 text-[10px]">New</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {diff.map((d) => (
                            <TableRow key={d.key}>
                                <TableCell className="p-1 font-mono text-[10px] font-medium whitespace-nowrap">{d.key}</TableCell>
                                <TableCell className="bg-destructive/10/50 p-1 font-mono text-[10px] break-all dark:bg-destructive/10/20">
                                    {d.old || <span className="italic">(empty)</span>}
                                </TableCell>
                                <TableCell className="bg-primary/10/50 p-1 font-mono text-[10px] break-all dark:bg-primary/10/20">
                                    {d.new || <span className="italic">(empty)</span>}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
