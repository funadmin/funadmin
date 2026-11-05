import { readFileSync, writeFileSync, unlinkSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const base=JSON.parse(readFileSync(path.join(root,'tsconfig.json'),'utf8'));
const file=path.join(root,`.tmp-cms-tsconfig-${process.pid}.json`);
writeFileSync(file,JSON.stringify({extends:'./tsconfig.json',compilerOptions:{paths:{...base.compilerOptions.paths,vue:['node_modules/vue']}},include:[...base.include,'../webman-admin/plugins/cms/admin-web/src/admin/modules/cms/**/*.ts','../webman-admin/plugins/cms/admin-web/src/admin/modules/cms/**/*.vue'],exclude:base.exclude},null,2));
try{const result=spawnSync(path.join(root,'node_modules/.bin/vue-tsc'),['--noEmit','-p',file],{cwd:root,stdio:'inherit'});process.exitCode=result.status??1;}finally{unlinkSync(file);}
