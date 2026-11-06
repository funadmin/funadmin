<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? t('systemUser.dialogEdit', '编辑管理员') : t('systemUser.dialogAdd', '新增管理员')"
    width="600px"
    :close-on-click-modal="false"
    @closed="onClosed"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px" class="px-2">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item :label="t('systemUser.colUsername', '账号')" prop="username">
            <el-input v-model="form.username" :disabled="isEdit" :placeholder="t('systemUser.usernamePlaceholder', '请输入账号')" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('systemUser.colNickname', '昵称')" prop="nickname">
            <el-input v-model="form.nickname" :placeholder="t('systemUser.nicknamePlaceholder', '请输入昵称')" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('systemUser.colEmail', '邮箱')" prop="email">
            <el-input v-model="form.email" maxlength="60" placeholder="user@example.com" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('systemUser.colMobile', '手机')" prop="mobile">
            <el-input v-model="form.mobile" :placeholder="t('systemUser.mobilePlaceholder', '11 位手机号')" />
          </el-form-item>
        </el-col>
        <el-col v-if="!isEdit" :span="12">
          <el-form-item :label="t('systemUser.password', '密码')" prop="password">
            <el-input v-model="form.password" type="password" show-password :placeholder="t('systemUser.passwordPlaceholder', '至少 8 位')" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('systemUser.colStatus', '状态')" prop="status">
            <el-radio-group v-model="form.status" :disabled="isSuperRow">
              <el-radio-button :value="1">{{ t('systemUser.enabled', '启用') }}</el-radio-button>
              <el-radio-button :value="0">{{ t('systemUser.disabled', '禁用') }}</el-radio-button>
            </el-radio-group>
          </el-form-item>
        </el-col>
        <el-col :span="24">
          <el-form-item :label="t('systemUser.dept', '主部门')" prop="deptId">
            <el-tree-select
              v-model="form.deptId"
              :disabled="isSuperRow"
              :data="departmentOptions"
              :props="{ label: 'name', children: 'children' }"
              node-key="id"
              check-strictly
              :placeholder="t('systemUser.deptPlaceholder', '选择部门')"
              class="w-full"
              :loading="optionsLoading"
            />
          </el-form-item>
        </el-col>
        <el-col :span="24">
          <el-form-item :label="t('systemUser.additionalDept', '兼任部门')" prop="departmentIds">
            <el-tree-select
              v-model="form.departmentIds"
              :disabled="isSuperRow"
              :data="additionalDepartmentOptions"
              :props="{ label: 'name', children: 'children' }"
              node-key="id"
              multiple
              show-checkbox
              check-strictly
              collapse-tags
              collapse-tags-tooltip
              clearable
              :placeholder="t('systemUser.additionalDeptPlaceholder', '可选，可兼任多个部门')"
              :no-data-text="t('systemUser.noAdditionalDept', '暂无可兼任部门')"
              class="w-full"
              :loading="optionsLoading"
            />
          </el-form-item>
        </el-col>
        <el-col :span="24">
          <el-form-item :label="t('systemUser.role', '角色')" prop="roleIds">
            <el-tree-select
              v-model="form.roleIds"
              :disabled="isSuperRow"
              :data="roleTreeOptions"
              :props="{ label: 'name', children: 'children' }"
              node-key="id"
              multiple
              show-checkbox
              check-strictly
              collapse-tags
              collapse-tags-tooltip
              clearable
              :placeholder="t('systemUser.rolePlaceholder', '选择角色')"
              :no-data-text="t('systemUser.noRole', '暂无可分配角色')"
              class="w-full"
              :loading="optionsLoading"
            />
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">{{ t('common.cancel', '取消') }}</el-button>
      <el-button v-perm="props.row?.id ? 'authorization:admin:update' : 'authorization:admin:create'" type="primary" :loading="saving" @click="onSubmit">{{ t('common.confirm', '确定') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { hasPermission } from '@/utils/permission';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormInstance, FormRules } from 'element-plus';
import { userApi, type UserModel } from '@/api/system/user';
import { roleApi, type RoleModel } from '@/api/system/role';
import { deptApi, type DeptModel } from '@/api/system/dept';
import { roleTree } from '../../role/roleHierarchy';
import { isWebmanBackend } from '@/config';
import { filterDepartmentTree } from '../departmentAssignment';
import { roleOptionsForRow } from '../superAdminAccess';

interface Props {
  modelValue: boolean;
  row?: UserModel | null;
}
const props = withDefaults(defineProps<Props>(), { row: null });
const { t } = useI18n();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  success: [];
}>();

