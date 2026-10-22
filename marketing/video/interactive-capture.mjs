// Record real UI states and actual pointer/click events, using demo accounts only.
import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';

const output=path.resolve(process.env.MARKETING_INTERACTIVE_OUTPUT || 'marketing/video/launch/2026-10-21-interactive');
const source=path.resolve(process.env.MARKETING_OUTPUT || 'marketing/video/launch/2026-10-21');
const base=process.env.MARKETING_DEMO_URL || 'http://127.0.0.1:8013';
if(!['127.0.0.1','localhost'].includes(new URL(base).hostname))throw new Error('Use the isolated local fixture server.');
await fs.mkdir(output,{recursive:true});await fs.cp(path.join(source,'screens'),path.join(output,'screens'),{recursive:true});
const browser=await chromium.launch({executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const results={recordedAt:new Date().toISOString(),demoOnly:true,formats:{}};
try{
 for(const portrait of [false,true]){
  const format=portrait?'portrait':'landscape',viewport=portrait?{width:480,height:720}:{width:1600,height:760};
  const context=await browser.newContext({viewport,serviceWorkers:'block'}),page=await context.newPage();
  page.setDefaultTimeout(15000);
  let pointer={x:viewport.width*.6,y:viewport.height*.6},active=null;
  const event=(kind,point=pointer)=>{if(active)active.events.push({kind,t:(Date.now()-active.start)/1000,...point});};
  const move=async(locator)=>{
   await locator.scrollIntoViewIfNeeded();const box=await locator.boundingBox();if(!box)throw new Error('Demo target is not visible');
   const target={x:box.x+box.width/2,y:box.y+Math.min(box.height/2,24)},from={...pointer};
   for(let i=1;i<=12;i++){const k=1-Math.pow(1-i/12,3);pointer={x:from.x+(target.x-from.x)*k,y:from.y+(target.y-from.y)*k};await page.mouse.move(pointer.x,pointer.y);event('move');await pause(22);}
  };
  const click=async(locator)=>{await move(locator);event('click');await page.mouse.click(pointer.x,pointer.y);await pause(250);};
  const record=async(id,label,title,duration,action)=>{
   const directory=path.join(output,'clips',format,id);await fs.mkdir(directory,{recursive:true});
   const clip={id,label,title,duration,frames:[],events:[],viewport,start:Date.now()};active=clip;event('move');let running=true;
   const sampler=(async()=>{while(running){const tick=Date.now();try{
    const data=await page.screenshot({type:'jpeg',quality:76,timeout:5000});const file=`clips/${format}/${id}/${String(clip.frames.length).padStart(4,'0')}.jpg`;
    await fs.writeFile(path.join(output,file),data);clip.frames.push({file,t:(Date.now()-clip.start)/1000});
   }catch(error){if(!running)break;console.log(`  capture delay ${id}: ${String(error.message).split('\n')[0]}`);}await pause(Math.max(0,125-(Date.now()-tick)));}})();
   try{await pause(500);await action();await pause(700);}finally{running=false;await sampler;active=null;}
   clip.realDuration=(Date.now()-clip.start)/1000;delete clip.start;if(clip.frames.length<3)throw new Error(`No usable frames for ${id}`);
   console.log(`Captured ${format}/${id}: ${clip.frames.length} real frames, ${clip.realDuration.toFixed(1)}s`);return clip;
  };
  const saved=()=>page.waitForFunction(()=>{const form=document.querySelector('[data-research-autosave]');return form?.querySelector('[data-autosave-status]')?.dataset.state==='saved'&&form.querySelector('textarea[name="body"]').value===form.querySelector('.ql-editor').innerHTML;});
  const clips=[];
  await page.goto(base,{waitUntil:'networkidle'});
  clips.push(await record('explore','EXPLORE THE PLATFORM','Big ideas. Real possibilities.',5,async()=>{
   await click(page.getByRole('link',{name:'Explore features',exact:true}));await page.waitForLoadState('networkidle');await page.mouse.wheel(0,360);await pause(800);
  }));
  await page.goto(`${base}/login`);await page.locator('#email').fill('studio.owner@acadsyntra.test');await page.locator('#password').fill('StudioExample123!');
  await page.getByRole('button',{name:'Sign in to AcadSyntra'}).click();await page.waitForURL(/dashboard|onboarding/);
  await page.goto(`${base}/research-studio/create`);await page.locator('[data-setup-controls]').waitFor({state:'visible'});
  await page.locator('[name="methodology"]').selectOption('interviews');await page.locator('[name="discipline"]').fill('Education & student experience');
  await page.locator('[data-setup-next]').click();await page.locator('[name="research_type_id"]').selectOption({label:'Independent Research Project'});
  await page.locator('[name="title"]').fill('A clearer path from ideas to evidence');await page.locator('[name="objectives"]').fill('Plan a demonstration study, organize its questions and develop an interview guide.');
  await page.locator('[data-setup-next]').click();await page.locator('[data-setup-submit]').click();await page.waitForURL(/\/research-studio\/[a-f0-9-]{36}$/);const project=page.url();
  clips.push(await record('chapters','CHOOSE YOUR CHAPTER','One chapter. Room to think.',5,async()=>{
   await click(page.locator('[data-writing-chapter]').nth(1));await page.waitForURL(/section=/);await page.locator('.ql-editor').waitFor();await pause(650);
  }));
  await page.locator('.ql-editor').fill('Research purpose\n\nGood research begins with a clear question. Keep the reasoning connected to the writing.\n\n');await saved();await page.evaluate(()=>scrollTo(0,0));
  clips.push(await record('focus','FOCUS & AUTOSAVE','Click. Focus. Find your flow.',7,async()=>{
   await click(page.getByRole('button',{name:'Focus writing',exact:true}));
   const editor=page.locator('.ql-editor');await click(editor);await page.keyboard.press('ControlOrMeta+End');await page.keyboard.press('Enter');
   await page.keyboard.type('A clear question is the start of something meaningful.',{delay:33});await saved();
   await page.locator('[data-autosave-status]').scrollIntoViewIfNeeded();await pause(700);
  }));
  await page.getByRole('button',{name:'Exit focus',exact:true}).click();await page.locator('.ql-editor').click();await page.keyboard.press('ControlOrMeta+End');await page.keyboard.press('Enter');await saved();
  clips.push(await record('tables','TOOLS THAT WORK WITH YOU','Add it. Shape it. Keep writing.',8,async()=>{
   await click(page.getByRole('button',{name:'Insert 3 × 3 table'}));await click(page.locator('.ql-editor table td').first());
   await page.keyboard.type('Evidence',{delay:90});await pause(450);await click(page.getByRole('button',{name:'Remove table',exact:true}));await saved();
   await pause(500);
  }));
  await page.goto(`${project}/plan`);const add=page.locator('form[action$="/resources"]');await add.locator('[name="kind"]').selectOption('instrument');
  await add.locator('[name="title"]').fill('Interview guide · the questions that matter');await add.getByRole('button',{name:'Add to library'}).click();
  await page.locator('.ql-editor').fill('Interview guide\n\nWhat helps you do your best academic work?\n\n');await saved();
  clips.push(await record('library','KEEP YOUR THINKING CONNECTED','Your plan. Your questions. Your work.',7,async()=>{
   await click(page.locator('.ql-editor'));await page.keyboard.press('ControlOrMeta+End');await page.keyboard.type('What would make your next study session better?',{delay:45});await saved();
   await page.locator('[data-autosave-status]').scrollIntoViewIfNeeded();await pause(650);
  }));
  await page.goto(project);await page.evaluate(()=>scrollTo(0,0));
  clips.push(await record('guide','GUIDANCE WHEN YOU NEED IT','Every next step, within reach.',10,async()=>{
   await click(page.locator('[data-studio-guide] > summary'));await click(page.getByText('Chapters, writing & focus mode',{exact:true}));await pause(1000);
   await click(page.locator('[data-studio-guide] > summary'));await click(page.getByRole('link',{name:'Writing tools',exact:true}));await pause(1000);
  }));
  results.formats[format]={viewport,clips,projectDemoOnly:true};await fs.writeFile(path.join(output,'interactions.json'),JSON.stringify(results,null,2));await context.close();
 }
 console.log(`Interactive recordings ready: ${output}`);
}finally{await browser.close();}
