import { readFile, writeFile } from 'node:fs/promises';
import { performance } from 'node:perf_hooks';
import { setTimeout as delay } from 'node:timers/promises';

const base = new URL(process.env.PERFORMANCE_TEST_URL || 'http://127.0.0.1:8013');
if (!['localhost', '127.0.0.1', '[::1]'].includes(base.hostname) && process.env.PERFORMANCE_ALLOW_REMOTE !== '1') {
    throw new Error('Set PERFORMANCE_ALLOW_REMOTE=1 explicitly when running against your staging server.');
}
const cookies = JSON.parse(await readFile(process.env.PERFORMANCE_COOKIES || '/private/tmp/acadsyntra-studio-load-cookies.json', 'utf8'));
const levels = (process.env.PERFORMANCE_LEVELS || '100,500,1000').split(',').map(Number);
const ramp = Number(process.env.PERFORMANCE_RAMP_MS || 30_000);
const hold = Number(process.env.PERFORMANCE_HOLD_MS || 60_000);
const timeout = Number(process.env.PERFORMANCE_TIMEOUT_MS || 5000);
const think = Number(process.env.PERFORMANCE_THINK_MS || 5000);
for (const [name, value] of Object.entries({ ramp, hold, timeout, think })) {
    if (!Number.isFinite(value) || value < 0 || (name === 'timeout' && value === 0)) throw new Error(`Invalid ${name} duration`);
}
const paths = ['/research-studio', '/research-studio/create', '/research-studio/create?studio_mode=final_year'];
const quantile = (values, p) => values.length ? [...values].sort((a, b) => a - b)[Math.ceil(values.length * p) - 1] : null;
const reports = [];
// Refuse to profile a misconfigured server, an expired fixture session, or a
// generic HTTP-200 error document before starting any traffic stage.
const probe = await fetch(new URL(paths[1], base), { headers: { Cookie: cookies[0] }, redirect: 'manual', signal: AbortSignal.timeout(timeout) });
if (probe.status !== 200 || !(await probe.text()).includes('data-research-setup')) throw new Error('Authenticated fixture preflight failed; verify the server and test sessions first.');
for (const users of levels) {
    if (!Number.isInteger(users) || users < 1 || users > cookies.length) throw new Error('Missing distinct session fixtures for requested users.');
    const started = performance.now();
    const samples = [];
    let active = 0, peakInFlight = 0;
    await Promise.all(Array.from({ length: users }, async (_, index) => {
        await delay(index * ramp / users);
        let iteration = 0;
        do {
            const path = paths[(index + iteration++) % paths.length];
            const start = performance.now();
            active++; peakInFlight = Math.max(peakInFlight, active);
            try {
                const response = await fetch(new URL(path, base), {
                    headers: { Cookie: cookies[index], Accept: 'text/html' },
                    redirect: 'manual', signal: AbortSignal.timeout(timeout),
                });
                const body = await response.text();
                const valid = response.status === 200 && (path.includes('/create') ? body.includes('data-research-setup') : body.includes('New research project')) && !/Fatal error|Uncaught Error/.test(body);
                samples.push({ path, ms: performance.now() - start, status: response.status, valid, bytes: Buffer.byteLength(body), serverTiming: response.headers.get('server-timing'), ...(valid ? {} : { error: 'Unexpected page content' }) });
            } catch (error) {
                samples.push({ path, ms: performance.now() - start, status: 0, error: error.name });
            } finally { active--; }
            if (performance.now() - started < ramp + hold) await delay(think + (index % 17) * 23);
        } while (performance.now() - started < ramp + hold);
    }));
    const elapsed = (performance.now() - started) / 1000;
    const successes = samples.filter(s => s.status === 200 && s.valid);
    const times = successes.map(s => s.ms);
    const report = {
        users, ramp_ms: ramp, hold_ms: hold, elapsed_s: elapsed,
        requests: samples.length, successful: successes.length, errors: samples.length - successes.length,
        error_rate: (samples.length - successes.length) / samples.length,
        peak_in_flight: peakInFlight, throughput: successes.length / elapsed,
        successful_p50_ms: quantile(times, 0.5), successful_p95_ms: quantile(times, 0.95),
        all_p95_ms: quantile(samples.map(s => s.ms), 0.95),
        statuses: samples.reduce((result, s) => ({ ...result, [s.status]: (result[s.status] || 0) + 1 }), {}),
        samples,
    };
    reports.push(report);
    console.log(JSON.stringify({ ...report, samples: undefined }));
    await writeFile(process.env.PERFORMANCE_LOAD_OUTPUT || '/private/tmp/acadsyntra-load-results.json', JSON.stringify(reports, null, 2));
    await delay(Number(process.env.PERFORMANCE_COOLDOWN_MS || 10_000));
}
if (process.env.PERFORMANCE_ASSERT_BUDGETS === '1' && reports.some(r => r.error_rate > 0.01 || r.successful_p95_ms > 1500)) process.exitCode = 1;
