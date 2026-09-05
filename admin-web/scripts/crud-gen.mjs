#!/usr/bin/env node
/**
 * CRUD 页面生成器：根据 JSON 配置生成 api + 列表页 + 表单弹窗（对齐 user/role 风格）。
 * 用法：pnpm gen:crud -- scripts/crud.example.json
 *      node scripts/crud-gen.mjs scripts/crud.example.json
 *      node scripts/crud-gen.mjs --dry scripts/crud.example.json  （仅打印，不写文件）
 *
 * 业务路由由后端菜单驱动，生成后请在后台配置菜单 component，如 system/demo/index。
 */

import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');

function pascal(str) {
  return String(str)
    .split(/[-_/]/)
    .filter(Boolean)
    .map((s) => s[0].toUpperCase() + s.slice(1).toLowerCase())
    .join('');
}

function camel(str) {
  const value = pascal(str);
  return value ? value[0].toLowerCase() + value.slice(1) : '';
}

function readArgs(argv) {
  const dry = argv.includes('--dry');
  const force = argv.includes('--force');
  const rest = argv.filter((a) => !['--dry', '--force'].includes(a));
  const configPath = rest[2] || rest[1];
  return { dry, force, configPath };
}

function validate(cfg) {
  const need = ['module', 'name', 'apiPrefix', 'title', 'permPrefix', 'columns', 'formFields'];
  for (const k of need) {
    if (cfg[k] === undefined || cfg[k] === null) throw new Error(`配置缺少字段: ${k}`);
  }
  if (!/^[a-z][a-z0-9-]*(\/[a-z][a-z0-9-]*)*$/.test(cfg.module)) {
    throw new Error('module 需小写字母开头，可用 / 分隔多级目录，每段仅 a-z0-9-');
  }
  if (!/^[a-z][a-z0-9-]*$/.test(cfg.name)) throw new Error('name 需小写字母开头，仅 a-z0-9-');
  if (!cfg.columns.length) throw new Error('columns 至少一项');
  if (!cfg.formFields.length) throw new Error('formFields 至少一项');
  if (cfg.backend) {
    const backend = cfg.backend;
    if (!/^[A-Z][A-Za-z0-9]*$/.test(backend.controller || '')) {
      throw new Error('backend.controller 必须是合法的 PHP 类名');
    }
    if (!/^app\\[A-Za-z0-9_\\]+$/.test(backend.model || '')) {
      throw new Error('backend.model 必须是 app\\ 开头的完整模型类名');
    }
    if (!Array.isArray(backend.writableFields) || !backend.writableFields.length) {
      throw new Error('backend.writableFields 至少一项');
    }
    const fieldPattern = /^[a-z_][a-z0-9_]*$/;
    for (const field of backend.writableFields) {
      if (!fieldPattern.test(field)) throw new Error(`非法可写字段: ${field}`);
    }
    for (const field of backend.requiredFields || []) {
      if (!backend.writableFields.includes(field)) throw new Error(`必填字段不在可写白名单: ${field}`);
    }
    for (const [param, fields] of Object.entries(backend.searchFields || {})) {
      if (!/^[a-zA-Z][a-zA-Z0-9_]*$/.test(param)) throw new Error(`非法搜索参数: ${param}`);
      const values = Array.isArray(fields) ? fields : [fields];
      if (!values.length || values.some((field) => !fieldPattern.test(field))) {
        throw new Error(`搜索参数 ${param} 包含非法字段`);
      }
    }
    if (backend.dataScope) {
      for (const key of ['adminField', 'departmentField']) {
        if (!fieldPattern.test(backend.dataScope[key] || '')) throw new Error(`backend.dataScope.${key} 不合法`);
      }
    }
  }
}

function normalizeModulePath(value, label) {
  if (!/^[a-z][a-z0-9-]*(\/[a-z][a-z0-9-]*)*$/.test(value)) {
    throw new Error(`${label} 需小写字母开头，可用 / 分隔多级目录，每段仅 a-z0-9-`);
  }
  return value;
}

