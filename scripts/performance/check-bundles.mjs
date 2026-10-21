import { readFile } from 'node:fs/promises';
import { gzipSync } from 'node:zlib';
import assert from 'node:assert/strict';

const manifest = JSON.parse(await readFile(new URL('../../public/build/manifest.json', import.meta.url), 'utf8'));
const checked = new Set();
async function staticGraph(key) {
    if (checked.has(key)) return 0;
    checked.add(key);
    const entry = manifest[key];
    assert.ok(entry, `Missing manifest entry ${key}`);
    let bytes = gzipSync(await readFile(new URL(`../../public/build/${entry.file}`, import.meta.url))).length;
    assert.ok(!/rich-editor|runtime-core|quill|bootstrap/.test(entry.file), `Optional dependency entered the main bundle: ${entry.file}`);
    for (const dependency of entry.imports || []) bytes += await staticGraph(dependency);
    return bytes;
}
const bytes = await staticGraph('resources/js/app.js');
assert.ok(bytes <= 8192, `Global JavaScript is ${bytes} gzip bytes; budget is 8192`);
const css = gzipSync(await readFile(new URL(`../../public/build/${manifest['resources/css/app.css'].file}`, import.meta.url))).length;
assert.ok(css <= 25600, `Global CSS is ${css} gzip bytes; budget is 25600`);
console.log(JSON.stringify({ global_js_gzip_bytes: bytes, global_css_gzip_bytes: css, passed: true }));
