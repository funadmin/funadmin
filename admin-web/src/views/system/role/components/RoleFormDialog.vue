<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑角色' : '新增角色'"
    width="520px"
    :close-on-click-modal="false"
    @closed="onClosed"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px" class="px-2">
      <el-form-item label="名称" prop="name">
        <el-input v-model="form.name" placeholder="角色名称" />
      </el-form-item>
      <el-form-item label="标识" prop="code">
        <el-input v-model="form.code" :disabled="isEdit" placeholder="英文标识，如 admin / editor" />
      </el-form-item>
      <el-form-item label="状态" prop="status">
        <el-radio-group v-model="form.status">
          <el-radio-button :value="1">启用</el-radio-button>
          <el-radio-button :value="0">禁用</el-radio-button>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="备注" prop="remark">
        <el-input v-model="form.remark" type="textarea" :rows="3" placeholder="可选" />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { roleApi, type RoleModel } from '@/api/system/role';

interface Props {
  modelValue: boolean;
  row?: RoleModel | null;
}
const props = withDefaults(defineProps<Props>(), { row: null });

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  success: [];
}>();

const visible = ref(false);
const isEdit = ref(false);
const saving = ref(false);
const formRef = ref<FormInstance>();

const initialForm = () => ({
  name: '',
  code: '',
  status: 1 as 0 | 1,
  remark: ''
});
const form = reactive<ReturnType<typeof initialForm>>(initialForm());

const rules: FormRules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  code: [
    { required: true, message: '请输入标识', trigger: 'blur' },
    { pattern: /^[a-zA-Z][a-zA-Z0-9_-]*$/, message: '需以英文开头', trigger: 'blur' }
  ]
};

watch(
  () => props.modelValue,
  (v) => {
    visible.value = v;
    if (v) initForm();
  }
);
watch(visible, (v) => emit('update:modelValue', v));

function initForm() {
  Object.assign(form, initialForm());
  isEdit.value = !!props.row;
  if (props.row) {
    form.name = props.row.name;
    form.code = props.row.code;
    form.status = props.row.status;
    form.remark = props.row.remark || '';
  }
}

async function onSubmit() {
  await formRef.value?.validate();
  saving.value = true;
  try {
    if (isEdit.value && props.row) {
      await roleApi.update(props.row.id, { ...form });
    } else {
      await roleApi.create({ ...form });
    }
    emit('success');
    visible.value = false;
  } finally {
    saving.value = false;
  }
}

function onClosed() {
  formRef.value?.resetFields();
  Object.assign(form, initialForm());
}
</script>
