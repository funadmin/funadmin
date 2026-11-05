#!/usr/bin/env node
/**
 * 扫描硬编码中文，输出 i18n 缺失清单
 * 用法：pnpm run scan:i18n
 *
 * 设计原则（KISS）：
 *  - 只输出"该 i18n 但还没 i18n"的位置，不修改源码。
 *  - 跳过：locales / mock / types / d.ts / 注释行 / console / __tests__。
 *  - 字符串识别：单/双/反引号字符串字面量 + Vue 模板文本节点 + 属性值。
 *  - 分组：pages(views) / components / layout / router / store / utils / composables / directives / api。
 *  - 每个文件最多展示前 8 条短语，避免噪音。
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const SRC = path.join(ROOT, 'src');
const OUT = path.join(ROOT, 'docs', 'i18n-gap.md');

const SKIP_DIRS = new Set(['locales', 'mock', '__tests__', 'node_modules', 'dist']);
const SKIP_FILE_PATTERNS = [/\.d\.ts$/, /\.spec\.ts$/, /\.test\.ts$/];

const CHINESE = /[\u4e00-\u9fff]/;
const STR_LITERAL = /(["'`])((?:\\.|(?!\1).)*?)\1/g;
const SCRIPT_BLOCK = /<script[^>]*>([\s\S]*?)<\/script>/i;
const TEMPLATE_BLOCK = /<template[^>]*>([\s\S]*?)<\/template>/i;
const TPL_TEXT = />([^<>{}]+)</g;
const TPL_ATTR = /\s([\w:-]+)=("([^"]*)"|'([^']*)')/g;

/** 递归收集源文件 */
function walk(dir, out = []) {
  for (const name of fs.readdirSync(dir)) {
    const full = path.join(dir, name);
    const stat = fs.statSync(full);
    if (stat.isDirectory()) {
      if (SKIP_DIRS.has(name)) continue;
      walk(full, out);
    } else {
      if (SKIP_FILE_PATTERNS.some((re) => re.test(name))) continue;
      if (/\.(vue|ts|tsx)$/.test(name)) out.push(full);
    }
  }
  return out;
}

/** 去掉单行 // 注释和块注释 */
function stripComments(code) {
  return code.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
}

/** 是否疑似无需 i18n（中文 console / 路径注释 / 内部日志） */
function isIgnorable(text) {
  const t = text.trim();
  if (!t) return true;
  if (t.length === 1) return true; // 单字基本是噪音
  return false;
}

/** 提取脚本中的中文字符串字面量 */
function extractFromScript(src) {
  const phrases = [];
  const code = stripComments(src);
  let m;
  STR_LITERAL.lastIndex = 0;
  while ((m = STR_LITERAL.exec(code))) {
    const raw = m[2];
    if (CHINESE.test(raw) && !isIgnorable(raw)) phrases.push(raw.trim());
  }
  // 过滤 console.* 调用周边的字符串（避免日志噪音）：粗颗粒——直接保留，让人工判断
  return phrases;
}

/** 提取模板中的文本节点 + 属性中的中文 */
function extractFromTemplate(src) {
  const phrases = [];
  // 文本节点
  let m;
  TPL_TEXT.lastIndex = 0;
  while ((m = TPL_TEXT.exec(src))) {
    const raw = m[1].replace(/\s+/g, ' ').trim();
    if (CHINESE.test(raw) && !isIgnorable(raw)) phrases.push(raw);
  }
  // 属性值（含 :placeholder="'xxx'" 等也会被脚本通道捕获，这里只取静态属性）
  TPL_ATTR.lastIndex = 0;
  while ((m = TPL_ATTR.exec(src))) {
    const name = m[1];
    if (name.startsWith(':') || name.startsWith('@') || name === 'v-perm') continue;
    const val = m[3] ?? m[4] ?? '';
    if (CHINESE.test(val) && !isIgnorable(val)) phrases.push(val.trim());
  }
  return phrases;
}

/** 处理单个文件 */
function scanFile(file) {
  const src = fs.readFileSync(file, 'utf8');
  let phrases = [];
  if (file.endsWith('.vue')) {
    const tpl = TEMPLATE_BLOCK.exec(src)?.[1] ?? '';
    const scr = SCRIPT_BLOCK.exec(src)?.[1] ?? '';
    phrases = phrases.concat(extractFromTemplate(tpl), extractFromScript(scr));
  } else {
    phrases = extractFromScript(src);
  }
  // 去重 + 限长
  return Array.from(new Set(phrases)).filter((p) => CHINESE.test(p));
}

/** 一级目录归类 */
function categoryOf(file) {
  const rel = path.relative(SRC, file).replace(/\\/g, '/');
  const seg = rel.split('/')[0];
  return seg || 'other';
}

function pad(s, n) {
  s = String(s);
  return s.length >= n ? s : s + ' '.repeat(n - s.length);
}

