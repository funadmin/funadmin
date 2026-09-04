<template>
  <el-dialog
    v-model="visible"
    :title="row?.id ? '编辑会员组' : '新增会员组'"
    width="480px"
    :close-on-click-modal="false"
    destroy-on-close
    @closed="resetForm"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
      <el-form-item label="会员组名称" prop="name">
        <el-input v-model="form.name" maxlength="50" show-word-limit placeholder="请输入会员组名称" />
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
import { memberGroupApi, type MemberGroupModel } from '@/api/system/memberGroup';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: MemberGroupModel | null }>(), { row: null });
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
const initialForm = () => ({ name: '', status: 1 as 0 | 1 });
const form = reactive(initialForm());
const rules: FormRules = {
  name: [
    { required: true, message: '请输入会员组名称', trigger: 'blur' },
    { max: 50, message: '最多 50 个字符', trigger: 'blur' }
  ]
};

watch(
  () => [props.modelValue, props.row] as const,
  ([opened, row]) => {
    if (opened) Object.assign(form, initialForm(), row ? { name: row.name, status: row.status } : {});
  },
  { immediate: true }
);

function resetForm() {
  formRef.value?.resetFields();
  Object.assign(form, initialForm());
}

async function onSubmit() {
  if (!(await formRef.value?.validate().catch(() => false))) return;
  saving.value = true;
  try {
    if (props.row?.id) await memberGroupApi.update(props.row.id, form);
    else await memberGroupApi.create(form);
    visible.value = false;
    emit('success');
  } finally {
    saving.value = false;
  }
}
</script>
