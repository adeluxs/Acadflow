// Render branded H.264/AAC launch films locally with Chrome's native MP4 encoder.
import fs from 'node:fs/promises';
import path from 'node:path';
import { createServer } from 'node:http';
import { chromium } from 'playwright';
import sharp from 'sharp';

const interactive = process.env.MARKETING_INTERACTIVE === '1';
const output = path.resolve(interactive ? process.env.MARKETING_INTERACTIVE_OUTPUT || 'marketing/video/launch/2026-10-21-interactive' : process.env.MARKETING_OUTPUT || 'marketing/video/launch/2026-10-21');
const launchUrl = process.env.MARKETING_LAUNCH_URL || '';
const launchDate = process.env.MARKETING_LAUNCH_DATE || '21 OCTOBER 2026';
await fs.access(path.join(output, 'screens/writing.png'));
const baseHtml = await fs.readFile(new URL('./launch.html', import.meta.url), 'utf8');
const html = interactive ? await fs.readFile(new URL('./interactive.html', import.meta.url)) : baseHtml;
const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://local');
        if (interactive && url.pathname === '/launch-engine.js') {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(baseHtml.match(/<script>([\s\S]*)<\/script>/)[1]);
        } else if (interactive && url.pathname === '/interactions.json') {
            response.setHeader('Content-Type', 'application/json');response.end(await fs.readFile(path.join(output,'interactions.json')));
        } else if (interactive && url.pathname.startsWith('/clips/')) {
            const file=path.resolve(output,'.'+url.pathname);
            if(!file.startsWith(output+path.sep))throw new Error('Invalid frame path');
            response.setHeader('Content-Type','image/jpeg');response.end(await fs.readFile(file));
        } else if (url.pathname.startsWith('/screens/')) {
            const file = path.basename(url.pathname);
            response.setHeader('Content-Type', file.endsWith('.svg') ? 'image/svg+xml' : 'image/png');
            response.end(await fs.readFile(path.join(output, 'screens', file)));
        } else { response.setHeader('Content-Type', 'text/html'); response.end(html); }
    } catch { response.writeHead(404); response.end('Missing launch asset'); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const address = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: true,
    args: ['--autoplay-policy=no-user-gesture-required', '--disable-background-timer-throttling', '--disable-renderer-backgrounding'],
});
const results = [];
try {
    for (const portrait of [false, true]) {
        const format = portrait ? 'portrait' : 'landscape';
        const page = await browser.newPage({ viewport: { width: portrait ? 540 : 960, height: portrait ? 960 : 540 } });
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        await page.goto(address);
        const config = await page.evaluate(options => window.setup(options), { portrait, url: launchUrl, date: launchDate });
        let position = 0;
        const previews = [];
        for (let i = 0; i < config.scenes.length; i++) {
            const scene = config.scenes[i];
            const time = position + scene.duration / 2;
            await page.evaluate(async time => { if (window.prepareAt) await window.prepareAt(time); }, time);
            await page.evaluate(time => window.drawAt(time), time);
            const data = await page.locator('canvas').evaluate(canvas => canvas.toDataURL('image/png').split(',')[1]);
            const preview = path.join(output, `${format}-scene-${i + 1}.png`);
            await fs.writeFile(preview, Buffer.from(data, 'base64'));
            previews.push(preview); position += scene.duration;
        }
        const thumbs = await Promise.all(previews.map(file => sharp(file).resize({ width: portrait ? 270 : 480 }).png().toBuffer()));
        const cw=portrait?270:480,ch=portrait?480:270,cols=3,rows=Math.ceil(thumbs.length/cols);
        await sharp({create:{width:cw*cols,height:ch*rows,channels:3,background:'#f5f6ff'}})
            .composite(thumbs.map((input,i)=>({input,left:(i%cols)*cw,top:Math.floor(i/cols)*ch}))).png().toFile(path.join(output, `${format}-storyboard.png`));
        console.log(`Recording ${format}: ${config.duration}s, ${portrait ? '1080×1920' : '1920×1080'}, H.264 + original stereo music…`);
        await page.evaluate(async () => { if (window.prepareAt) await window.prepareAt(.01); });
        const recording = await page.evaluate(() => window.record());
        if (errors.length) throw new Error(errors.join('; '));
        const file = path.join(output, `acadsyntra-${interactive?'interactive-':''}launch-${format}.mp4`);
        const handle = await fs.open(file, 'w');
        try {
            for (let offset=0;offset<recording.bytes;offset+=1024*1024) {
                const chunk=await page.evaluate(({offset,length})=>window.chunk(offset,length),{offset,length:1024*1024});
                await handle.write(Buffer.from(chunk,'base64'));
            }
        } finally { await handle.close(); }
        const playback = await page.evaluate(async () => {
            const video=document.createElement('video');video.muted=true;video.src=URL.createObjectURL(window.recording);
            await new Promise((resolve,reject)=>{video.onloadedmetadata=resolve;video.onerror=()=>reject(new Error('Encoded MP4 failed playback metadata validation'));});
            const details={duration:video.duration,width:video.videoWidth,height:video.videoHeight};
            video.currentTime=video.duration/2;await new Promise((resolve,reject)=>{video.onseeked=resolve;video.onerror=reject;});
            return {...details,seekedTo:video.currentTime};
        });
        if (!Number.isFinite(playback.duration) || Math.abs(playback.duration-config.duration)>1) throw new Error(`Unexpected duration: ${playback.duration}`);
        if (playback.width !== (portrait?1080:1920) || playback.height !== (portrait?1920:1080)) throw new Error('Unexpected export dimensions');
        results.push({format,file:path.basename(file),...recording,...playback});
        await fs.writeFile(path.join(output, 'exports.json'), JSON.stringify(results,null,2));
        const stamp = value => `${String(Math.floor(value/3600)).padStart(2,'0')}:${String(Math.floor(value/60)%60).padStart(2,'0')}:${String(Math.floor(value)%60).padStart(2,'0')},000`;
        let cursor=0;const srt=config.scenes.map((scene,i)=>{const from=cursor;cursor+=scene.duration;return `${i+1}\n${stamp(from)} --> ${stamp(cursor)}\n${scene.title.replaceAll('\n',' ')}\n${scene.body}\n`;}).join('\n');
        await fs.writeFile(path.join(output,`acadsyntra-${interactive?'interactive-':''}launch.srt`),srt);
        console.log(`Ready: ${file} (${(recording.bytes/1024/1024).toFixed(1)} MB, ${playback.duration.toFixed(2)}s; playback and seek passed)`);
        await page.close();
    }
    await fs.writeFile(path.join(output,'CREDITS.txt'),`AcadSyntra launch film\nLaunch date: ${launchDate}\n\nVisuals: real AcadSyntra demo screens and the existing AcadSyntra brand mark.\nAll shown accounts and research material are demonstration fixtures.\nMusic: original synthesized instrumental generated in launch.html; no third-party recordings used.\nCaptions and scene artwork: generated by the local launch renderer.\nNo website URL is displayed unless MARKETING_LAUNCH_URL is explicitly set.\n\nExports: 1920x1080 landscape and 1080x1920 portrait, H.264 video + AAC stereo audio.\n`);
} finally {
    await browser.close();
    await new Promise(resolve=>server.close(resolve));
}
