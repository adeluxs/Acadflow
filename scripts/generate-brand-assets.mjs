// Reproducible vector-source assets. No customer images or uploads are modified.
import fs from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';
import { fileURLToPath } from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const dir=path.join(root,'public/branding');
const mark=await fs.readFile(path.join(dir,'acadsyntra-mark.svg'));
const logo=await fs.readFile(path.join(dir,'acadsyntra-logo.svg'));
const sizes=[32,180,192,512];
for(const size of sizes) await sharp(mark).resize(size,size).png().toFile(path.join(dir,`acadsyntra-icon-${size}.png`));
await sharp(mark).resize(512,512).png().toFile(path.join(dir,'acadsyntra-mark.png'));
await sharp(logo).resize(840,176).png().toFile(path.join(dir,'acadsyntra-logo.png'));
const social=Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630"><defs><linearGradient id="bg"><stop stop-color="#f6f4ff"/><stop offset="1" stop-color="#ffffff"/></linearGradient></defs><rect width="1200" height="630" fill="url(#bg)"/><circle cx="1120" cy="40" r="330" fill="#eae5ff"/><text x="90" y="360" font-family="Arial, sans-serif" font-size="58" font-weight="700" letter-spacing="-2" fill="#171b33">Your academic work.</text><text x="90" y="433" font-family="Arial, sans-serif" font-size="58" fill="#4f46e5" letter-spacing="-2">Connected.</text><text x="90" y="516" font-family="Arial, sans-serif" font-size="24" fill="#646a7b">Learning · Research · Knowledge sharing</text></svg>`);
const logoPng=await sharp(logo).resize(525,110).png().toBuffer();
await sharp(social).composite([{input:logoPng,top:85,left:80}]).png().toFile(path.join(dir,'acadsyntra-social.png'));
// A PNG-based ICO directory is supported by current browsers and Windows.
const buffers=await Promise.all([16,32,48].map(size=>sharp(mark).resize(size,size).png().toBuffer()));
const head=Buffer.alloc(6+16*buffers.length);head.writeUInt16LE(1,2);head.writeUInt16LE(buffers.length,4);
let offset=head.length;
for(let i=0;i<buffers.length;i++){const n=6+i*16;head[n]=[16,32,48][i];head[n+1]=head[n];head.writeUInt16LE(1,n+4);head.writeUInt16LE(32,n+6);head.writeUInt32LE(buffers[i].length,n+8);head.writeUInt32LE(offset,n+12);offset+=buffers[i].length;}
const ico=Buffer.concat([head,...buffers]);
await fs.writeFile(path.join(root,'public/favicon.ico'),ico);
await fs.writeFile(path.join(root,'public/favicon 2.ico'),ico);
// Replace publicly reachable old asset URLs with the new identity too.
for(const file of await fs.readdir(dir)){
 if(!/^acadflow-(?:mark|logo|icon-\d+)(?: 2)?\.png$/.test(file))continue;
 const target=file.replace('acadflow-','acadsyntra-').replace(' 2.png','.png');
 await fs.copyFile(path.join(dir,target),path.join(dir,file));
}
for(const size of [192,512]){
 for(const suffix of ['', ' 2']) await fs.writeFile(path.join(root,`public/icons/icon-${size}x${size}${suffix}.svg`),mark);
 await fs.copyFile(path.join(dir,`acadsyntra-icon-${size}.png`),path.join(root,`public/icons/icon-${size}x${size}.png`));
}
console.log('AcadSyntra vector, PNG, social, PWA and favicon assets generated; old URLs contain the new artwork.');