const visible = ref(false);
const isEdit = ref(false);
/** 超管只改资料：状态、部门与角色由后端固定，前端也不允许改，并跳过角色必填校验 */
const isSuperRow = computed(() => !!props.row?.isSuper);
const saving = ref(false);
const optionsLoading = ref(false);
const formRef = ref<FormInstance>();
const roleOptions = ref<RoleModel[]>([]);
const roleTreeOptions = computed(() => roleTree(roleOptionsForRow(roleOptions.value, props.row, t('systemUser.superAdmin', '超级管理员'))));
const departmentOptions = ref<DeptModel[]>([]);

const initialForm = () => ({
  username: '',
  nickname: '',
  email: '',
  mobile: '',
  password: '',
  status: 1 as 0 | 1,
  deptId: undefined as number | undefined,
  departmentIds: [] as number[],
  roleIds: [] as number[]
});
const form = reactive<ReturnType<typeof initialForm>>(initialForm());
const additionalDepartmentOptions = computed(() => filterDepartmentTree(departmentOptions.value, form.deptId));

const rules = computed<FormRules>(() => ({
  username: [
    { required: true, message: t('systemUser.usernamePlaceholder', '请输入账号'), trigger: 'blur' },
    { pattern: /^[A-Za-z][A-Za-z0-9_]{2,19}$/, message: t('systemUser.usernamePattern', '账号需以字母开头，长度 3 到 20 位'), trigger: 'blur' }
  ],
  nickname: [{ required: true, message: t('systemUser.nicknamePlaceholder', '请输入昵称'), trigger: 'blur' }],
  password: [{
    required: true,
    validator: (_rule, value: string, callback) => {
      if (!isWebmanBackend()) return value.length >= 8 ? callback() : callback(new Error(t('systemUser.passwordMin', '密码至少 8 位')));
      const bytes = new TextEncoder().encode(value).length;
      return bytes < 12 || bytes > 72 || value.includes('\0') ? callback(new Error(t('profile.passwordBytes', '新密码需为 12～72 字节'))) : callback();
    },
    trigger: 'blur'
  }],
  email: [
    { type: 'email', message: t('systemUser.emailInvalid', '邮箱格式不正确'), trigger: 'blur' },
    { max: 60, message: t('systemUser.emailMax', '邮箱不能超过 60 个字符'), trigger: 'blur' }
  ],
  mobile: [{ pattern: /^1\d{10}$/, message: t('systemUser.mobileInvalid', '手机号格式不正确'), trigger: 'blur' }],
  deptId: [{ required: true, message: t('systemUser.deptPlaceholder', '选择部门'), trigger: 'change' }],
  roleIds: isSuperRow.value ? [] : [{ required: true, message: t('systemUser.rolePlaceholder', '选择角色'), trigger: 'change' }]
}));

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
watch(() => form.deptId, (deptId) => {
  if (deptId !== undefined) form.departmentIds = form.departmentIds.filter((id) => id !== deptId);
});

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
    form.departmentIds = [...(props.row.departmentIds || [])];
    form.roleIds = [...(props.row.roleIds || [])];
  }
}

async function loadOptions() {
  optionsLoading.value = true;
  try {
    const [roles, departments] = await Promise.all([roleApi.all(), deptApi.tree()]);
    roleOptions.value = roles.filter((role) => role.status === 1);
    departmentOptions.value = departments;
  } finally {
    optionsLoading.value = false;
  }
}

async function onSubmit() {
  if (!hasPermission(props.row?.id ? 'authorization:admin:update' : 'authorization:admin:create')) return;
  await formRef.value?.validate();
  saving.value = true;
  try {
    const payload = {
      ...form,
      deptId: form.deptId as number,
      departmentIds: [...form.departmentIds],
      roleIds: [...form.roleIds]
    };
    if (isEdit.value && props.row) {
      const { password, ...rest } = payload;
      void password;
      if (!hasPermission(props.row?.id ? 'authorization:admin:update' : 'authorization:admin:create')) return;
     await userApi.update(props.row.id, rest);
    } else {
      if (!hasPermission(props.row?.id ? 'authorization:admin:update' : 'authorization:admin:create')) return;
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
