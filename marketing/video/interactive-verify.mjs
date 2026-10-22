import fs from 'node:fs/promises';
import path from 'node:path';
import {createServer} from 'node:http';
import {chromium} from 'playwright';

const output=path.resolve(process.env.MARKETING_INTERACTIVE_OUTPUT || 'marketing/video/launch/2026-10-21-interactive');
const server=createServer(async(request,response)=>{
    try{
        const filename=path.basename(new URL(request.url,'http://local').pathname)||'preview.html';
        const content=await fs.readFile(path.join(output,filename));
        response.setHeader('Content-Type',filename.endsWith('.mp4')?'video/mp4':'text/html');
        response.setHeader('Accept-Ranges','bytes');
        const range=request.headers.range?.match(/^bytes=(\d+)-(\d*)$/);
        if(range){
            const start=Number(range[1]),end=range[2]?Math.min(Number(range[2]),content.length-1):content.length-1;
            if(start> end){response.writeHead(416,{'Content-Range':`bytes */${content.length}`});response.end();return;}
            response.writeHead(206,{'Content-Range':`bytes ${start}-${end}/${content.length}`,'Content-Length':end-start+1});response.end(content.subarray(start,end+1));
        }else{response.setHeader('Content-Length',content.length);response.end(content);}
    }catch{response.writeHead(404);response.end();}
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const browser=await chromium.launch({executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true,args:['--autoplay-policy=no-user-gesture-required']});
try{
    const page=await browser.newPage();
    await page.goto(`http://127.0.0.1:${server.address().port}/preview.html`);
    const checks=[];
    for(const format of ['landscape','portrait']){
        const check=await page.evaluate(async format=>{
            const url=`acadsyntra-interactive-launch-${format}.mp4`;
            const bytes=await(await fetch(url)).arrayBuffer();
            const audio=new AudioContext();const buffer=await audio.decodeAudioData(bytes.slice(0));
            let sum=0,count=0;for(let channel=0;channel<buffer.numberOfChannels;channel++){const data=buffer.getChannelData(channel);for(let i=0;i<data.length;i+=50){sum+=data[i]*data[i];count++;}}
            const rms=Math.sqrt(sum/count);await audio.close();
            const video=document.createElement('video');video.muted=true;video.src=URL.createObjectURL(new Blob([bytes],{type:'video/mp4'}));
            await new Promise((resolve,reject)=>{video.onloadedmetadata=resolve;video.onerror=reject;});
            const canvas=document.createElement('canvas');canvas.width=video.videoWidth;canvas.height=video.videoHeight;const ctx=canvas.getContext('2d',{willReadFrequently:true});const frames=[];
            for(const time of [10.5,17,24,31.5,40]){
                await new Promise((resolve,reject)=>{video.onseeked=resolve;video.onerror=reject;video.currentTime=time;});
                ctx.drawImage(video,0,0);const pixels=ctx.getImageData(0,0,canvas.width,canvas.height).data;let bright=0,n=0;
                for(let i=0;i<pixels.length;i+=400){bright+=pixels[i]+pixels[i+1]+pixels[i+2];n+=3;}
                frames.push({time,meanBrightness:bright/n});
            }
            return {format,duration:video.duration,width:video.videoWidth,height:video.videoHeight,audioChannels:buffer.numberOfChannels,audioRms:rms,frames,decodedPreview:canvas.toDataURL('image/png').split(',')[1]};
        },format);
        const {decodedPreview,...details}=check;
        if(Math.abs(check.duration-52)>1||check.audioChannels!==2||check.audioRms<.005||check.frames.some(frame=>frame.meanBrightness<40))throw new Error(`Invalid export: ${JSON.stringify(details)}`);
        await fs.writeFile(path.join(output,`${format}-decoded-preview.png`),Buffer.from(decodedPreview,'base64'));checks.push(details);
    }
    await page.getByRole('button',{name:/FOCUS/}).click();
    await page.waitForFunction(()=>document.querySelector('video').currentTime>=13&&document.querySelector('video').currentTime<15&&!document.querySelector('video').paused);
    await page.evaluate(()=>document.querySelector('video').pause());
    await page.getByLabel('Video format').selectOption('portrait');
    await page.waitForFunction(()=>{const video=document.querySelector('video');return video.videoWidth===1080&&video.videoHeight===1920&&video.currentTime>=13&&video.currentTime<15;});
    await fs.writeFile(path.join(output,'quality-checks.json'),JSON.stringify({exports:checks,chapterSeeking:true,formatSwitchPreservesPosition:true},null,2));
    console.log('Both exports decoded successfully with stereo audio. Five live scenes per format passed image checks; chapter seeking and format switching passed.');
}finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
