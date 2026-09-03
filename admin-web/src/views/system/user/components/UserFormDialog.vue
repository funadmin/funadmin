<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑用户' : '新增用户'"
    width="560px"
    :close-on-click-modal="false"
    @closed="onClosed"
  >
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="90px"
      class="px-2"
    >
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="账号" prop="username">
            <el-input v-model="form.username" :disabled="isEdit" placeholder="请输入账号" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="昵称" prop="nickname">
            <el-input v-model="form.nickname" placeholder="请输入昵称" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="邮箱" prop="email">
            <el-input v-model="form.email" placeholder="user@example.com" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="手机" prop="mobile">
            <el-input v-model="form.mobile" placeholder="11 位手机号" />
          </el-form-item>
        </el-col>
        <el-col v-if="!isEdit" :span="12">
          <el-form-item label="密码" prop="password">
            <el-input v-model="form.password" type="password" show-password placeholder="至少 6 位" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="状态" prop="status">
            <el-radio-group v-model="form.status">
              <el-radio-button :value="1">启用</el-radio-button>
              <el-radio-button :value="0">禁用</el-radio-button>
            </el-radio-group>
          </el-form-item>
        </el-col>
        <el-col :span="24">
          <el-form-item label="角色" prop="roleIds">
            <el-select
              v-model="form.roleIds"
              multiple
              collapse-tags
              collapse-tags-tooltip
              placeholder="选择角色"
              class="w-full"
              :loading="rolesLoading"
            >
              <el-option
                v-for="r in roleOptions"
                :key="r.id"
                :label="r.name"
                :value="r.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { userApi, type UserModel } from '@/api/system/user';
import { roleApi, type RoleModel } from '@/api/system/role';

interface Props {
  modelValue: boolean;
  row?: UserModel | null;
}
const props = withDefaults(defineProps<Props>(), {
  row: null
});

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  success: [];
}>();

const visible = ref(false);
const isEdit = ref(false);
const saving = ref(false);
const formRef = ref<FormInstance>();
const roleOptions = ref<RoleModel[]>([]);
const rolesLoading = ref(false);

const initialForm = () => ({
  username: '',
  nickname: '',
  email: '',
  mobile: '',
  password: '',
  status: 1 as 0 | 1,
  roleIds: [] as number[]
});

const form = reactive<ReturnType<typeof initialForm>>(initialForm());

const rules: FormRules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  nickname: [{ required: true, message: '请输入昵称', trigger: 'blur' }],
  password: [{ required: true, min: 6, message: '至少 6 位', trigger: 'blur' }],
  email: [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
  mobile: [{ pattern: /^1\d{10}$/, message: '手机号格式不正确', trigger: 'blur' }],
  roleIds: [{ required: true, message: '请选择角色', trigger: 'change' }]
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
    form.username = props.row.username;
    form.nickname = props.row.nickname;
    form.email = props.row.email || '';
    form.mobile = props.row.mobile || '';
    form.status = props.row.status;
    form.roleIds = props.row.roleIds || [];
  }
}

async function loadRoles() {
  rolesLoading.value = true;
  try {
    roleOptions.value = await roleApi.all();
  } finally {
    rolesLoading.value = false;
  }
}

async function onSubmit() {
  await formRef.value?.validate();
  saving.value = true;
  try {
    if (isEdit.value && props.row) {
      const { password, ...rest } = form;
      void password;
      await userApi.update(props.row.id, rest);
    } else {
      await userApi.create({ ...form });
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

onMounted(loadRoles);
</script>
