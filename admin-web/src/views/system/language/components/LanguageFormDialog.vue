<template>
  <el-dialog
    v-model="visible"
    :title="row?.id ? '编辑语言' : '新增语言'"
    width="460px"
    :close-on-click-modal="false"
    destroy-on-close
    @closed="resetForm"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
      <el-form-item label="语言名称" prop="name">
        <el-input v-model="form.name" maxlength="20" show-word-limit placeholder="如 zh-cn、en-us" />
      </el-form-item>
      <el-alert title="默认语言 zh-cn 由系统保护，不能重命名或删除。" type="info" :closable="false" />
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { languageApi, type LanguageModel } from '@/api/system/language';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: LanguageModel | null }>(), { row: null });
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void;
  (event: 'success'): void;
}>();
const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});
const formRef = ref<FormInstance>();
const saving = ref(false);
const form = reactive({ name: '' });
const rules: FormRules = {
  name: [
    { required: true, message: '请输入语言名称', trigger: 'blur' },
    { max: 20, message: '最多 20 个字符', trigger: 'blur' }
  ]
};

watch(
  () => [props.modelValue, props.row] as const,
  ([opened, row]) => {
    if (opened) form.name = row?.name ?? '';
  },
  { immediate: true }
);

function resetForm() {
  formRef.value?.resetFields();
  form.name = '';
}

async function onSubmit() {
  if (!(await formRef.value?.validate().catch(() => false))) return;
  saving.value = true;
  try {
    if (props.row?.id) await languageApi.update(props.row.id, form);
    else await languageApi.create(form);
    visible.value = false;
    emit('success');
  } finally {
    saving.value = false;
  }
}
</script>
