import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

test('procurements list uses role-specific route namespace for filtering and pagination', () => {
    const source = readFileSync(path.join(process.cwd(), 'resources/js/pages/procurements/procurements-list.tsx'), 'utf8');

    assert.match(source, /import \{ index as adminProcurementsIndex \} from '@\/routes\/admin\/procurements';/);
    assert.match(source, /import \{ index as bacChairmanProcurementsIndex \} from '@\/routes\/bac-chairman\/procurements';/);
    assert.match(source, /import \{ index as bacSecretariatProcurementsIndex \} from '@\/routes\/bac-secretariat\/procurements';/);
    assert.match(source, /import \{ index as hopeProcurementsIndex \} from '@\/routes\/hope\/procurements';/);

    assert.match(source, /const getProcurementsListUrl = useCallback\(/);
    assert.match(source, /case 'admin':[\s\S]*adminProcurementsIndex\.url\(options\);/);
    assert.match(source, /case 'bac_chairman':[\s\S]*bacChairmanProcurementsIndex\.url\(options\);/);
    assert.match(source, /case 'hope':[\s\S]*hopeProcurementsIndex\.url\(options\);/);
    assert.match(source, /default:[\s\S]*bacSecretariatProcurementsIndex\.url\(options\);/);

    assert.doesNotMatch(source, /procurementsRoutes\.index\.url\(/);
    assert.match(source, /getProcurementsListUrl\(\{\s*mergeQuery:/);
});
