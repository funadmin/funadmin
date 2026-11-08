import { readFileSync, writeFileSync, unlinkSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

// 对未安装的文档插件源码包做类型检查：第三方依赖从宿主 node_modules 解析。
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const base = JSON.parse(readFileSync(path.join(root, 'tsconfig.json'), 'utf8'));
const hostPackages = ['vue', 'vue-router', 'vue-i18n', 'pinia', 'element-plus', 'dompurify', 'mermaid', '@tiptap/core', '@tiptap/pm/*', '@tiptap/vue-3', '@tiptap/starter-kit', '@tiptap/extension-image', '@tiptap/extension-table', '@tiptap/extension-list', '@tiptap/markdown', '@tiptap/extension-code-block-lowlight', 'lowlight', 'highlight.js'];
const paths = { ...base.compilerOptions.paths };
for (const name of hostPackages) paths[name] = [`node_modules/${name}`];
const source = '../admin/plugins/document/web/src/admin/modules/document';
const file = path.join(root, `.tmp-document-tsconfig-${process.pid}.json`);
writeFileSync(file, JSON.stringify({
  extends: './tsconfig.json',
  compilerOptions: { paths },
  include: [...base.include, `${source}/**/*.ts`, `${source}/**/*.vue`, 'tests/document/**/*.ts'],
  exclude: base.exclude
}, null, 2));
try {
  const result = spawnSync(path.join(root, 'node_modules/.bin/vue-tsc'), ['--noEmit', '-p', file], { cwd: root, stdio: 'inherit' });
  process.exitCode = result.status ?? 1;
} finally {
  unlinkSync(file);
}
