import { readFileSync, writeFileSync, unlinkSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const base=JSON.parse(readFileSync(path.join(root,'tsconfig.json'),'utf8'));
const file=path.join(root,`.tmp-oss-tsconfig-${process.pid}.json`);
writeFileSync(file,JSON.stringify({extends:'./tsconfig.json',compilerOptions:{paths:{...base.compilerOptions.paths,vue:['node_modules/vue'],'vue-router':['node_modules/vue-router'],'vue-i18n':['node_modules/vue-i18n'],'element-plus':['node_modules/element-plus']}},include:[...base.include,'../admin/plugins/oss/web/src/admin/plugins/oss/**/*.ts','../admin/plugins/oss/web/src/admin/plugins/oss/**/*.vue'],exclude:base.exclude},null,2));
try{const result=spawnSync(path.join(root,'node_modules/.bin/vue-tsc'),['--noEmit','-p',file],{cwd:root,stdio:'inherit'});process.exitCode=result.status??1;}finally{unlinkSync(file);}
