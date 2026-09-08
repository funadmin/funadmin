<template>
  <el-switch v-if="type === 'switch'" :model-value="modelValue === '1'" @update:model-value="emitValue($event ? '1' : '0')" />
  <el-radio-group v-else-if="type === 'radio'" :model-value="modelValue" @update:model-value="emitValue(String($event))">
    <el-radio v-for="item in optionList" :key="item.value" :value="item.value">{{ item.label }}</el-radio>
  </el-radio-group>
  <el-checkbox-group v-else-if="type === 'checkbox'" :model-value="arrayValue" @update:model-value="emitArray($event as Array<string | number>)">
    <el-checkbox v-for="item in optionList" :key="item.value" :value="item.value">{{ item.label }}</el-checkbox>
  </el-checkbox-group>
  <el-select v-else-if="type === 'select'" :model-value="modelValue" class="w-full" filterable clearable @update:model-value="emitValue(String($event ?? ''))">
    <el-option v-for="item in optionList" :key="item.value" :label="item.label" :value="item.value" />
  </el-select>
  <el-select v-else-if="type === 'tags'" :model-value="arrayValue" multiple filterable allow-create default-first-option class="w-full" @update:model-value="emitArray($event as Array<string | number>)" />
  <el-input v-else-if="type === 'array'" :model-value="modelValue" type="textarea" :rows="5" placeholder="每行一个值" @update:model-value="emitValue" />
  <el-input v-else-if="type === 'json'" :model-value="modelValue" type="textarea" :rows="8" placeholder='例如 {"key":"value"}' @update:model-value="emitValue" />
  <el-input v-else-if="type === 'textarea' || type === 'editor'" :model-value="modelValue" type="textarea" :rows="8" @update:model-value="emitValue" />
  <el-input v-else-if="type === 'hidden'" :model-value="modelValue" type="password" show-password @update:model-value="emitValue" />
  <el-color-picker v-else-if="type === 'color'" :model-value="modelValue" show-alpha @update:model-value="emitValue(String($event ?? ''))" />
  <el-date-picker v-else-if="type === 'date'" :model-value="modelValue" type="date" value-format="YYYY-MM-DD" class="w-full" @update:model-value="emitValue(String($event ?? ''))" />
  <el-date-picker v-else-if="type === 'datetime'" :model-value="modelValue" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="w-full" @update:model-value="emitValue(String($event ?? ''))" />
  <el-date-picker v-else-if="type === 'range'" :model-value="rangeValue" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss" range-separator="至" start-placeholder="开始时间" end-placeholder="结束时间" class="w-full" @update:model-value="emitRange" />
  <Upload v-else-if="type === 'image'" :model-value="modelValue" type="image" biz-type="image" @update:model-value="emitValue(String($event ?? ''))" />
  <Upload v-else-if="type === 'images'" :model-value="arrayValue" type="images" biz-type="image" @update:model-value="emitUploadValues" />
  <Upload v-else-if="type === 'file' || type === 'files'" :model-value="fileValue" type="file" biz-type="file" :multiple="type === 'files'" :max-count="type === 'file' ? 1 : 0" @update:model-value="emitUploadValues" />
  <el-input-number v-else-if="type === 'number'" :model-value="numberValue" :precision="0" class="w-full" @update:model-value="emitNumber" />
  <el-input-number v-else-if="type === 'float' || type === 'decimal'" :model-value="numberValue" :precision="type === 'decimal' ? 2 : undefined" class="w-full" @update:model-value="emitNumber" />
  <el-input v-else :model-value="modelValue" @update:model-value="emitValue" />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import Upload from '@/components/Upload/index.vue';
import type { UploadResult } from '@/api/common/upload';

const props = withDefaults(defineProps<{ modelValue: string; type?: string; extra?: string }>(), { type: 'text', extra: '' });
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const arrayTypes = ['checkbox', 'array', 'tags', 'images', 'files'];
const arrayValue = computed(() => props.modelValue ? props.modelValue.split(/\r?\n|,/).map((item) => item.trim()).filter(Boolean) : []);
const rangeValue = computed(() => props.modelValue ? props.modelValue.split(/\s+-\s+/, 2) : []);
const numberValue = computed(() => props.modelValue === '' ? undefined : Number(props.modelValue));
const optionList = computed(() => props.extra.split(/\r?\n/).map((line) => line.trim()).filter(Boolean).map((line) => {
  const index = line.indexOf(':');
  return index < 0 ? { value: line, label: line } : { value: line.slice(0, index).trim(), label: line.slice(index + 1).trim() };
}));
const fileValue = computed<UploadResult[]>(() => arrayValue.value.map((url) => ({
  url, name: url.split('/').pop() || url, size: 0, ext: url.split('.').pop() || '', groupId: 1, driver: 'local', reused: true, uploadedAt: 0
})));
const emitValue = (value: string) => emit('update:modelValue', value);
const emitArray = (value: Array<string | number>) => emitValue(value.map(String).map((item) => item.trim()).filter(Boolean).join('\n'));
const emitRange = (value: string[] | null) => emitValue((value || []).join(' - '));
const emitNumber = (value: number | undefined) => emitValue(value == null ? '' : String(value));
const emitUploadValues = (value: string | string[] | UploadResult[]) => {
  const urls = Array.isArray(value) ? value.map((item) => typeof item === 'string' ? item : item.url) : [value];
  emitValue((arrayTypes.includes(props.type) ? urls : urls.slice(0, 1)).filter(Boolean).join('\n'));
};
</script>