function viewModule(cfg) {
  return normalizeModulePath(cfg.viewModule || cfg.module, 'viewModule');
}

function apiModule(cfg) {
  return normalizeModulePath(cfg.apiModule || cfg.module, 'apiModule');
}

function sourceRoot(module) {
  return module.startsWith('modules/') ? path.join(ROOT, 'src', module) : path.join(ROOT, 'src/views', module);
}

function importAlias(module) {
  return module.startsWith('modules/') ? `@/${module}` : `@/api/${module}`;
}

function modelInterface(cfg) {
  const modelName = `${pascal(cfg.name)}Model`;
  const types = new Map([['id', 'number']]);
  const required = new Set(['id']);

  for (const f of cfg.formFields) {
    if (f.required) required.add(f.prop);
    if (f.type === 'number' || f.type === 'inputNumber') types.set(f.prop, 'number');
    else if (f.type === 'radio01') types.set(f.prop, '0 | 1');
    else types.set(f.prop, types.get(f.prop) || 'string');
  }

  for (const c of cfg.columns) {
    if (types.has(c.prop)) continue;
    if (c.tag) types.set(c.prop, '0 | 1');
    else if (c.prop === 'sort' || c.prop.endsWith('Id')) types.set(c.prop, 'number');
    else types.set(c.prop, 'string');
  }

  const keys = [...types.keys()].filter((k) => k !== 'id').sort();
  const lines = [`export interface ${modelName} {`, '  id: number;'];
  for (const k of keys) {
    const optional = !required.has(k);
    lines.push(`  ${k}${optional ? '?' : ''}: ${types.get(k)};`);
  }
  lines.push('}');
  return lines.join('\n');
}

function searchInitialState(cfg) {
  const parts = ['  page: 1,', '  pageSize: 10,'];
  for (const s of cfg.search || []) {
    if (s.type === 'select') {
      const numOpts = (s.options || []).every((o) => typeof o.value === 'number');
      parts.push(
        numOpts
          ? `  ${s.prop}: undefined as number | undefined,`
          : `  ${s.prop}: undefined as string | undefined,`
      );
    } else parts.push(`  ${s.prop}: '',`);
  }
  return parts.join('\n');
}

function optionValueAttr(v) {
  if (typeof v === 'number') return `:value="${v}"`;
  return `:value="'${String(v).replace(/'/g, "\\'")}'"`;
}

function searchTemplate(cfg) {
  const search = cfg.search || [];
  if (!search.length) return '';
  const items = search
    .map((s) => {
      if (s.type === 'select') {
        const opts = (s.options || [])
          .map((o) => `          <el-option label="${o.label}" ${optionValueAttr(o.value)} />`)
          .join('\n');
        return `      <el-form-item label="${s.label}" prop="${s.prop}">
        <el-select v-model="query.${s.prop}" placeholder="${s.placeholder || '全部'}" clearable class="!w-36">
${opts}
        </el-select>
      </el-form-item>`;
      }
      return `      <el-form-item label="${s.label}" prop="${s.prop}">
        <el-input v-model="query.${s.prop}" placeholder="${s.placeholder || ''}" clearable />
      </el-form-item>`;
    })
    .join('\n');
  return `
    <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
${items}
      <template #extra>
        <el-button type="primary" v-perm="'${cfg.permPrefix}:add'" @click="onAdd">
          <i class="i-ep-plus mr-1" /> 新增
        </el-button>
      </template>
    </SearchForm>`;
}

function toolbarOnly(cfg) {
  return `
    <div class="mb-3">
      <el-button type="primary" v-perm="'${cfg.permPrefix}:add'" @click="onAdd">
        <i class="i-ep-plus mr-1" /> 新增
      </el-button>
    </div>`;
}

