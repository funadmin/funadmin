<template>
  <section class="mb-4">
    <div class="flex flex-wrap gap-2 items-center"><strong>{{ title }}</strong><el-button size="small" @click="add">添加按钮</el-button><el-button size="small" @click="emit('update', [])">全部隐藏</el-button><el-button size="small" @click="emit('update', undefined)">恢复默认</el-button></div>
    <p class="text-xs text-[var(--el-text-color-secondary)]">{{ modelValue === undefined ? '继承宿主默认按钮' : modelValue.length ? '使用自定义按钮' : '空集合：全部隐藏' }}；显示配置不会授予权限或开启业务能力。</p>
    <div v-for="(button, index) in buttons" :key="button.id" class="flex gap-2 items-center my-2">
      <span>{{ button.label }} · {{ button.action.type === 'builtin' ? button.action.key : button.action.type }}</span>
      <el-button size="small" @click="edit(index)">配置</el-button><el-button size="small" :disabled="index === 0" @click="move(index, -1)">上移</el-button><el-button size="small" :disabled="index === buttons.length - 1" @click="move(index, 1)">下移</el-button><el-button size="small" type="danger" @click="remove(index)">移除</el-button>
    </div>
    <el-drawer v-model="visible" :title="`${title}配置`" size="min(560px, 96vw)" append-to-body>
      <el-form v-if="draft" label-width="100px">
        <el-alert v-if="unsupported" title="此动作尚未接通安全宿主适配，不可执行或发布。保留原配置供修改，不会假执行。" type="warning" :closable="false" />
        <el-form-item label="动作"><el-select :model-value="actionValue" @change="changeAction"><el-option v-for="key in keys" :key="key" :value="key" :label="listButtonLabels[key]" /><el-option value="refresh" label="刷新" /><el-option v-for="kind in ['registered', 'navigate', 'external', 'download', 'copy']" :key="kind" :value="kind" :label="`${kind}（尚未接通）`" disabled /></el-select></el-form-item>
        <el-form-item label="稳定 ID"><el-input :model-value="draft.id" disabled /></el-form-item>
        <el-form-item label="显示名称"><el-input v-model="draft.label" maxlength="100" /></el-form-item>
        <el-form-item label="图标"><el-select v-model="draft.icon" clearable><el-option v-for="icon in ['plus', 'edit', 'delete', 'view', 'refresh', 'download', 'upload', 'search']" :key="icon" :value="icon" :label="icon" /></el-select></el-form-item>
        <el-form-item label="颜色"><el-select v-model="draft.color"><el-option v-for="color in ['default', 'primary', 'success', 'warning', 'danger', 'info']" :key="color" :value="color" :label="color" /></el-select></el-form-item>
        <el-form-item label="尺寸"><el-select v-model="draft.size"><el-option v-for="size in ['small', 'default', 'large']" :key="size" :value="size" :label="size" /></el-select></el-form-item>
        <el-form-item label="提示 tips"><el-input v-model="draft.tips" maxlength="500" /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="draft.order" :min="-10000" :max="10000" :precision="0" /></el-form-item>
        <el-form-item label="显示位置"><el-radio-group v-model="draft.placement"><el-radio value="inline">直接显示</el-radio><el-radio value="more">更多菜单</el-radio></el-radio-group></el-form-item>
        <el-form-item label="隐藏"><el-switch v-model="draft.hidden" /></el-form-item><el-form-item label="禁用"><el-switch v-model="draft.disabled" /></el-form-item>
        <el-form-item label="禁用原因"><el-input v-model="draft.disabledReason" maxlength="500" /></el-form-item>
        <template v-for="condition in conditionKeys" :key="condition.key">
          <el-form-item :label="condition.label"><el-select :model-value="draft[condition.key]?.field" clearable placeholder="不限制" @change="field => setCondition(condition.key, field)"><el-option v-for="field in fields ?? []" :key="field" :value="field" :label="field" /></el-select></el-form-item>
          <template v-if="draft[condition.key]?.field"><el-form-item label="比较"><el-select v-model="draft[condition.key]!.op"><el-option v-for="op in ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'empty', 'notEmpty', 'contains', 'startsWith', 'endsWith']" :key="op" :value="op" :label="op" /></el-select></el-form-item><el-form-item label="条件值"><el-input :model-value="String(draft[condition.key]!.value ?? '')" @update:model-value="value => setConditionValue(condition.key, value)" placeholder="数字/布尔自动识别，其余为文本" /></el-form-item></template>
        </template>
        <el-form-item label="成功提示"><el-input :model-value="draft.success?.message" @update:model-value="value => draft!.success = { ...draft!.success, message: value }" /></el-form-item>
        <el-form-item label="成功刷新"><el-switch :model-value="draft.success?.refresh ?? false" @change="value => draft!.success = { ...draft!.success, refresh: Boolean(value) }" /></el-form-item>
        <el-form-item label="附加权限"><el-input v-model="draft.permission" placeholder="只收紧授权，不授予权限" /></el-form-item>
        <el-form-item label="交互"><el-select :model-value="draft.interaction?.type ?? 'none'" @change="changeInteraction"><el-option v-for="type in ['none', 'confirm', 'input', 'form']" :key="type" :value="type" :label="({ none: '无', confirm: '确认', input: '单项输入', form: '参数表单' })[type]" /></el-select></el-form-item>
        <template v-if="draft.interaction && draft.interaction.type !== 'none'">
          <el-form-item label="容器"><el-radio-group v-model="draft.interaction.presentation"><el-radio value="dialog">弹窗</el-radio><el-radio value="drawer">抽屉</el-radio></el-radio-group></el-form-item>
          <el-form-item label="标题"><el-input v-model="draft.interaction.title" /></el-form-item><el-form-item label="提示内容"><el-input v-model="draft.interaction.message" /></el-form-item>
          <div v-for="field in draft.interaction.fields ?? []" :key="field.name" class="border p-2 mb-2">
            <el-form-item label="参数名"><el-input v-model="field.name" /></el-form-item><el-form-item label="标签"><el-input v-model="field.label" /></el-form-item>
            <el-form-item label="控件"><el-select v-model="field.type"><el-option v-for="type in ['input', 'textarea', 'number', 'select', 'switch', 'date']" :key="type" :value="type" :label="type" /></el-select></el-form-item>
            <el-form-item v-if="field.type === 'select'" label="选项"><el-input type="textarea" :model-value="(field.options ?? []).map(option => `${option.label}=${option.value}`).join('\n')" @update:model-value="value => field.options = value.split('\n').filter(Boolean).map(line => { const [label, ...parts] = line.split('='); return { label, value: parts.join('=') || label }; })" placeholder="每行：标签=值" /></el-form-item>
            <el-form-item v-if="field.type === 'number'" label="最小/最大"><el-input-number v-model="field.min" /><el-input-number v-model="field.max" /></el-form-item>
            <el-form-item label="必填"><el-switch v-model="field.required" /></el-form-item>
            <el-form-item label="最大长度"><el-input-number v-model="field.maxLength" :min="1" :max="10000" /></el-form-item>
          </div>
          <el-button v-if="draft.interaction.type === 'form'" @click="addField">添加输入字段</el-button>
          <el-alert v-if="draft.interaction.fields?.length" title="内置 CRUD 使用原业务表单；这里的输入不会被当作业务写入参数。注册参数动作尚未开放。" :closable="false" />
        </template>
      </el-form>
      <template #footer><el-button @click="visible = false">取消</el-button><el-button type="primary" :disabled="!draft?.label.trim()" @click="save">应用</el-button></template>
    </el-drawer>
  </section>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { ElMessage } from 'element-plus';