function main() {
  const files = walk(SRC);
  const buckets = new Map();
  let totalPhrases = 0;
  let totalFiles = 0;

  for (const f of files) {
    const phrases = scanFile(f);
    if (phrases.length === 0) continue;
    const cat = categoryOf(f);
    if (!buckets.has(cat)) buckets.set(cat, []);
    buckets.get(cat).push({ file: path.relative(ROOT, f).replace(/\\/g, '/'), phrases });
    totalFiles += 1;
    totalPhrases += phrases.length;
  }

  // 排序：按桶内文件命中数倒序
  for (const arr of buckets.values()) arr.sort((a, b) => b.phrases.length - a.phrases.length);

  const lines = [];
  lines.push('# i18n 缺失清单');
  lines.push('');
  lines.push(`> 自动生成自 \`scripts/scan-i18n.mjs\`，命中文件 **${totalFiles}** 个，去重短语共 **${totalPhrases}** 条。`);
  lines.push('> 已跳过：`src/locales/**`、`src/mock/**`、`*.d.ts`、`*.spec.ts`、`__tests__/**` 与所有注释。');
  lines.push('> 仅作"应当 i18n 但还没接入 vue-i18n"的盘点参考，不代表所有命中都必须改造。');
  lines.push('');
  lines.push('## 现状');
  lines.push('');
  lines.push('- 已有 i18n key：仅 `menu / layout / login` 三个命名空间（见 `src/locales/zh-CN.ts`）。');
  lines.push('- 业务页面、通用组件、`ElMessage` / `ElMessageBox` 文案、表单校验提示几乎全部为硬编码中文。');
  lines.push('- 推荐渐进式策略：');
  lines.push('  1. **第 1 步（建议立刻做）**：把高频通用文案抽到 `common` 命名空间（确定/取消/操作/提示/成功/失败/请输入/请选择/重置/查询/新增/编辑/删除/批量删除/导入/导出/搜索/状态/启用/禁用）。');
  lines.push('  2. **第 2 步**：按"系统管理"模块逐页改造（`system.user.*` / `system.role.*` / `system.menu.*` …）。');
  lines.push('  3. **第 3 步**：把 `useCrud` / `DataTableShell` 等基础设施内部的 `ElMessage` 文案接入 `common`。');
  lines.push('');
  lines.push('---');
  lines.push('');
  lines.push('## 命中明细（按一级目录分组）');

  const order = ['views', 'components', 'layout', 'composables', 'directives', 'router', 'store', 'utils', 'api', 'main.ts', 'App.vue', 'config'];
  const rest = [...buckets.keys()].filter((k) => !order.includes(k));
  const cats = order.filter((k) => buckets.has(k)).concat(rest);

  for (const cat of cats) {
    const items = buckets.get(cat);
    if (!items?.length) continue;
    const sum = items.reduce((s, it) => s + it.phrases.length, 0);
    lines.push('');
    lines.push(`### \`src/${cat}/\` — ${items.length} 个文件 / ${sum} 条短语`);
    lines.push('');
    lines.push('| 文件 | 命中 | 示例短语 |');
    lines.push('| ---- | ---: | ---- |');
    for (const it of items) {
      const sample = it.phrases.slice(0, 8).map((s) => '`' + s.replace(/\|/g, '\\|') + '`').join('，');
      lines.push(`| \`${it.file}\` | ${it.phrases.length} | ${sample}${it.phrases.length > 8 ? ' …' : ''} |`);
    }
  }

  lines.push('');
  lines.push('---');
  lines.push('');
  lines.push('## 建议补齐的 `common` 命名空间（草案）');
  lines.push('');
  lines.push('```ts');
  lines.push('// src/locales/zh-CN.ts');
  lines.push('common: {');
  lines.push('  ok: \'确定\', cancel: \'取消\', confirm: \'确认\', tip: \'提示\',');
  lines.push('  success: \'成功\', failed: \'失败\', loading: \'加载中\',');
  lines.push('  search: \'查询\', reset: \'重置\', refresh: \'刷新\',');
  lines.push('  add: \'新增\', edit: \'编辑\', remove: \'删除\', batchRemove: \'批量删除\',');
  lines.push('  import: \'导入\', export: \'导出\', upload: \'上传\', download: \'下载\',');
  lines.push('  status: \'状态\', enable: \'启用\', disable: \'禁用\',');
  lines.push('  pleaseInput: \'请输入\', pleaseSelect: \'请选择\',');
  lines.push('  operation: \'操作\', detail: \'详情\', clear: \'清空\',');
  lines.push('  saveSuccess: \'保存成功\', deleteSuccess: \'删除成功\',');
  lines.push('  deleteConfirm: \'确定删除该记录吗？\', batchDeleteConfirm: \'确定批量删除选中记录吗？\'');
  lines.push('}');
  lines.push('```');
  lines.push('');
  lines.push('改造时可优先把 `useCrud.ts` 内部的成功/失败 toast、`SearchForm` 的"查询/重置"按钮、`DataTableToolbar` 的工具栏标签接入 `common`，单点改动即可惠及所有列表页。');
  lines.push('');

  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, lines.join('\n'), 'utf8');

  console.log(`✓ 扫描完成：${totalFiles} 文件 / ${totalPhrases} 短语`);
  console.log(`✓ 已写入：${path.relative(ROOT, OUT)}`);
}

main();
