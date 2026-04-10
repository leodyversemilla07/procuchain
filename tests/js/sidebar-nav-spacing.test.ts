import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

function readComponent(relativePath: string): string {
    return readFileSync(path.join(process.cwd(), relativePath), 'utf8');
}

test('sidebar navigation menus apply spacing between links outside ui components', () => {
    const navMainSource = readComponent('resources/js/components/nav-main.tsx');
    const navFooterSource = readComponent('resources/js/components/nav-footer.tsx');

    assert.match(navMainSource, /<SidebarMenu className="gap-1">/);
    assert.match(navFooterSource, /<SidebarMenu className="gap-1">/);
});
