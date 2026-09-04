<template>
  <el-dialog v-model="visible" :title="row?.id ? '编辑配置分组' : '新增配置分组'" width="520px" destroy-on-close @closed="resetForm">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="96px">
      <el-form-item label="分组编码" prop="name">
        <el-input v-model="form.name" maxlength="30" placeholder="例如 site" />
      </el-form-item>
      <el-form-item label="分组标题" prop="title">
        <el-input v-model="form.title" maxlength="60" show-word-limit />
      </el-form-item>
      <el-form-item label="状态" prop="status">
        <el-radio-group v-model="form.status">
          <el-radio :value="1">启用</el-radio>
          <el-radio :value="0">停用</el-radio>
        </el-radio-group>
      </el-form-item>
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
import { configApi, type ConfigGroupModel, type ConfigGroupPayload } from '@/api/system/config';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: ConfigGroupModel | null }>(), { row: null });
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'success'): void }>();
const visible = computed({ get: () => props.modelValue, set: (value) => emit('update:modelValue', value) });
const formRef = ref<FormInstance>();
const saving = ref(false);
const initialForm = (): ConfigGroupPayload => ({ name: '', title: '', status: 1 });
const form = reactive<ConfigGroupPayload>(initialForm());
const rules: FormRules = {
  name: [{ required: true, message: '请输入分组编码', trigger: 'blur' }, { pattern: /^[A-Za-z][A-Za-z0-9_-]{0,29}$/, message: '以字母开头，只能包含字母、数字、横线和下划线', trigger: 'blur' }],
  title: [{ required: true, message: '请输入分组标题', trigger: 'blur' }, { max: 60, message: '最多 60 个字符', trigger: 'blur' }]
};
watch(() => [props.modelValue, props.row] as const, ([opened, row]) => {
  if (!opened) return;
  Object.assign(form, initialForm(), row ? { name: row.name, title: row.title, status: row.status } : {});
}, { immediate: true });
function resetForm() { formRef.value?.resetFields(); Object.assign(form, initialForm()); }
async function onSubmit() {
  if (!(await formRef.value?.validate().catch(() => false))) return;
  saving.value = true;
  try {
    if (props.row?.id) await configApi.updateGroup(props.row.id, form);
    else await configApi.createGroup(form);
    visible.value = false;
    emit('success');
  } finally { saving.value = false; }
}
</script>
