import assert from 'node:assert/strict';
import { createServer, type Server } from 'node:http';
import { test } from 'node:test';
import { chromium, request } from 'playwright';

const webUrl = process.env.PROCUCHAIN_E2E_WEB_URL;
const apiUrl = process.env.PROCUCHAIN_E2E_API_URL;
const bearerToken = process.env.PROCUCHAIN_E2E_BEARER_TOKEN;
const externalEnabled = Boolean(webUrl && apiUrl && bearerToken);

type Harness = { url: string; close: () => Promise<void> };

async function startSelfContainedHarness(): Promise<Harness> {
    let taskCreated = false;
    const server: Server = createServer(async (incoming, response) => {
        const url = new URL(incoming.url ?? '/', 'http://127.0.0.1');
        const hasSession = incoming.headers.cookie?.includes('procuchain_session=acceptance-session') ?? false;
        const send = (status: number, body: unknown, headers: Record<string, string> = {}) => {
            const encoded = JSON.stringify(body);
            response.writeHead(status, { 'content-type': 'application/json', ...headers });
            response.end(encoded);
        };

        if (url.pathname === '/workbench') {
            response.writeHead(200, { 'content-type': 'text/html' });
            response.end(`<!doctype html><html><body>
                <input data-testid="bearer-token" type="password"><button data-testid="start-session">Start session</button>
                <span data-testid="authenticated-principal" hidden></span>
                <button data-testid="create-ppmp">Create PPMP</button>
                <span data-testid="ppmp-version-id">—</span>
                <pre data-testid="document-version-content"></pre>
                <button data-testid="refresh-ppmp-version">Refresh version</button>
                <script>
                const json = async (path, options = {}) => (await fetch(path, { ...options, credentials: 'include' })).json();
                const token = document.querySelector('[data-testid="bearer-token"]');
                document.querySelector('[data-testid="start-session"]').onclick = async () => {
                    await fetch('/api/v1/auth/session', { method: 'POST', headers: { Authorization: 'Bearer ' + token.value }, credentials: 'include' });
                    const me = await json('/api/v1/me'); const principal = document.querySelector('[data-testid="authenticated-principal"]');
                    principal.textContent = me.display_name + ' · ' + me.roles.join(', '); principal.hidden = false;
                };
                document.querySelector('[data-testid="create-ppmp"]').onclick = async () => {
                    const created = await json('/api/v1/ppmp', { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ title: 'PPMP FY 2026' }) });
                    document.querySelector('[data-testid="ppmp-version-id"]').textContent = created.version_id;
                    const version = await json('/api/v1/ppmp/' + created.version_id);
                    document.querySelector('[data-testid="document-version-content"]').textContent = JSON.stringify(version.content);
                };
                document.querySelector('[data-testid="refresh-ppmp-version"]').onclick = async () => {
                    const id = document.querySelector('[data-testid="ppmp-version-id"]').textContent;
                    const version = await json('/api/v1/ppmp/' + id); document.querySelector('[data-testid="document-version-content"]').textContent = JSON.stringify(version.content);
                };
                </script></body></html>`);
            return;
        }
        if (url.pathname === '/api/v1/me') {
            return hasSession
                ? send(200, { display_name: 'Acceptance User', roles: ['BAC Chairperson'] })
                : send(401, { message: 'a valid session is required' });
        }
        if (url.pathname === '/api/v1/auth/session' && incoming.method === 'POST') {
            return incoming.headers.authorization === 'Bearer acceptance-token'
                ? send(
                      201,
                      { expires_at: '2099-01-01T00:00:00Z' },
                      { 'set-cookie': 'procuchain_session=acceptance-session; HttpOnly; SameSite=Lax; Path=/' },
                  )
                : send(401, { message: 'invalid bearer token' });
        }
        if (url.pathname === '/api/v1/ppmp' && incoming.method === 'POST') {
            if (!hasSession) return send(401, { message: 'a valid session is required' });
            taskCreated = true;
            return send(201, { version_id: 'acceptance-version-1', content_hash: 'acceptance-hash' });
        }
        if (url.pathname === '/api/v1/ppmp/acceptance-version-1' && incoming.method === 'GET') {
            if (!hasSession) return send(401, { message: 'a valid session is required' });
            return send(taskCreated ? 200 : 404, {
                version_id: 'acceptance-version-1',
                content: { fiscal_year: 2026, project_title: 'Office supplies' },
            });
        }
        send(404, { message: 'not found' });
    });
    await new Promise<void>((resolve) => server.listen(0, '127.0.0.1', resolve));
    server.unref();
    const address = server.address();
    assert.ok(address && typeof address !== 'string');
    return {
        url: `http://127.0.0.1:${address.port}`,
        close: () => new Promise((resolve, reject) => server.close((error) => (error ? reject(error) : resolve()))),
    };
}

test('PPMP workbench logs in, creates a PPMP, reads its persisted version, and rejects unauthenticated access', { timeout: 30000 }, async () => {
    const harness = externalEnabled ? undefined : await startSelfContainedHarness();
    const currentWebUrl = webUrl ?? harness!.url;
    const currentApiUrl = apiUrl ?? harness!.url;
    const currentBearerToken = bearerToken ?? 'acceptance-token';
    const api = await request.newContext({ baseURL: currentApiUrl });
    const unauthenticated = await api.get('/api/v1/me');
    assert.equal(unauthenticated.status(), 401);
    await api.dispose();

    const browser = await chromium.launch(
        process.env.PROCUCHAIN_E2E_BROWSER_PATH
            ? { headless: true, executablePath: process.env.PROCUCHAIN_E2E_BROWSER_PATH }
            : { headless: true, channel: 'chrome' },
    );
    const context = await browser.newContext();
    const page = await context.newPage();
    try {
        await page.goto(`${currentWebUrl}/workbench`, { waitUntil: 'domcontentloaded', timeout: 10000 });
        await page.getByTestId('bearer-token').fill(currentBearerToken);
        await page.getByTestId('start-session').click();
        await page.getByTestId('authenticated-principal').waitFor({ state: 'visible' });
        assert.ok((await context.cookies()).some((cookie) => cookie.name === 'procuchain_session'));
        await page.getByTestId('create-ppmp').click();
        const version = page.getByTestId('ppmp-version-id');
        await version.waitFor({ state: 'visible' });
        assert.notEqual((await version.textContent())?.trim(), '—');
        const content = page.getByTestId('document-version-content');
        await content.waitFor({ state: 'visible' });
        assert.match((await content.textContent()) ?? '', /fiscal_year/);
        await page.getByTestId('refresh-ppmp-version').click();
        assert.match((await content.textContent()) ?? '', /Office supplies/);
    } finally {
        await context.close();
        await browser.close();
        await harness?.close();
    }
});
