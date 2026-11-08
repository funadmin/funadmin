import { readFileSync, writeFileSync, unlinkSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const base=JSON.parse(readFileSync(path.join(root,'tsconfig.json'),'utf8'));
const file=path.join(root,`.tmp-cms-tsconfig-${process.pid}.json`);
writeFileSync(file,JSON.stringify({extends:'./tsconfig.json',compilerOptions:{paths:{...base.compilerOptions.paths,vue:['node_modules/vue'],'vue-router':['node_modules/vue-router']}},include:[...base.include,'../admin/plugins/cms/web/src/admin/plugins/cms/**/*.ts','../admin/plugins/cms/web/src/admin/plugins/cms/**/*.vue','../admin/plugins/qa/web/src/admin/plugins/qa/**/*.vue'],exclude:base.exclude},null,2));
try{const result=spawnSync(path.join(root,'node_modules/.bin/vue-tsc'),['--noEmit','-p',file],{cwd:root,stdio:'inherit'});process.exitCode=result.status??1;}finally{unlinkSync(file);}
