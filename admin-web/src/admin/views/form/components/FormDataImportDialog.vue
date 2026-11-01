<template>
  <el-dialog :model-value="modelValue" :title="t('formData.importTitle', '导入 CSV')" width="min(760px, 96vw)" destroy-on-close @update:model-value="value => emit('update:modelValue', value)">
    <el-form label-width="90px">
      <el-form-item :label="t('formData.csvFile', 'CSV 文件')">
        <input type="file" accept=".csv,text/csv" :aria-label="t('formData.csvFileAria', '选择 CSV 文件')" @change="onFile" />
      </el-form-item>
      <el-form-item :label="t('formData.hasHeader', '首行表头')">
        <el-switch v-model="hasHeader" @change="reparse" />
        <span class="ml-2 text-xs text-[var(--el-text-color-secondary)]">{{ t('formData.headerHint', '开启时首行按字段名或标签映射列；关闭时按可写字段顺序映射。') }}</span>
      </el-form-item>
    </el-form>
    <el-alert v-if="parseError" :title="parseError" type="error" :closable="false" class="mb-3" />
    <template v-if="rows.length">
      <p class="mb-2 text-sm">{{ t('formData.parsedRows', { n: rows.length }, '已解析 {n} 行（上限 1000 行）；未映射列将被忽略。') }}</p>
      <el-table :data="rows.slice(0, 5)" size="small" max-height="240" border>
        <el-table-column v-for="name in mappedNames" :key="name" :prop="name" :label="fieldLabel(name)" min-width="140" show-overflow-tooltip />
      </el-table>
    </template>
    <template #footer>
      <el-button @click="emit('update:modelValue', false)">{{ t('common.cancel', '取消') }}</el-button>
      <el-button type="primary" :disabled="!rows.length || Boolean(parseError)" @click="submit">{{ t('common.import', '导入') }}</el-button>
    </template>
  </el-dialog>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormFieldDef } from '@/api/form';
import { parseCsv } from '../runtime/csvParse';

const { t } = useI18n();
const props = defineProps<{ modelValue: boolean; fields: FormFieldDef[] }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; submit: [rows: Array<Record<string, unknown>>] }>();

const hasHeader = ref(true);
const parseError = ref('');
const raw = ref<string[][]>([]);
const rows = computed(() => buildRows());

const writableFields = computed(() => props.fields.filter(field => field.column_type && field.type !== 'password' && !field.control_props?.sensitive && !field.control_props?.writeOnly));
const fieldLabel = (name: string) => props.fields.find(field => field.field_name === name)?.label || name;

function headerMap(header: string[]): Array<string | null> {
  return header.map(cell => {
    const key = cell.trim();
    const hit = props.fields.find(field => field.field_name === key) ?? props.fields.find(field => field.label?.trim() === key);
    return hit && writableFields.value.some(field => field.field_name === hit.field_name) ? hit.field_name : null;
  });
}
function buildRows(): Array<Record<string, unknown>> {
  if (!raw.value.length) return [];
  const table = raw.value;
  const map = hasHeader.value ? headerMap(table[0]) : writableFields.value.map(field => field.field_name);
  const body = hasHeader.value ? table.slice(1) : table;
  return body.slice(0, 1000).map(cells => {
    const record: Record<string, unknown> = {};
    map.forEach((name, index) => { if (name) record[name] = (cells[index] ?? '').trim(); });
    return record;
  });
}
const mappedNames = computed(() => {
  const names = new Set<string>();
  rows.value.forEach(record => Object.keys(record).forEach(name => names.add(name)));
  return [...names];
});

let lastFileText = '';
async function onFile(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  parseError.value = '';
  raw.value = [];
  if (!file) return;
  lastFileText = await file.text();
  reparse();
}
function reparse() {
  parseError.value = '';
  if (!lastFileText) { raw.value = []; return; }
  const table = parseCsv(lastFileText);
  if (!table.length) { parseError.value = t('formData.csvEmpty', 'CSV 内容为空或无法解析'); raw.value = []; return; }
  if (table.length > 1001) { parseError.value = t('formData.csvTooMany', '超过 1000 行上限，请拆分后重试'); raw.value = []; return; }
  raw.value = table;
  if (!rows.value.length) parseError.value = t('formData.csvNoMappedColumns', '没有可映射的列：表头需使用字段名或字段标签');
}
function submit() {
  emit('submit', rows.value);
}
</script>
