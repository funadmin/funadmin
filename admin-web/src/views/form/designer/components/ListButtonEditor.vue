<template>
  <section class="mb-4">
    <div class="flex flex-wrap gap-2 items-center"><strong>{{ title }}</strong><el-button size="small" @click="add">添加按钮</el-button><el-button size="small" @click="emit('update', [])">全部隐藏</el-button><el-button size="small" @click="emit('update', undefined)">恢复默认</el-button></div>
    <p class="text-xs text-[var(--el-text-color-secondary)]">{{ modelValue === undefined ? '继承宿主默认按钮' : modelValue.length ? '使用自定义按钮' : '空集合：全部隐藏' }}；显示配置不会授予权限或开启业务能力。</p>
    <div v-for="(button, index) in buttons" :key="button.id" class="flex gap-2 items-center my-2">
      <span>{{ button.label }} · {{ button.action.type === 'builtin' ? button.action.key : button.action.type }}</span>
      <el-button size="small" @click="edit(index)">配置</el-button><el-button size="small" :disabled="index === 0" @click="move(index, -1)">上移</el-button><el-button size="small" :disabled="index === buttons.length - 1" @click="move(index, 1)">下移</el-button><el-button size="small" type="danger" @click="remove(index)">移除</el-button>
    </div>
    <el-drawer v-model="visible" :title="`${title}配置`" size="min(560px, 96vw)" append-to-body>
      <el-form v-if="draft" label-width="100px" size="small" class="button-form">
        <el-alert v-if="unsupported" title="未注册、无权限或目录不可用：保留原配置，不代表可执行或可发布。" type="warning" :closable="false" />
        <el-alert v-if="catalogError" :title="catalogError" type="warning" :closable="false" />
        <el-form-item label="动作"><el-select aria-label="动作" :model-value="actionValue" @change="changeAction"><el-option v-for="option in actionOptions" :key="option.value" :value="option.value" :label="option.label" /><el-option v-if="unsupported && actionValue" :value="actionValue" :label="`${actionValue}（不可用，已保留）`" disabled /></el-select></el-form-item>
        <el-form-item v-if="'capabilityVersion' in draft.action" label="目录版本"><el-input :model-value="draft.action.capabilityVersion" readonly /></el-form-item>
        <p class="text-xs">{{ selectionNotice }}；数量约束仅收紧实际能力与权限，不授予批量能力。</p>
        <template v-if="location === 'toolbar'">
          <el-form-item label="最少选择"><el-input-number aria-label="最少选择数量" :model-value="draft.selection?.min" :min="0" :max="200" :precision="0" @update:model-value="value => setSelection('min', value)" /></el-form-item>
          <el-form-item label="最多选择"><el-input-number aria-label="最多选择数量" :model-value="draft.selection?.max" :min="0" :max="200" :precision="0" @update:model-value="value => setSelection('max', value)" /></el-form-item>
          <el-button @click="delete draft.selection">清除数量约束</el-button>
        </template>
        <el-alert v-if="metadata?.requiresConfirmation" title="注册动作要求服务端二次确认，不能关闭；参数表单之后仍会确认。" :closable="false" />
        <el-divider v-if="parameterNames.length">参数绑定（数据库字段名）</el-divider>
        <div v-for="name in parameterNames" :key="name" class="parameter-row">
          <strong>{{ name }} <small>{{ metadata?.parameterTypes[name] }}</small></strong>
          <el-select :aria-label="`参数 ${name} 来源`" :model-value="draft.params?.[name]?.source" placeholder="选择来源" @change="source => setBindingSource(name, source)">
            <el-option v-for="source in bindingSources" :key="source.value" :value="source.value" :label="source.label" :disabled="!sourceAllowed(source.value)" />
          </el-select>
          <template v-if="draft.params?.[name]?.source === 'literal'">
            <el-select :aria-label="`参数 ${name} 常量类型`" :model-value="literalType(name)" @change="type => setLiteralType(name, type)"><el-option v-for="type in ['string', 'number', 'boolean', 'null']" :key="type" :value="type" :label="type" /></el-select>
            <el-input :aria-label="`参数 ${name} 常量值`" :model-value="String((draft.params[name] as { value: unknown }).value ?? '')" @update:model-value="value => setLiteral(name, value)" />
          </template>
          <el-select v-else :aria-label="`参数 ${name} 字段`" :model-value="(draft.params?.[name] as { field?: string })?.field" filterable placeholder="选择字段" @change="field => setBindingField(name, field)"><el-option v-for="field in bindingFields(draft.params?.[name]?.source)" :key="field" :value="field" :label="field" /></el-select>
          <el-button v-if="!requiredParameters.includes(name)" @click="delete draft.params![name]">清除绑定</el-button>
        </div>
        <el-form-item label="稳定 ID"><el-input :model-value="draft.id" disabled /></el-form-item>
        <el-form-item label="显示名称"><el-input v-model="draft.label" maxlength="100" /></el-form-item>
        <el-form-item label="图标"><el-select v-model="draft.icon" clearable><el-option v-for="icon in ['plus', 'edit', 'delete', 'view', 'refresh', 'download', 'upload', 'search']" :key="icon" :value="icon" :label="icon" /></el-select></el-form-item>
        <el-form-item label="颜色"><el-select v-model="draft.color"><el-option v-for="color in ['default', 'primary', 'success', 'warning', 'danger', 'info']" :key="color" :value="color" :label="color" /></el-select></el-form-item>
        <el-form-item label="尺寸"><el-select v-model="draft.size"><el-option v-for="size in ['small', 'default', 'large']" :key="size" :value="size" :label="size" /></el-select></el-form-item>
        <el-form-item label="提示 tips"><el-input v-model="draft.tips" maxlength="500" /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="draft.order" :min="-10000" :max="10000" :precision="0" /></el-form-item>
        <el-form-item label="显示位置"><el-radio-group v-model="draft.placement"><el-radio value="inline">直接显示</el-radio><el-radio value="more">更多菜单</el-radio></el-radio-group></el-form-item>
        <el-form-item label="启用显示"><el-switch :model-value="!draft.hidden" aria-label="启用显示" @change="value => draft!.hidden = !value" /></el-form-item><el-form-item label="禁用"><el-switch v-model="draft.disabled" /></el-form-item>
        <el-form-item label="禁用原因"><el-input v-model="draft.disabledReason" maxlength="500" /></el-form-item>
        <el-form-item v-for="condition in conditionKeys" :key="condition.key" :label="condition.label">
          <ListButtonConditionEditor :label="condition.label" :model-value="draft[condition.key]" :fields="fields ?? []" @update="value => { if (value) draft![condition.key] = value; else delete draft![condition.key]; }" />
        </el-form-item>
        <el-form-item label="成功提示"><el-input :model-value="draft.success?.message" @update:model-value="value => draft!.success = { ...draft!.success, message: value }" /></el-form-item>
        <el-form-item label="成功刷新"><el-switch :model-value="draft.success?.refresh ?? false" @change="value => draft!.success = { ...draft!.success, refresh: Boolean(value) }" /></el-form-item>
        <el-form-item label="清空选择"><el-switch :model-value="draft.success?.clearSelection ?? false" @change="value => draft!.success = { ...draft!.success, clearSelection: Boolean(value) }" /></el-form-item>
        <el-form-item label="关闭容器"><el-switch :model-value="draft.success?.close ?? false" @change="value => draft!.success = { ...draft!.success, close: Boolean(value) }" /></el-form-item>
        <el-form-item label="附加权限"><el-input v-model="draft.permission" :readonly="Boolean(metadata || resourceMetadata)" placeholder="只收紧授权，不授予权限" /></el-form-item>
        <el-form-item label="交互"><el-select aria-label="交互" :model-value="draft.interaction?.type ?? 'none'" @change="changeInteraction"><el-option v-for="type in ['none', 'confirm', 'input', 'form']" :key="type" :value="type" :label="({ none: '无', confirm: '确认', input: '单项输入', form: '参数表单' })[type]" /></el-select></el-form-item>
        <template v-if="draft.interaction && draft.interaction.type !== 'none'">
          <el-form-item label="容器"><el-radio-group v-model="draft.interaction.presentation"><el-radio value="dialog">弹窗</el-radio><el-radio value="drawer">抽屉</el-radio></el-radio-group></el-form-item>
          <el-form-item label="标题"><el-input v-model="draft.interaction.title" /></el-form-item><el-form-item label="提示内容"><el-input v-model="draft.interaction.message" /></el-form-item>
          <div v-for="(field, fieldIndex) in draft.interaction.fields ?? []" :key="fieldIndex" class="border p-2 mb-2">
            <el-form-item label="参数名"><el-input :model-value="field.name" :aria-label="`输入字段 ${fieldIndex + 1} 名称`" @update:model-value="value => renameField(fieldIndex, value)" /></el-form-item><el-form-item label="标签"><el-input v-model="field.label" /></el-form-item>
            <el-form-item label="控件"><el-select v-model="field.type"><el-option v-for="type in ['input', 'textarea', 'number', 'select', 'switch', 'date']" :key="type" :value="type" :label="type" /></el-select></el-form-item>
            <el-form-item v-if="field.type === 'select'" label="选项"><el-input type="textarea" :model-value="(field.options ?? []).map(option => `${option.label}=${option.value}`).join('\n')" @update:model-value="value => field.options = value.split('\n').filter(Boolean).map(line => { const [label, ...parts] = line.split('='); return { label, value: parts.join('=') || label }; })" placeholder="每行：标签=值" /></el-form-item>
            <el-form-item v-if="field.type === 'number'" label="最小/最大"><el-input-number v-model="field.min" /><el-input-number v-model="field.max" /></el-form-item>
            <el-button v-if="draft.interaction.type === 'form'" @click="removeField(fieldIndex)">移除输入字段</el-button>
            <el-form-item label="必填"><el-switch v-model="field.required" /></el-form-item>
            <el-form-item label="最大长度"><el-input-number v-model="field.maxLength" :min="1" :max="10000" /></el-form-item>
          </div>
          <el-button v-if="draft.interaction.type === 'form'" @click="addField">添加输入字段</el-button>
          <el-alert v-if="draft.interaction.fields?.length && draft.action.type === 'builtin'" title="内置 CRUD 使用原业务表单；这里的输入不会被当作业务写入参数。" :closable="false" />
        </template>
      </el-form>
      <template #footer><el-button size="small" @click="preview">模拟预览</el-button><el-button @click="visible = false">取消</el-button><el-button type="primary" :disabled="!draft?.label.trim()" @click="save">应用</el-button></template>
    </el-drawer>
    <ListButtonInteraction ref="previewInteraction" />
  </section>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import ListButtonConditionEditor from './ListButtonConditionEditor.vue';