import type { FormListButton, FormListButtonLocation, FormListBuiltinAction, FormListButtonInteraction } from '../../schema/types';
import { defaultListButtons, listButtonLabels, listButtonKeys } from '../../runtime/listButtonHost';
const props = defineProps<{ title: string; location: FormListButtonLocation; modelValue?: FormListButton[]; fields?: string[] }>();
const emit = defineEmits<{ update: [value: FormListButton[] | undefined] }>();
const buttons = computed(() => props.modelValue ?? defaultListButtons(props.location));
const keys = computed(() => listButtonKeys[props.location]);
const conditionKeys = [{ key: 'visibleWhen', label: '显示条件' }, { key: 'disabledWhen', label: '禁用条件' }] as const;
function setCondition(key: 'visibleWhen' | 'disabledWhen', field: string) { if (!draft.value) return; if (!field) delete draft.value[key]; else draft.value[key] = { field, op: 'eq', value: '' }; }
function setConditionValue(key: 'visibleWhen' | 'disabledWhen', value: string) { if (!draft.value?.[key]) return; let parsed: unknown = value; try { const candidate = JSON.parse(value); if (candidate === null || ['string', 'number', 'boolean'].includes(typeof candidate)) parsed = candidate; } catch {} draft.value[key]!.value = parsed; }
const visible = ref(false); const draft = ref<FormListButton>(); const editing = ref(-1);
const actionValue = computed(() => draft.value?.action.type === 'builtin' ? draft.value.action.key : draft.value?.action.type);
const unsupported = computed(() => !['builtin', 'refresh'].includes(draft.value?.action.type ?? ''));
const clone = (value: FormListButton[]) => JSON.parse(JSON.stringify(value)) as FormListButton[];
function edit(index: number) { editing.value = index; draft.value = clone(buttons.value)[index]; visible.value = true; }
function add() { editing.value = -1; draft.value = { id: `button-${crypto.randomUUID()}`, label: '新按钮', action: { type: 'refresh' } }; visible.value = true; }
function remove(index: number) { emit('update', clone(buttons.value).filter((_, i) => i !== index)); }
function move(index: number, offset: number) { const next = clone(buttons.value); const target = index + offset; [next[index], next[target]] = [next[target], next[index]]; next.forEach((button, i) => { button.order = i; }); emit('update', next); }
function changeAction(key: string) { if (draft.value) { draft.value.action = key === 'refresh' ? { type: 'refresh' } : { type: 'builtin', key: key as FormListBuiltinAction }; delete draft.value.params; } }
function changeInteraction(type: FormListButtonInteraction['type']) { if (draft.value) draft.value.interaction = { type, presentation: 'dialog', ...(['input', 'form'].includes(type) ? { fields: [{ name: 'reason', label: '原因', type: 'input', required: true }] } : {}) }; }
function addField() { draft.value?.interaction?.fields?.push({ name: `field${draft.value.interaction.fields.length + 1}`, label: '参数', type: 'input' }); }
function save() {
  if (!draft.value?.label.trim()) return;
  const inputs = draft.value.interaction?.fields ?? [];
  if (inputs.length > 20 || new Set(inputs.map(field => field.name)).size !== inputs.length || inputs.some(field => !/^[a-z][a-z0-9_.-]{0,63}$/.test(field.name) || field.name.split('.').some(part => ['constructor', 'prototype', '__proto__'].includes(part)) || !field.label.trim())) { ElMessage.error('输入字段标识不合法或重复'); return; }
  const button = clone([draft.value])[0];
  if (button.success && !button.success.message?.trim()) delete button.success.message;
  for (const key of ['icon', 'tips', 'disabledReason', 'permission'] as const) if (!button[key]?.trim()) delete button[key];
  if (button.interaction) for (const key of ['title', 'message'] as const) if (!button.interaction[key]?.trim()) delete button.interaction[key];
  const next = clone(buttons.value); if (editing.value < 0) next.push(button); else next[editing.value] = button;
  emit('update', next); visible.value = false;
}
</script>