function columnsTemplate(cfg) {
  return cfg.columns
    .map((c) => {
      if (c.tag && c.tagMap) {
        const entries = Object.entries(c.tagMap);
        const lines = entries.map(([val, meta], idx) => {
          const dir = idx === 0 ? 'v-if' : 'v-else-if';
          return `          <el-tag ${dir}="String(row.${c.prop}) === '${val}'" type="${meta.type}" size="small">${meta.text}</el-tag>`;
        });
        const width = c.width ? ` width="${c.width}"` : '';
        const minWidth = c.minWidth ? ` min-width="${c.minWidth}"` : '';
        const align = c.align ? ` align="${c.align}"` : '';
        return `      <el-table-column label="${c.label}"${width}${minWidth}${align}>
        <template #default="{ row }">
${lines.join('\n')}
        </template>
      </el-table-column>`;
      }
      const width = c.width ? ` width="${c.width}"` : '';
      const minWidth = c.minWidth ? ` min-width="${c.minWidth}"` : '';
      const align = c.align ? ` align="${c.align}"` : '';
      const ellipsis = c.ellipsis ? ' show-overflow-tooltip' : '';
      return `      <el-table-column prop="${c.prop}" label="${c.label}"${width}${minWidth}${align}${ellipsis} />`;
    })
    .join('\n');
}

function onResetBodyLines(cfg) {
  const search = cfg.search || [];
  if (!search.length) return ['query.page = 1;', 'loadData();'];
  const lines = search.map((s) =>
    s.type === 'select' ? `query.${s.prop} = undefined;` : `query.${s.prop} = '';`
  );
  lines.push('query.page = 1;', 'loadData();');
  return lines;
}

function indexVue(cfg) {
  const Model = pascal(cfg.name);
  const apiName = camel(cfg.name);
  const compName = `${pascal(viewModule(cfg))}${Model}`;
  const hasSearch = (cfg.search || []).length > 0;
  const topBlock = hasSearch ? searchTemplate(cfg) : toolbarOnly(cfg);
  const tableMt = hasSearch ? ' class="mt-4"' : '';
  const apiAlias = importAlias(apiModule(cfg));

  return `<template>
  <PageWrapper title="${cfg.title}" subtitle="${cfg.subtitle || ''}">
${topBlock}

    <el-table :data="list" v-loading="loading" border stripe${tableMt}>
${columnsTemplate(cfg)}
      <el-table-column label="操作" width="180" align="center" fixed="right">
        <template #default="{ row }">
          <el-button text type="primary" v-perm="'${cfg.permPrefix}:edit'" @click="onEdit(row as ${Model}Model)">编辑</el-button>
          <el-button text type="danger" v-perm="'${cfg.permPrefix}:delete'" @click="onDelete(row as ${Model}Model)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="flex justify-end mt-4">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        background
        @current-change="loadData"
        @size-change="loadData"
      />
    </div>

    <${Model}FormDialog v-model="dialogVisible" :row="current" @success="loadData" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { ${apiName}Api, type ${Model}Model } from '${apiAlias}/${cfg.name}';
import ${Model}FormDialog from './components/${Model}FormDialog.vue';

defineOptions({ name: '${compName}' });

const loading = ref(false);
const list = ref<${Model}Model[]>([]);
const total = ref(0);
const dialogVisible = ref(false);
const current = ref<${Model}Model | null>(null);

const query = reactive({
${searchInitialState(cfg)}
});

async function loadData() {
  loading.value = true;
  try {
    const res = await ${apiName}Api.list(query);
    list.value = res.list;
    total.value = res.total;
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  query.page = 1;
  loadData();
}

function onReset() {
${onResetBodyLines(cfg)
    .map((l) => `  ${l}`)
    .join('\n')}
}

function onAdd() {
  current.value = null;
  dialogVisible.value = true;
}

function onEdit(row: ${Model}Model) {
  current.value = row;
  dialogVisible.value = true;
}

async function onDelete(row: ${Model}Model) {
  await ElMessageBox.confirm('确认删除该记录？', '提示', { type: 'warning' });
  await ${apiName}Api.remove(row.id);
  loadData();
}

onMounted(loadData);
</script>
`;
}