import ListButtonInteraction from '../../components/ListButtonInteraction.vue';
import { ElMessage } from 'element-plus';
import type { FormListButton, FormListButtonLocation, FormListBuiltinAction, FormListButtonInteraction, FormListParameterBinding, FormListActionMetadata, FormSchemaCondition } from '../../schema/types';
import type { ListResource } from '../../runtime/listResourceHost';
import { resolveListButtons } from '../../schema/listButtons';
import { defaultListButtons, listButtonLabels, listButtonKeys } from '../../runtime/listButtonHost';
const props = withDefaults(defineProps<{ title: string; location: FormListButtonLocation; modelValue?: FormListButton[]; fields?: string[]; filterFields?: string[]; categoryFields?: string[]; resources?: Record<string, ListResource>; actions?: Record<string, FormListActionMetadata>; catalogError?: string; builtinKeys?: FormListBuiltinAction[]; resourceEnabled?: boolean }>(), { resourceEnabled: true });
const emit = defineEmits<{ update: [value: FormListButton[] | undefined] }>();
const buttons = computed(() => props.modelValue ?? defaultListButtons(props.location));
const keys = computed(() => props.builtinKeys ?? listButtonKeys[props.location]);
const actionOptions = computed(() => [
  ...keys.value.map(key => ({ value: key, label: listButtonLabels[key] })), { value: 'refresh', label: '刷新' },
  ...(props.location === 'row' && keys.value.includes('edit') ? [{ value: 'form', label: '当前业务编辑表单' }] : []),
  ...(props.resourceEnabled === false ? [] : [
    ...(['row', 'categoryNode'].includes(props.location) ? [{ value: 'copy', label: '复制字段文本' }] : []),
    ...(props.location === 'toolbar' && keys.value.includes('export') ? [{ value: 'download', label: '授权导出下载' }] : []),
    ...Object.entries(props.resources ?? {}).map(([key, item]) => ({ value: `${item.type}:${key}`, label: `${item.type === 'navigate' ? '站内导航' : '受控外链'} · ${key}` })),
    ...Object.entries(props.actions ?? {}).filter(([, item]) => item.locations.includes(props.location) && item.targets.includes({ row: 'record', toolbar: 'selection', categoryNode: 'category', categoryToolbar: 'none' }[props.location]) && (props.location !== 'toolbar' || item.batch) && item.parameters && item.parameterTypes && item.resultContract === 'json').map(([key]) => ({ value: `registered:${key}`, label: `注册动作 · ${key}` }))
  ])
]);
const selectionNotice = computed(() => ({ row: '当前行单条记录', toolbar: draft.value?.action.type === 'registered' ? '当前页明确勾选 1–200 条，且动作必须支持批量' : '仅当前页明确选择，不隐式选择所有筛选记录', categoryNode: '当前分类记录，不使用右表勾选记录', categoryToolbar: '分类顶部无记录上下文' }[props.location]));
const conditionKeys = [{ key: 'visibleWhen', label: '显示条件' }, { key: 'disabledWhen', label: '禁用条件' }] as const;
function setCondition(key: 'visibleWhen' | 'disabledWhen', field: string) { if (!draft.value) return; if (!field) delete draft.value[key]; else draft.value[key] = { field, op: 'eq', value: '' }; }
function setConditionValue(key: 'visibleWhen' | 'disabledWhen', value: string) { if (!draft.value?.[key]) return; let parsed: unknown = value; try { const candidate = JSON.parse(value); if (candidate === null || ['string', 'number', 'boolean'].includes(typeof candidate)) parsed = candidate; } catch {} draft.value[key]!.value = parsed; }
const visible = ref(false); const draft = ref<FormListButton>(); const editing = ref(-1);
const actionKey = (button: FormListButton) => button.action.type === 'builtin' ? button.action.key : ['registered', 'navigate', 'external'].includes(button.action.type) && 'key' in button.action ? `${button.action.type}:${button.action.key}` : button.action.type;
const actionValue = computed(() => draft.value ? actionKey(draft.value) : '');
const metadata = computed(() => draft.value?.action.type === 'registered' ? props.actions?.[draft.value.action.key] : undefined);
const resourceMetadata = computed(() => draft.value && ['navigate', 'external'].includes(draft.value.action.type) && 'key' in draft.value.action ? props.resources?.[draft.value.action.key] : undefined);
const unsupported = computed(() => !actionOptions.value.some(item => item.value === actionValue.value) || Boolean(draft.value && 'capabilityVersion' in draft.value.action && draft.value.action.capabilityVersion !== (metadata.value ?? resourceMetadata.value)?.capabilityVersion));
const requiredParameters = computed(() => metadata.value?.parameters ?? resourceMetadata.value?.params ?? (draft.value?.action.type === 'copy' ? ['text'] : []));
const parameterNames = computed(() => [...new Set([...requiredParameters.value, ...(resourceMetadata.value?.query ?? []), ...Object.keys(draft.value?.params ?? {})])]);
const bindingSources = [{ value: 'row', label: '当前记录' }, { value: 'selection', label: '当前页选择 ID' }, { value: 'filter', label: '当前筛选' }, { value: 'category', label: '当前分类' }, { value: 'form', label: '交互表单' }, { value: 'literal', label: '常量' }] as const;
function sourceAllowed(source: string) {
  if (draft.value?.action.type === 'copy') return source === (props.location === 'row' ? 'row' : 'category');
  if (source === 'row') return props.location === 'row';
  if (source === 'selection') return props.location === 'toolbar' && !resourceMetadata.value;
  if (source === 'category') return Boolean(props.categoryFields?.length) && props.location !== 'categoryToolbar';
  return true;
}
function bindingFields(source?: string): string[] { return source === 'selection' ? ['ids'] : source === 'form' ? (draft.value?.interaction?.fields ?? []).map(field => field.name) : source === 'category' ? props.categoryFields ?? [] : source === 'filter' ? props.filterFields ?? props.fields ?? [] : props.fields ?? []; }
function setBindingSource(name: string, source: FormListParameterBinding['source']) {
  if (!draft.value || !sourceAllowed(source)) return;
  draft.value.params ??= {};
  draft.value.params[name] = source === 'literal' ? { source, value: '' } : source === 'selection' ? { source, field: 'ids' } : { source, field: '' };
}
function setBindingField(name: string, field: string) { const binding = draft.value?.params?.[name]; if (binding && binding.source !== 'literal') binding.field = field; }
function literalType(name: string) { const binding = draft.value?.params?.[name]; return binding?.source === 'literal' ? binding.value === null ? 'null' : typeof binding.value : 'string'; }
function setLiteralType(name: string, type: string) { if (draft.value?.params) draft.value.params[name] = { source: 'literal', value: type === 'null' ? null : type === 'number' ? 0 : type === 'boolean' ? false : '' }; }
function setLiteral(name: string, value: string) { const binding = draft.value?.params?.[name]; if (binding?.source !== 'literal') return; const type = literalType(name); binding.value = type === 'number' ? Number(value) : type === 'boolean' ? value === 'true' : type === 'null' ? null : value; }
const previewInteraction = ref<InstanceType<typeof ListButtonInteraction>>();
async function preview() {
  if (!draft.value) return;
  const button = clone([draft.value])[0];
  button.interaction = { ...button.interaction, type: button.interaction?.type ?? 'none', title: `模拟交互：${button.label}` };
  const result = await previewInteraction.value?.open(button, {});
  if (result !== null) ElMessage.info('预览仅模拟，不执行请求、写入、导航、复制或下载');
}
const actionDrafts = new Map<string, Pick<FormListButton, 'action' | 'params' | 'permission' | 'interaction'>>();
const clone = (value: FormListButton[]) => JSON.parse(JSON.stringify(value)) as FormListButton[];
function edit(index: number) { actionDrafts.clear(); interactionDrafts.clear(); editing.value = index; draft.value = clone(buttons.value)[index]; visible.value = true; }
function add() { actionDrafts.clear(); interactionDrafts.clear(); editing.value = -1; draft.value = { id: `button-${crypto.randomUUID()}`, label: '新按钮', action: { type: 'refresh' } }; visible.value = true; }
function remove(index: number) { emit('update', clone(buttons.value).filter((_, i) => i !== index)); }
function move(index: number, offset: number) { const next = clone(buttons.value); const target = index + offset; [next[index], next[target]] = [next[target], next[index]]; next.forEach((button, i) => { button.order = i; }); emit('update', next); }
function changeAction(key: string) {
  const button = draft.value; if (!button || !actionOptions.value.some(item => item.value === key)) return;
  actionDrafts.set(actionKey(button), JSON.parse(JSON.stringify({ action: button.action, params: button.params, permission: button.permission, interaction: button.interaction })));
  delete button.params; delete button.permission; delete button.interaction;
  const saved = actionDrafts.get(key); if (saved) { Object.assign(button, JSON.parse(JSON.stringify(saved))); return; }
  const [type, name] = key.split(':');
  if (type === 'registered' || type === 'navigate' || type === 'external') {
    const item = type === 'registered' ? props.actions?.[name] : props.resources?.[name]; if (!item) return;
    button.action = { type, key: name, capabilityVersion: item.capabilityVersion }; button.permission = item.permission;
    const names = type === 'registered' ? (item as FormListActionMetadata).parameters : (item as ListResource).params;
    button.params = Object.fromEntries(names.map(name => [name, { source: 'literal', value: '' }]));
    if (type === 'registered') {
      const meta = item as FormListActionMetadata;
      button.interaction = { type: meta.parameters.length ? 'form' : meta.requiresConfirmation ? 'confirm' : 'none', presentation: 'dialog', ...(meta.parameters.length ? { fields: meta.parameters.filter(name => meta.parameterTypes[name] !== 'ids').map(name => ({ name, label: name, required: true, type: meta.parameterTypes[name] === 'boolean' ? 'switch' as const : ['integer', 'number'].includes(meta.parameterTypes[name]) ? 'number' as const : 'input' as const })) } : {}) };
      button.params = Object.fromEntries(meta.parameters.map(name => [name, meta.parameterTypes[name] === 'ids' && props.location === 'toolbar' ? { source: 'selection', field: 'ids' } : { source: 'form', field: name }]));
    }
  } else if (key === 'download') button.action = { type: 'download', key: 'export' };
  else if (key === 'copy') { button.action = { type: 'copy' }; button.params = { text: { source: props.location === 'row' ? 'row' : 'category', field: '' } }; }
  else button.action = ['refresh', 'form'].includes(key) ? { type: key as 'refresh' | 'form' } : { type: 'builtin', key: key as FormListBuiltinAction };
}
const interactionDrafts = new Map<string, FormListButtonInteraction>();
function changeInteraction(type: FormListButtonInteraction['type']) {
  if (!draft.value) return;
  const current = draft.value.interaction;
  if (current) interactionDrafts.set(`${actionValue.value}:${current.type}`, JSON.parse(JSON.stringify(current)));
  const saved = interactionDrafts.get(`${actionValue.value}:${type}`);
  draft.value.interaction = saved ? JSON.parse(JSON.stringify(saved)) : { type, presentation: current?.presentation ?? 'dialog', ...(['input', 'form'].includes(type) ? { fields: [{ name: 'reason', label: '原因', type: 'input', required: true }] } : {}) };
}
function addField() { const fields = draft.value?.interaction?.fields; if (!fields || fields.length >= 20) return; let index = 1; while (fields.some(field => field.name === `field${index}`)) index++; fields.push({ name: `field${index}`, label: '参数', type: 'input' }); }
function renameField(index: number, name: string) { const field = draft.value?.interaction?.fields?.[index]; if (!field) return; for (const binding of Object.values(draft.value?.params ?? {})) if (binding.source === 'form' && binding.field === field.name) binding.field = name; field.name = name; }
function removeField(index: number) { const field = draft.value?.interaction?.fields?.[index]; if (!field) return; if (Object.values(draft.value?.params ?? {}).some(binding => binding.source === 'form' && binding.field === field.name)) { ElMessage.warning('请先修改引用此字段的参数绑定'); return; } draft.value?.interaction?.fields?.splice(index, 1); }
function setSelection(key: 'min' | 'max', value: number | undefined) {
  if (!draft.value) return;
  const selection = { ...draft.value.selection };
  if (value === undefined || value === null) delete selection[key]; else selection[key] = value;
  if (Object.keys(selection).length) draft.value.selection = selection; else delete draft.value.selection;
}
function save() {
  if (!draft.value?.label.trim()) return;
  try { resolveListButtons({ buttons: { [props.location]: [draft.value] } }, props.location, []); }
  catch { ElMessage.error('选择数量必须为 0–200 的整数，且最少选择不能超过最多选择'); return; }
  let budget = 100;
  const validCondition = (condition: FormSchemaCondition, depth = 0): boolean => {
    if (--budget < 0 || depth > 8) return false;
    if (['and', 'or'].includes(condition.op)) return Boolean(condition.conditions?.length && condition.conditions.length <= 20 && condition.conditions.every(child => validCondition(child, depth + 1)));
    if (condition.op === 'not') return Boolean(condition.condition && validCondition(condition.condition, depth + 1));
    return Boolean(condition.field && props.fields?.includes(condition.field));
  };
  if ([draft.value.visibleWhen, draft.value.disabledWhen].some(condition => condition && !validCondition(condition))) { ElMessage.error('条件组不能为空，且必须引用可读字段'); return; }
  const inputs = draft.value.interaction?.fields ?? [];
  if (inputs.length > 20 || new Set(inputs.map(field => field.name)).size !== inputs.length || inputs.some(field => !/^[a-z][a-z0-9_.-]{0,63}$/.test(field.name) || field.name.split('.').some(part => ['constructor', 'prototype', '__proto__'].includes(part)) || !field.label.trim())) { ElMessage.error('输入字段标识不合法或重复'); return; }
  if (!unsupported.value) {
      if (requiredParameters.value.some(name => !draft.value?.params?.[name])) { ElMessage.error('请配置必需参数'); return; }
      for (const [name, binding] of Object.entries(draft.value.params ?? {})) {
        if (!sourceAllowed(binding.source) || (binding.source !== 'literal' && !bindingFields(binding.source).includes(binding.field)) || (binding.source === 'literal' && typeof binding.value === 'number' && !Number.isFinite(binding.value))) { ElMessage.error(`参数 ${name} 的来源或字段无效`); return; }
      }
    }
    const button = clone([draft.value])[0];
  if (button.success && !button.success.message?.trim()) delete button.success.message;
  for (const key of ['icon', 'tips', 'disabledReason', 'permission'] as const) if (!button[key]?.trim()) delete button[key];
  if (button.interaction) for (const key of ['title', 'message'] as const) if (!button.interaction[key]?.trim()) delete button.interaction[key];
  const next = clone(buttons.value); if (editing.value < 0) next.push(button); else next[editing.value] = button;
  emit('update', next); visible.value = false;
}
</script>
<style scoped>
.button-form :deep(.el-form-item) { margin-bottom: 12px; }
.button-form :deep(.el-select) { width: 100%; }
.parameter-row { display: grid; gap: 8px; padding: 10px; margin-bottom: 10px; border: 1px solid var(--el-border-color); border-radius: 4px; }
@media (max-width: 600px) { .button-form :deep(.el-form-item) { display: block; } .button-form :deep(.el-form-item__content) { margin-left: 0 !important; } }
</style>
