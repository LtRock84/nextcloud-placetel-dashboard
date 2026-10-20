import {guideToText} from './guide-text.mjs';
import {build} from 'esbuild';
import {readFile,writeFile} from 'node:fs/promises';
import {resolve,join} from 'node:path';
const root=import.meta.dirname;
await build({entryPoints:[join(root,'ui-src/nextcloud.tsx')],bundle:true,minify:true,format:'iife',target:['es2022'],outfile:join(root,'placetel/js/main.js'),define:{'process.env.NODE_ENV':'"production"'},plugins:[{name:'inline-css',setup(b){b.onResolve({filter:/\.css\?inline$/},a=>({path:resolve(a.resolveDir,a.path.replace('?inline','')),namespace:'inline-css'}));b.onLoad({filter:/.*/,namespace:'inline-css'},async a=>({contents:await readFile(a.path,'utf8'),loader:'text'}));}}]});

await writeFile(join(root,'placetel/help/ereignisanbindung.txt'),guideToText(JSON.parse(await readFile(join(root,'ui-src/setup-guide.json'),'utf8'))));