function formFieldTemplate(f) {
  const span = f.span || 24;
  if (f.type === 'textarea') {
    return `        <el-col :span="${span}">
          <el-form-item label="${f.label}" prop="${f.prop}">
            <el-input v-model="form.${f.prop}" type="textarea" :rows="${f.rows || 3}" placeholder="${f.placeholder || ''}" />
          </el-form-item>
        </el-col>`;
  }
  if (f.type === 'number' || f.type === 'inputNumber') {
    return `        <el-col :span="${span}">
          <el-form-item label="${f.label}" prop="${f.prop}">
            <el-input-number v-model="form.${f.prop}" class="!w-full" controls-position="right" />
          </el-form-item>
        </el-col>`;
  }
  if (f.type === 'radio01') {
    return `        <el-col :span="${span}">
          <el-form-item label="${f.label}" prop="${f.prop}">
            <el-radio-group v-model="form.${f.prop}">
              <el-radio-button :value="1">启用</el-radio-button>
              <el-radio-button :value="0">禁用</el-radio-button>
            </el-radio-group>
          </el-form-item>
        </el-col>`;
  }
  if (f.type === 'select' && f.options) {
    const opts = f.options
      .map((o) => `              <el-option label="${o.label}" ${optionValueAttr(o.value)} />`)
      .join('\n');
    return `        <el-col :span="${span}">
          <el-form-item label="${f.label}" prop="${f.prop}">
            <el-select v-model="form.${f.prop}" placeholder="${f.placeholder || '请选择'}" class="w-full">
${opts}
            </el-select>
          </el-form-item>
        </el-col>`;
  }
  return `        <el-col :span="${span}">
          <el-form-item label="${f.label}" prop="${f.prop}">
            <el-input v-model="form.${f.prop}" placeholder="${f.placeholder || ''}" />
          </el-form-item>
        </el-col>`;
}

function defaultValueExpr(f) {
  if (f.type === 'radio01') return '1 as 0 | 1';
  if (f.type === 'number' || f.type === 'inputNumber') return '0';
  return "''";
}

function formDialogVue(cfg) {
  const Model = pascal(cfg.name);
  const apiName = camel(cfg.name);
  const fields = cfg.formFields.map((f) => formFieldTemplate(f)).join('\n');
  const rulesEntries = cfg.formFields
    .filter((f) => f.required)
    .map((f) => `  ${f.prop}: [{ required: true, message: '请填写${f.label}', trigger: 'blur' }]`);
  const rulesBlock = rulesEntries.length ? `const rules: FormRules = {\n${rulesEntries.join(',\n')}\n};` : `const rules: FormRules = {};`;

  const initialKeys = cfg.formFields.map((f) => `  ${f.prop}: ${defaultValueExpr(f)}`);
  const assignKeys = cfg.formFields.map((f) => {
    if (f.type === 'radio01') return `    form.${f.prop} = (props.row!.${f.prop} ?? 1) as 0 | 1;`;
    if (f.type === 'number' || f.type === 'inputNumber') return `    form.${f.prop} = props.row!.${f.prop} ?? 0;`;
    return `    form.${f.prop} = props.row!.${f.prop} ?? '';`;
  });
  const apiAlias = importAlias(apiModule(cfg));

  return `<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑' : '新增'"
    width="560px"
    :close-on-click-modal="false"
    @closed="onClosed"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px" class="px-2">
      <el-row :gutter="16">
${fields}
      </el-row>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { ${apiName}Api, type ${Model}Model } from '${apiAlias}/${cfg.name}';

interface Props {
  modelValue: boolean;
  row?: ${Model}Model | null;
}
const props = withDefaults(defineProps<Props>(), { row: null });

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  success: [];
}>();

const visible = ref(false);
const isEdit = ref(false);
const saving = ref(false);
const formRef = ref<FormInstance>();

const initialForm = () => ({
${initialKeys.join(',\n')}
});
const form = reactive<ReturnType<typeof initialForm>>(initialForm());

${rulesBlock}

watch(
  () => props.modelValue,
  (v) => {
    visible.value = v;
    if (v) initForm();
  }
);
watch(visible, (v) => emit('update:modelValue', v));

function initForm() {
  Object.assign(form, initialForm());
  isEdit.value = !!props.row;
  if (props.row) {
${assignKeys.join('\n')}
  }
}

async function onSubmit() {
  await formRef.value?.validate();
  saving.value = true;
  try {
    if (isEdit.value && props.row) {
      await ${apiName}Api.update(props.row.id, { ...form });
    } else {
      await ${apiName}Api.create({ ...form });
    }
    emit('success');
    visible.value = false;
  } finally {
    saving.value = false;
  }
}

function onClosed() {
  formRef.value?.resetFields();
  Object.assign(form, initialForm());
}
</script>
`;
}

