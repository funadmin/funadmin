<template>
  <el-dialog v-model="visible" :title="`设置配置值：${row?.code || ''}`" width="640px" destroy-on-close>
    <el-form label-width="92px">
      <el-form-item :label="row?.remark || '配置值'">
        <ConfigValueEditor v-model="value" :type="row?.type" :extra="row?.extra" />
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
import { configApi, type ConfigModel } from '@/api/system/config';
import ConfigValueEditor from './ConfigValueEditor.vue';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: ConfigModel | null }>(), { row: null });
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'success'): void }>();
const visible = computed({ get: () => props.modelValue, set: (opened) => emit('update:modelValue', opened) });
const saving = ref(false);
const value = ref('');
watch(() => [props.modelValue, props.row] as const, ([opened, row]) => {
  if (opened && row) value.value = row.value || '';
}, { immediate: true });
async function onSubmit() {
  if (!props.row) return;
  saving.value = true;
  try {
    await configApi.updateValue(props.row.id, value.value);
    visible.value = false;
    emit('success');
  } finally {
    saving.value = false;
  }
}
</script>
