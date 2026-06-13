/**
 * Compute the diff fields between old and new values.
 */
export function computeDiff(
    oldValues: Record<string, unknown>,
    newValues: Record<string, unknown>,
): Array<{ key: string; old: string; new: string }> {
    const diff: Array<{ key: string; old: string; new: string }> = [];
    const allKeys = new Set([...Object.keys(oldValues), ...Object.keys(newValues)]);

    for (const key of allKeys) {
        const oldVal = oldValues[key];
        const newVal = newValues[key];
        const oldStr = oldVal !== undefined ? String(oldVal) : '';
        const newStr = newVal !== undefined ? String(newVal) : '';

        if (oldStr !== newStr) {
            diff.push({ key, old: oldStr, new: newStr });
        }
    }

    return diff;
}

export function getPaginationPages(current: number, last: number): (number | string)[] {
    const pages: (number | string)[] = [];

    if (last <= 7) {
        for (let i = 1; i <= last; i++) pages.push(i);
        return pages;
    }

    pages.push(1);

    if (current > 3) pages.push('...');

    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    for (let i = start; i <= end; i++) pages.push(i);

    if (current < last - 2) pages.push('...');

    pages.push(last);

    return pages;
}