function buildApiSource(cfg) {
  const Model = pascal(cfg.name);
  const apiName = camel(cfg.name);
  const iface = modelInterface(cfg);
  const deleteBlock =
    cfg.deleteMode === 'pathId'
      ? `  remove: (id: number) =>
    http.delete<void>(\`\${PREFIX}/\${id}\`, undefined, { requestOptions: { showSuccessMsg: true } })`
      : `  remove: (ids: number | number[]) =>
    http.delete<void>(\`\${PREFIX}\`, { ids: Array.isArray(ids) ? ids : [ids] }, {
      requestOptions: { showSuccessMsg: true }
    })`;

  return `import http from '@/utils/http';

const PREFIX = '${cfg.apiPrefix}';

${iface}

export const ${apiName}Api = {
  list: (params: API.PageQuery) => http.get<API.PageResult<${Model}Model>>(\`\${PREFIX}\`, params),
  detail: (id: number) => http.get<${Model}Model>(\`\${PREFIX}/\${id}\`),
  create: (data: Partial<${Model}Model>) =>
    http.post<${Model}Model>(\`\${PREFIX}\`, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<${Model}Model>) =>
    http.put<${Model}Model>(\`\${PREFIX}/\${id}\`, data, { requestOptions: { showSuccessMsg: true } }),
${deleteBlock}
};
`;
}

async function patchApiIndex(module, name) {
  if (module.startsWith('modules/')) return;
  const indexPath = path.join(ROOT, 'src/api/index.ts');
  let txt = await fs.readFile(indexPath, 'utf8');
  const line = `export * from './${module}/${name}';`;
  if (txt.includes(line)) return;
  await fs.writeFile(indexPath, txt.trimEnd() + '\n' + line + '\n', 'utf8');
}


