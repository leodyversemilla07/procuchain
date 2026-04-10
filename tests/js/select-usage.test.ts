import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

function getTypeScriptFiles(directory: string): string[] {
    return readdirSync(directory).flatMap((entry) => {
        const fullPath = path.join(directory, entry);
        const stats = statSync(fullPath);

        if (stats.isDirectory()) {
            return getTypeScriptFiles(fullPath);
        }

        return fullPath.endsWith('.tsx') || fullPath.endsWith('.ts') ? [fullPath] : [];
    });
}

test('select content wraps options in SelectGroup outside ui components', () => {
    const sourceFiles = getTypeScriptFiles(path.join(process.cwd(), 'resources/js'));
    const filesUsingSelect = sourceFiles.filter((file) =>
        readFileSync(file, 'utf8').includes("from '@/components/ui/select'"),
    );

    const filesWithInvalidComposition = filesUsingSelect.filter((file) => {
        const source = readFileSync(file, 'utf8');

        return /<SelectContent[^>]*>\s*(?:<SelectItem|\{)/s.test(source);
    });

    assert.deepEqual(filesWithInvalidComposition, []);
});
