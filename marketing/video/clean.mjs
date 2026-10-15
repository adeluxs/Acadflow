// Normalise, frame and brand the raw screenshots, then build a contact sheet.
//
//   node marketing/video/clean.mjs [runId]
//
// Output: marketing/video/frames/<runId>/... plus contact-sheet.png

import fs from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';
import { ensureDir, videoDir } from './lib.mjs';

const artifactsBase = path.join(videoDir, 'artifacts');
const runs = fs.existsSync(artifactsBase)
  ? fs.readdirSync(artifactsBase).filter((name) => !name.startsWith('.')).sort()
  : [];
const runId = process.argv[2] || process.env.MARKETING_RUN_ID || runs[runs.length - 1];
if (!runId) {
  throw new Error('No capture runs found. Run "npm run video:capture" first.');
}
const rawRoot = path.join(artifactsBase, runId);
const outRoot = path.join(videoDir, 'frames', runId);
const BRAND_TOP = '#0f172a';
const BRAND_BOTTOM = '#1e293b';
const PADDING = 72;
const RADIUS = 18;

function brandBackground(width, height) {
  const svg = Buffer.from(
    `<svg width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg">
      <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="${BRAND_TOP}"/><stop offset="1" stop-color="${BRAND_BOTTOM}"/>
      </linearGradient></defs>
      <rect width="${width}" height="${height}" fill="url(#g)"/>
    </svg>`
  );
  return sharp(svg).png().toBuffer();
}

async function rounded(inputBuffer, width, height) {
  const mask = Buffer.from(
    `<svg width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg">
      <rect x="0" y="0" width="${width}" height="${height}" rx="${RADIUS}" ry="${RADIUS}" fill="#fff"/>
    </svg>`
  );
  return sharp(inputBuffer)
    .resize({ width, withoutEnlargement: true })
    .composite([{ input: mask, blend: 'dest-in' }])
    .png()
    .toBuffer();
}

async function shadow(width, height) {
  const svg = Buffer.from(
    `<svg width="${width + 40}" height="${height + 40}" xmlns="http://www.w3.org/2000/svg">
      <rect x="20" y="24" width="${width}" height="${height}" rx="${RADIUS}" ry="${RADIUS}" fill="rgba(0,0,0,0.45)"/>
    </svg>`
  );
  return sharp(svg).blur(14).png().toBuffer();
}

async function frameShot(inputFile, outputFile) {
  const meta = await sharp(inputFile).metadata();
  const targetWidth = meta.width >= 1000 ? 1280 : Math.min(meta.width, 720);
  const scaled = sharp(inputFile).resize({ width: targetWidth, withoutEnlargement: false });
  const resizedMeta = await scaled.metadata();
  const width = resizedMeta.width;
  const height = resizedMeta.height;

  const shot = await rounded(await sharp(inputFile).resize({ width }).png().toBuffer(), width, height);
  const shadowLayer = await shadow(width, height);
  const canvasWidth = width + PADDING * 2 + 40;
  const canvasHeight = height + PADDING * 2 + 40;

  const bg = await brandBackground(canvasWidth, canvasHeight);
  ensureDir(path.dirname(outputFile));
  await sharp(bg)
    .composite([
      { input: shadowLayer, top: PADDING - 20, left: PADDING - 20 },
      { input: shot, top: PADDING + 4, left: PADDING + 4 },
    ])
    .png()
    .toFile(outputFile);
}

async function contactSheet(frames, outFile) {
  const thumbWidth = 420;
  const cols = 4;
  const gap = 18;
  const cellW = thumbWidth + gap;
  const prepared = [];
  for (const frame of frames.slice(0, 24)) {
    const buf = await sharp(frame.file).resize({ width: thumbWidth }).png().toBuffer();
    const meta = await sharp(buf).metadata();
    prepared.push({ buf, height: meta.height });
  }
  if (prepared.length === 0) return;
  const rows = Math.ceil(prepared.length / cols);
  const rowHeights = [];
  for (let r = 0; r < rows; r++) {
    const slice = prepared.slice(r * cols, r * cols + cols);
    rowHeights.push(Math.max(...slice.map((p) => p.height)) + gap);
  }
  const sheetWidth = cols * cellW + gap;
  const sheetHeight = rowHeights.reduce((a, b) => a + b, 0) + gap;
  const bg = await sharp({
    create: { width: sheetWidth, height: sheetHeight, channels: 3, background: '#111827' },
  })
    .png()
    .toBuffer();

  let y = gap;
  const composites = [];
  prepared.forEach((p, i) => {
    const col = i % cols;
    const row = Math.floor(i / cols);
    if (col === 0 && row > 0) y += rowHeights[row - 1];
    composites.push({ input: p.buf, top: y, left: gap + col * cellW });
  });

  await sharp(bg).composite(composites).png().toFile(outFile);
}

async function main() {
  const manifestFile = path.join(rawRoot, 'manifest.json');
  if (!fs.existsSync(manifestFile)) {
    throw new Error(`No manifest at ${manifestFile}. Run "npm run video:capture" first.`);
  }
  const manifest = JSON.parse(fs.readFileSync(manifestFile, 'utf8'));
  const ok = manifest.shots.filter((s) => s.status === 'ok' && s.file);

  console.log(`Framing ${ok.length} screenshots → ${path.relative(process.cwd(), outRoot)}`);
  const framed = [];
  for (const shot of ok) {
    const inputFile = path.join(rawRoot, shot.file);
    const outputFile = path.join(outRoot, shot.section, path.basename(shot.file));
    try {
      await frameShot(inputFile, outputFile);
      framed.push({ ...shot, file: outputFile });
    } catch (error) {
      console.log(`  ✗ ${shot.file}: ${String(error).split('\n')[0]}`);
    }
  }

  await contactSheet(framed, path.join(outRoot, 'contact-sheet.png'));
  console.log(`\nDone: ${framed.length} frames. Contact sheet: frames/${runId}/contact-sheet.png`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
