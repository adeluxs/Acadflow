import fs from 'node:fs/promises';
import path from 'node:path';

export async function writeInteractivePreview(output) {
    const manifest=JSON.parse(await fs.readFile(path.join(output,'interactions.json'),'utf8'));
    const chapters=[{time:0,label:'Welcome'}];let time=3;
    for(const clip of manifest.formats.landscape.clips){chapters.push({time,label:clip.label});time+=clip.duration;}
    chapters.push({time,label:'Launch today'});
    const template=await fs.readFile(new URL('./interactive-preview.html',import.meta.url),'utf8');
    await fs.writeFile(path.join(output,'preview.html'),template.replace('/* CHAPTER_DATA */[]',JSON.stringify(chapters).replaceAll('<','\\u003c')));
    await fs.writeFile(path.join(output,'CREDITS.txt'),`AcadSyntra interactive launch walkthrough\nLaunch date: 21 October 2026\n\nVisuals: actual desktop and mobile application interactions recorded with Playwright, with timestamped cursor and click overlays.\nAccounts, projects and research text are sample data from an isolated local fixture database.\nFootage is paced to a 52-second timeline. App screens are captured at approximately 8 frames/second; the composed film is encoded at 30 frames/second.\nMusic: original synthesized instrumental from launch.html; no third-party recordings or voiceover.\nBrand mark: existing AcadSyntra asset. No unconfirmed website URL is displayed.\n\nExports: 1920x1080 landscape and 1080x1920 portrait, H.264/AAC stereo MP4.\npreview.html supplies interactive chapter navigation and a format selector for local playback.\n`);
}
