import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('team page uses inline brand SVGs instead of unsupported lucide brand exports', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/team.tsx'), 'utf8');

    assert.match(source, /import \{ Mail \} from 'lucide-react';/);
    assert.match(source, /function FacebookIcon\(/);
    assert.match(source, /function GithubIcon\(/);
    assert.match(source, /<GithubIcon className="size-5" \/>/);
    assert.match(source, /<FacebookIcon className="size-5" \/>/);
    assert.doesNotMatch(source, /import \{ Facebook, Github, Mail \} from 'lucide-react';/);
    assert.doesNotMatch(source, /<Github className=|<Facebook className=/);
});