function phpExport(value) {
  if (Array.isArray(value)) return `[${value.map((item) => phpExport(item)).join(', ')}]`;
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function backendControllerSource(cfg) {
  const backend = cfg.backend;
  const controller = backend.controller;
  const modelClass = backend.model;
  const modelName = modelClass.split('\\').pop();
  const writable = backend.writableFields;
  const required = backend.requiredFields || [];
  const search = backend.searchFields || {};
  const responseMap = backend.responseMap || {};
  const dataScope = backend.dataScope;
  const prefix = cfg.apiPrefix.replace(/^\//, '');
  const deleteByPathId = cfg.deleteMode === 'pathId';
  const deleteRule = deleteByPathId ? `${prefix}/:id` : prefix;
  const deletePatternAttribute = deleteByPathId ? "\n    #[Pattern('id', '\\\\d+')]" : '';
  const searchLines = [];
  for (const [param, rawFields] of Object.entries(search)) {
    const fields = Array.isArray(rawFields) ? rawFields : [rawFields];
    if (fields.length === 1) {
      searchLines.push(`        $${param} = trim((string) $this->request->get('${param}', ''));`);
      searchLines.push(`        if ($${param} !== '') {`);
      searchLines.push(`            $query->where('${fields[0]}', $${param});`);
      searchLines.push('        }');
    } else {
      searchLines.push(`        $${param} = trim((string) $this->request->get('${param}', ''));`);
      searchLines.push(`        if ($${param} !== '') {`);
      searchLines.push(`            $query->where(function ($where) use ($${param}) {`);
      fields.forEach((field, index) => {
        const method = index === 0 ? 'whereLike' : 'whereOr';
        const args = index === 0
          ? `'${field}', '%' . $${param} . '%'`
          : `'${field}', 'like', '%' . $${param} . '%'`;
        searchLines.push(`                $where->${method}(${args});`);
      });
      searchLines.push('            });');
      searchLines.push('        }');
    }
  }
  const scopeLine = dataScope
    ? `        $query = $this->applyDataScope($query, '${dataScope.adminField}', '${dataScope.departmentField}');\n`
    : '';
  const mapLines = Object.entries(responseMap).map(
    ([output, source]) => `        $row['${output}'] = $this->formatMappedValue($row['${source}'] ?? null);`
  );
  const unsetLines = [...new Set(Object.values(responseMap))]
    .filter((source) => !Object.keys(responseMap).includes(source))
    .map((source) => `        unset($row['${source}']);`);

  return String.raw`<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use ${modelClass};
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;

class ${controller} extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private const WRITABLE_FIELDS = ${phpExport(writable)};
    private const REQUIRED_FIELDS = ${phpExport(required)};

    #[Get('${prefix}')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = ${modelName}::order('id', 'desc');
${scopeLine}${searchLines.join('\n')}
        $result = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);
        return $this->ok([
            'list' => array_map(fn (${modelName} $model): array => $this->serialize($model), $result->items()),
            'total' => $result->total(),
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Get('${prefix}/:id')]
    #[Pattern('id', '\\d+')]
    public function detail(int $id): Response
    {
        $model = $this->findScoped($id);
        return $model ? $this->ok($this->serialize($model)) : $this->fail('记录不存在或无权访问', 404);
    }

    #[Post('${prefix}')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        $model = ${modelName}::create($data);
        return $this->ok($this->serialize($model), '创建成功');
    }

    #[Put('${prefix}/:id')]
    #[Pattern('id', '\\d+')]
    public function update(int $id): Response
    {
        $model = $this->findScoped($id);
        if (!$model) {
            return $this->fail('记录不存在或无权访问', 404);
        }
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        $model->save($data);
        return $this->ok($this->serialize($model), '保存成功');
    }

    #[Delete('${deleteRule}')]${deletePatternAttribute}
    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail('请选择要删除的记录', 422);
        }
        $query = ${modelName}::whereIn('id', $ids);
${dataScope ? `        $query = $this->applyDataScope($query, '${dataScope.adminField}', '${dataScope.departmentField}');\n` : ''}        $models = $query->select();
        if (count($models) !== count($ids)) {
            return $this->fail('包含不存在或无权操作的记录', 403);
        }
        foreach ($models as $model) {
            $model->delete();
        }
        return $this->ok(['removed' => count($models)], '删除成功');
    }

    private function findScoped(int $id): ?${modelName}
    {
        $query = ${modelName}::where('id', $id);
${dataScope ? `        $query = $this->applyDataScope($query, '${dataScope.adminField}', '${dataScope.departmentField}');\n` : ''}        return $query->find();
    }

    private function payload(): array
    {
        $input = $this->request->param();
        $data = [];
        foreach (self::WRITABLE_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
            }
        }
        return $data;
    }

    private function validatePayload(array $data): ?string
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '') {
                return '缺少必填字段：' . $field;
            }
        }
        return $data ? null : '没有可保存的字段';
    }

    private function serialize(${modelName} $model): array
    {
        $row = $model->toArray();
${mapLines.join('\n')}${mapLines.length ? '\n' : ''}${unsetLines.join('\n')}${unsetLines.length ? '\n' : ''}        return $row;
    }

    private function formatMappedValue($value)
    {
        if (is_numeric($value) && (int) $value > 1000000000) {
            return $this->formatTime($value);
        }
        return $value;
    }
}
`;
}

async function main() {
  const { dry, force, configPath } = readArgs(process.argv);
  if (!configPath) {
    console.error('请指定配置文件，例如: pnpm gen:crud -- scripts/crud.example.json');
    process.exit(1);
  }
  const abs = path.isAbsolute(configPath) ? configPath : path.join(ROOT, configPath);
  const cfg = JSON.parse(await fs.readFile(abs, 'utf8'));
  validate(cfg);

  const Model = pascal(cfg.name);
  const apiContent = buildApiSource(cfg);
  const indexContent = indexVue(cfg);
  const formContent = formDialogVue(cfg);
  const resolvedApiModule = apiModule(cfg);
  const resolvedViewModule = viewModule(cfg);

  const apiDir = resolvedApiModule.startsWith('modules/')
    ? path.join(ROOT, 'src', resolvedApiModule)
    : path.join(ROOT, 'src/api', resolvedApiModule);
  const viewDir = path.join(sourceRoot(resolvedViewModule), cfg.name);
  const compDir = path.join(viewDir, 'components');

  const targets = [
    { file: path.join(apiDir, `${cfg.name}.ts`), content: apiContent },
    { file: path.join(viewDir, 'index.vue'), content: indexContent },
    { file: path.join(compDir, `${Model}FormDialog.vue`), content: formContent }
  ];
  if (cfg.backend) {
    targets.push({
      file: path.join(ROOT, '..', 'app/backend/controller/system', `${cfg.backend.controller}.php`),
      content: backendControllerSource(cfg)
    });
  }

  if (cfg.backend) {
    const modelFile = path.join(ROOT, '..', `${cfg.backend.model.replace(/\\/g, '/')}.php`);
    try {
      await fs.access(modelFile);
    } catch {
      throw new Error(`backend.model 对应文件不存在: ${path.relative(path.join(ROOT, '..'), modelFile)}`);
    }
  }

  console.log('将生成文件:');
  for (const t of targets) console.log(' ', path.relative(ROOT, t.file));
  if (dry) {
    console.log('\n--dry 模式，未写入。');
    return;
  }
  if (!force) {
    const existing = [];
    for (const target of targets) {
      try {
        await fs.access(target.file);
        existing.push(path.relative(ROOT, target.file));
      } catch {
        // 文件不存在，可以安全生成。
      }
    }
    if (existing.length) {
      throw new Error(`目标文件已存在，拒绝覆盖：${existing.join(', ')}。确认后使用 --force。`);
    }
  }

  await fs.mkdir(apiDir, { recursive: true });
  await fs.mkdir(compDir, { recursive: true });
  for (const t of targets) {
    await fs.mkdir(path.dirname(t.file), { recursive: true });
    await fs.writeFile(t.file, t.content, 'utf8');
  }
  await patchApiIndex(resolvedApiModule, cfg.name);

  console.log(
    `\n完成。请在后台配置菜单：component ≈ ${resolvedViewModule}/${cfg.name}/index ，权限 ${cfg.permPrefix}:add | edit | delete`
  );
  if (cfg.backend) {
    console.log('\n控制器使用官方 Attribute 注册路由；权限资源与菜单种子必须单独审阅后写入 migration。');
  }
}

main().catch((e) => {
  console.error(e.message || e);
  process.exit(1);
});
