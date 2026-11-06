import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
export function flatten(value,prefix='',result={}) {
  for(const [key,item] of Object.entries(value)){
    const name=prefix?`${prefix}.${key}`:key;
    if(item&&typeof item==='object'&&!Array.isArray(item))flatten(item,name,result);
    else result[name]=item;
  }
  return result;
}
export function validateCatalogues(chinese,english) {
  const zh=flatten(chinese),en=flatten(english),errors=[];
  const parameters=value=>[...new Set([...String(value).matchAll(/\{([A-Za-z_][A-Za-z0-9_]*)\}/g)].map(match=>match[1]))].sort().join(',');
  for(const key of new Set([...Object.keys(zh),...Object.keys(en)])){
    if(!(key in zh)||!(key in en))errors.push(`Missing ${key} in ${key in zh?'en-US':'zh-CN'}`);
    else if(typeof zh[key]!=='string'||typeof en[key]!=='string')errors.push(`Non-text translation: ${key}`);
    else if(parameters(zh[key])!==parameters(en[key]))errors.push(`Interpolation mismatch: ${key}`);
  }
  return errors;
}
export function findMissingKeys(source,chinese,english) {
  const zh=flatten(chinese),en=flatten(english),missing=new Set();
  for(const match of source.matchAll(/\b(?:t|\$t|cmsTranslate)\(\s*['"]([A-Za-z_][\w.-]*)['"]/g)){
    const key=match[1];
    // Generated CRUD translations are persisted by their own SQL migration.
    if(!key.startsWith('crud.')&&!(key in zh)&&!(key in en))missing.add(key);
  }
  return [...missing];
}
function loadCatalogue(file,errors,cache=new Map()) {
  if(cache.has(file))return cache.get(file);
  const source=fs.readFileSync(file,'utf8');
  const tree=ts.createSourceFile(file,source,ts.ScriptTarget.Latest,true);
  const visit=node=>{
    if(ts.isObjectLiteralExpression(node)){
      const names=new Set();
      for(const property of node.properties){
        const name=property.name&&(ts.isIdentifier(property.name)||ts.isStringLiteral(property.name))?property.name.text:null;
        if(name!=null){if(names.has(name))errors.push(`Duplicate key ${name}: ${path.relative(root,file)}`);names.add(name);}
      }
    }
    ts.forEachChild(node,visit);
  };visit(tree);
  const compiled=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022}}).outputText;
  const scope={exports:{},require:specifier=>{
    if(!specifier.startsWith('.'))throw new Error(`Language catalogue must use local modules: ${specifier}`);
    const target=path.resolve(path.dirname(file),specifier.endsWith('.ts')?specifier:`${specifier}.ts`);
    return {__esModule:true,default:loadCatalogue(target,errors,cache)};
  }};
  vm.runInNewContext(compiled,scope,{filename:file});
  cache.set(file,scope.exports.default);return scope.exports.default;
}
function sourceFiles(directory) {
  return fs.readdirSync(directory,{withFileTypes:true}).flatMap(entry=>{
    const file=path.join(directory,entry.name);
    if(entry.isDirectory())return ['locales','mock'].includes(entry.name)?[]:sourceFiles(file);
    return /\.(vue|ts|tsx)$/.test(entry.name)&&!/(?:\.spec|\.test|\.d)\.ts$/.test(entry.name)?[file]:[];
  });
}
export function checkProject() {
  const errors=[];
  const zh=loadCatalogue(path.join(root,'src/shared/locales/zh-CN.ts'),errors);
  const en=loadCatalogue(path.join(root,'src/shared/locales/en-US.ts'),errors);
  errors.push(...validateCatalogues(zh,en));
  const directories=[path.join(root,'src'),path.join(root,'../admin/plugins/cms/web/src'),path.join(root,'../admin/plugins/qa/web/src')];
  for(const directory of directories)if(fs.existsSync(directory))for(const file of sourceFiles(directory)){
    for(const key of findMissingKeys(fs.readFileSync(file,'utf8'),zh,en))errors.push(`Missing referenced key ${key}: ${path.relative(root,file)}`);
  }
  return {errors:[...new Set(errors)],keys:Object.keys(flatten(zh)).length};
}
if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url)){
  const result=checkProject();
  if(result.errors.length){process.stderr.write(`${result.errors.join('\n')}\n`);process.exitCode=1;}
  else process.stdout.write(`Language checks passed: ${result.keys} keys per locale; matching placeholders and literal references.\n`);
}
