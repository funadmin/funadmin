<template>
  <el-dialog v-model="visible" :title="`设置配置值：${row?.code || ''}`" width="640px" destroy-on-close>
    <el-form label-width="92px">
      <el-form-item :label="row?.remark || '配置值'">
        <el-switch v-if="row?.type === 'switch'" v-model="switchValue" />
        <el-radio-group v-else-if="row?.type === 'radio'" v-model="scalarValue">
          <el-radio v-for="item in optionList" :key="item.value" :value="item.value">{{ item.label }}</el-radio>
        </el-radio-group>
        <el-checkbox-group v-else-if="row?.type === 'checkbox'" v-model="arrayValue">
          <el-checkbox v-for="item in optionList" :key="item.value" :value="item.value">{{ item.label }}</el-checkbox>
        </el-checkbox-group>
        <el-select v-else-if="row?.type === 'select'" v-model="scalarValue" class="w-full" filterable>
          <el-option v-for="item in optionList" :key="item.value" :label="item.label" :value="item.value" />
        </el-select>
        <el-color-picker v-else-if="row?.type === 'color'" v-model="scalarValue" show-alpha />
        <el-date-picker v-else-if="row?.type === 'datetime'" v-model="scalarValue" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="w-full" />
        <el-date-picker v-else-if="row?.type === 'range'" v-model="dateRange" type="datetimerange" value-format="YYYY-MM-DD HH:mm:ss" range-separator="至" start-placeholder="开始时间" end-placeholder="结束时间" class="w-full" />
        <Upload v-else-if="row?.type === 'image'" v-model="scalarValue" type="image" biz-type="image" />
        <Upload v-else-if="row?.type === 'images'" v-model="arrayValue" type="images" biz-type="image" />
        <Upload v-else-if="row?.type === 'file' || row?.type === 'files'" v-model="fileValue" type="file" biz-type="file" :multiple="row?.type === 'files'" :max-count="row?.type === 'file' ? 1 : 0" />
        <el-input-number v-else-if="row?.type === 'number'" v-model="numberValue" :precision="0" class="w-full" />
        <el-input-number v-else-if="row?.type === 'float' || row?.type === 'decimal'" v-model="numberValue" :precision="row?.type === 'decimal' ? 2 : undefined" class="w-full" />
        <el-input v-else-if="['textarea', 'array', 'editor'].includes(row?.type || '')" v-model="scalarValue" type="textarea" :rows="8" />
        <el-input v-else v-model="scalarValue" :type="row?.type === 'hidden' ? 'password' : 'text'" />
        <div v-if="row?.remark" class="mt-2 w-full text-xs text-[var(--el-text-color-secondary)]">{{ row.remark }}</div>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">保存配置值</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Upload from '@/components/Upload/index.vue';
import { configApi, type ConfigModel } from '@/api/system/config';
import type { UploadResult } from '@/api/common/upload';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: ConfigModel | null }>(), { row: null });
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'success'): void }>();
const visible = computed({ get: () => props.modelValue, set: (value) => emit('update:modelValue', value) });
const saving = ref(false);
const scalarValue = ref('');
const arrayValue = ref<string[]>([]);
const fileValue = ref<UploadResult[]>([]);
const switchValue = ref(false);
const numberValue = ref<number>();
const dateRange = ref<string[]>([]);
const optionList = computed(() => (props.row?.extra || '').split(/\r?\n/).map((line) => line.trim()).filter(Boolean).map((line) => {
  const index = line.indexOf(':');
  return index < 0 ? { value: line, label: line } : { value: line.slice(0, index).trim(), label: line.slice(index + 1).trim() };
}));
watch(() => [props.modelValue, props.row] as const, ([opened, row]) => {
  if (!opened || !row) return;
  scalarValue.value = row.value || '';
  arrayValue.value = row.value ? row.value.split(/\r?\n/).filter(Boolean) : [];
  switchValue.value = row.value === '1';
  numberValue.value = row.value === '' ? undefined : Number(row.value);
  dateRange.value = row.type === 'range' && row.value ? row.value.split(/\s+-\s+/, 2) : [];
  fileValue.value = row.value ? row.value.split(/\r?\n/).filter(Boolean).map((url) => ({ url, name: url.split('/').pop() || url, size: 0, ext: url.split('.').pop() || '', groupId: 1, reused: true, uploadedAt: 0 })) : [];
}, { immediate: true });
function submitValue(): string | string[] {
  const type = props.row?.type || 'text';
  if (type === 'switch') return switchValue.value ? '1' : '0';
  if (['checkbox', 'images'].includes(type)) return arrayValue.value;
  if (['file', 'files'].includes(type)) return fileValue.value.map((item) => item.url);
  if (type === 'range') return dateRange.value.join(' - ');
  if (['number', 'float', 'decimal'].includes(type)) return numberValue.value == null ? '' : String(numberValue.value);
  return scalarValue.value;
}
async function onSubmit() {
  if (!props.row) return;
  saving.value = true;
  try {
    await configApi.updateValue(props.row.id, submitValue());
    visible.value = false;
    emit('success');
  } finally { saving.value = false; }
}
</script>
