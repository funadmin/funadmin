<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑管理员' : '新增管理员'"
    width="600px"
    :close-on-click-modal="false"
    @closed="onClosed"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px" class="px-2">
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
            <el-input v-model="form.password" type="password" show-password placeholder="至少 8 位" />
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
          <el-form-item label="部门" prop="deptId">
            <el-tree-select
              v-model="form.deptId"
              :data="departmentOptions"
              :props="{ label: 'name', children: 'children' }"
              node-key="id"
              check-strictly
              placeholder="选择部门"
              class="w-full"
              :loading="optionsLoading"
            />
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
              :loading="optionsLoading"
            >
              <el-option
                v-for="role in roleOptions"
                :key="role.id"
                :label="`${role.name}（等级 ${role.level}）`"
                :value="role.id"
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
import { reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { userApi, type UserModel } from '@/api/system/user';
import { roleApi, type RoleModel } from '@/api/system/role';
import { deptApi, type DeptModel } from '@/api/system/dept';

interface Props {
  modelValue: boolean;
  row?: UserModel | null;
}
const props = withDefaults(defineProps<Props>(), { row: null });

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  success: [];
}>();

const visible = ref(false);
const isEdit = ref(false);
const saving = ref(false);
const optionsLoading = ref(false);
const formRef = ref<FormInstance>();
const roleOptions = ref<RoleModel[]>([]);
const departmentOptions = ref<DeptModel[]>([]);

const initialForm = () => ({
  username: '',
  nickname: '',
  email: '',
  mobile: '',
  password: '',
  status: 1 as 0 | 1,
  deptId: undefined as number | undefined,
  roleIds: [] as number[]
});
const form = reactive<ReturnType<typeof initialForm>>(initialForm());

const rules: FormRules = {
  username: [
    { required: true, message: '请输入账号', trigger: 'blur' },
    { pattern: /^[A-Za-z][A-Za-z0-9_]{2,19}$/, message: '账号需以字母开头，长度 3 到 20 位', trigger: 'blur' }
  ],
  nickname: [{ required: true, message: '请输入昵称', trigger: 'blur' }],
  password: [{ required: true, min: 8, message: '密码至少 8 位', trigger: 'blur' }],
  email: [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
  mobile: [{ pattern: /^1\d{10}$/, message: '手机号格式不正确', trigger: 'blur' }],
  deptId: [{ required: true, message: '请选择部门', trigger: 'change' }],
  roleIds: [{ required: true, message: '请选择角色', trigger: 'change' }]
};

watch(
  () => props.modelValue,
  async (value) => {
    visible.value = value;
    if (value) {
      initForm();
      await loadOptions();
    }
  }
);
watch(visible, (value) => emit('update:modelValue', value));

function initForm() {
  Object.assign(form, initialForm());
  isEdit.value = !!props.row;
  if (props.row) {
    form.username = props.row.username;
    form.nickname = props.row.nickname;
    form.email = props.row.email || '';
    form.mobile = props.row.mobile || '';
    form.status = props.row.status;
    form.deptId = props.row.deptId;
    form.roleIds = [...(props.row.roleIds || [])];
  }
}

async function loadOptions() {
  optionsLoading.value = true;
  try {
    const [roles, departments] = await Promise.all([roleApi.all(), deptApi.tree()]);
    roleOptions.value = roles;
    departmentOptions.value = departments;
  } finally {
    optionsLoading.value = false;
  }
}

async function onSubmit() {
  await formRef.value?.validate();
  saving.value = true;
  try {
    const payload = { ...form, deptId: form.deptId as number, roleIds: [...form.roleIds] };
    if (isEdit.value && props.row) {
      const { password, ...rest } = payload;
      void password;
      await userApi.update(props.row.id, rest);
    } else {
      await userApi.create(payload);
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
