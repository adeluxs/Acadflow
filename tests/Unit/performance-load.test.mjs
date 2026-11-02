import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtemp, writeFile, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const exec = promisify(execFile);
async function runFixture(valid) {
    const directory = await mkdtemp(join(tmpdir(), 'acadsyntra-load-test-'));
    const server = createServer((request, response) => {
        response.writeHead(200, { 'Content-Type': 'text/html' });
        response.end(valid ? (request.url.includes('/create') ? '<form data-research-setup></form>' : '<a>New research project</a>') : '<h1>Fatal error: failed to open index.php</h1>');
    });
    try {
        await writeFile(join(directory, 'cookies.json'), JSON.stringify(['fixture=test']));
        await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
        return await exec(process.execPath, ['scripts/performance/load.mjs'], {
            cwd: new URL('../../', import.meta.url), timeout: 10_000,
            env: { ...process.env, PERFORMANCE_TEST_URL: `http://127.0.0.1:${server.address().port}`, PERFORMANCE_COOKIES: join(directory, 'cookies.json'), PERFORMANCE_LOAD_OUTPUT: join(directory, 'results.json'), PERFORMANCE_LEVELS: '1', PERFORMANCE_RAMP_MS: '0', PERFORMANCE_HOLD_MS: '0', PERFORMANCE_THINK_MS: '0', PERFORMANCE_TIMEOUT_MS: '1000', PERFORMANCE_COOLDOWN_MS: '0', PERFORMANCE_ASSERT_BUDGETS: '1' },
        }).then(async result => ({ ...result, reports: JSON.parse(await readFile(join(directory, 'results.json'), 'utf8')) }));
    } finally {
        await new Promise(resolve => server.close(resolve));
        await rm(directory, { recursive: true, force: true });
    }
}

test('load runner rejects HTTP-200 error documents before generating traffic', async () => {
    await assert.rejects(runFixture(false), error => error.code !== 0 && error.stderr.includes('Authenticated fixture preflight failed'));
});

test('load runner records a valid authenticated-page-shaped response', async () => {
    const result = await runFixture(true);
    assert.equal(result.reports[0].requests, 1);
    assert.equal(result.reports[0].successful, 1);
    assert.equal(result.reports[0].errors, 0);
    assert.equal(result.reports[0].samples[0].valid, true);
});
