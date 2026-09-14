<template>
  <div class="condition-editor condition-grid">
    <el-select :aria-label="`${label}规则`" :model-value="mode" @change="changeMode">
      <el-option value="none" label="不限制" /><el-option value="field" label="字段比较" />
      <el-option value="and" label="全部满足" /><el-option value="or" label="任一满足" /><el-option value="not" label="取反" />
    </el-select>
    <template v-if="modelValue && mode === 'field'">
      <el-select aria-label="条件字段" :model-value="modelValue.field" filterable @change="field => patch({ field })"><el-option v-for="field in fields" :key="field" :value="field" :label="field" /></el-select>
      <el-select aria-label="条件运算" :model-value="modelValue.op" @change="op => patch({ op, value: ['in', 'notIn'].includes(op) ? [] : '' })"><el-option v-for="op in operators" :key="op" :value="op" :label="op" /></el-select>
      <template v-if="['in', 'notIn'].includes(modelValue.op)">
        <div v-for="(value, index) in values" :key="index" class="value-row"><el-input :model-value="String(value ?? '')" @update:model-value="value => setValue(index, value)" /><el-button @click="removeValue(index)">移除值</el-button></div>
        <el-button :disabled="values.length >= 100" @click="patch({ value: [...values, ''] })">添加值</el-button>
      </template>
      <el-input v-else-if="!['empty', 'notEmpty'].includes(modelValue.op)" aria-label="条件值" :model-value="String(modelValue.value ?? '')" placeholder="数字、布尔、null 自动识别" @update:model-value="value => patch({ value: scalar(value) })" />
    </template>
    <template v-if="modelValue && ['and', 'or'].includes(mode)">
      <div v-for="(child, index) in modelValue.conditions ?? []" :key="index">
        <ListButtonConditionEditor :model-value="child" :fields="fields" :depth="depth + 1" @update="value => updateChild(index, value)" />
        <el-button @click="updateChild(index, undefined)">移除条件</el-button>
      </div>
      <el-button :disabled="depth >= 7 || (modelValue.conditions?.length ?? 0) >= 20" @click="patch({ conditions: [...(modelValue.conditions ?? []), { op: 'eq', field: fields[0] ?? '', value: '' }] })">添加条件</el-button>
    </template>
    <ListButtonConditionEditor v-if="modelValue && mode === 'not' && depth < 8" :model-value="modelValue.condition" :fields="fields" :depth="depth + 1" @update="condition => patch({ condition })" />
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import type { FormSchemaCondition } from '../../schema/types';
const props = withDefaults(defineProps<{ modelValue?: FormSchemaCondition; fields: string[]; depth?: number; label?: string }>(), { depth: 0, label: '条件' });
const emit = defineEmits<{ update: [value: FormSchemaCondition | undefined] }>();
const operators = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'notIn', 'empty', 'notEmpty', 'contains', 'startsWith', 'endsWith'];
const mode = computed(() => !props.modelValue ? 'none' : ['and', 'or', 'not'].includes(props.modelValue.op) ? props.modelValue.op : 'field');
const values = computed<unknown[]>(() => Array.isArray(props.modelValue?.value) ? props.modelValue.value : []);
const patch = (value: Partial<FormSchemaCondition>) => emit('update', { op: 'eq', ...props.modelValue, ...value });
function changeMode(mode: string) { emit('update', mode === 'none' ? undefined : mode === 'field' ? { op: 'eq', field: props.fields[0] ?? '', value: '' } : mode === 'not' ? { op: 'not', condition: { op: 'eq', field: props.fields[0] ?? '', value: '' } } : { op: mode, conditions: [] }); }
function scalar(value: string): unknown { try { const parsed = JSON.parse(value); if (parsed === null || ['string', 'number', 'boolean'].includes(typeof parsed)) return parsed; } catch {} return value; }
function setValue(index: number, value: string) { const next = [...values.value]; next[index] = scalar(value); patch({ value: next }); }
function removeValue(index: number) { patch({ value: values.value.filter((_, i) => i !== index) }); }
function updateChild(index: number, value?: FormSchemaCondition) { const conditions = [...(props.modelValue?.conditions ?? [])]; if (value) conditions[index] = value; else conditions.splice(index, 1); patch({ conditions }); }
</script>
<style scoped>
.condition-editor { display: grid; gap: 8px; padding: 8px; border-left: 2px solid var(--el-border-color); min-width: 0; }
.value-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; }
.condition-grid :deep(.el-input), .condition-grid :deep(.el-select) { min-width: 0; width: 100%; }
@media (max-width: 600px) { .condition-editor { padding: 6px; } }
</style>