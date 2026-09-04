<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑角色' : '新增角色'"
    width="640px"
    :close-on-click-modal="false"
    @closed="onClosed"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" class="px-2">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="名称" prop="name">
            <el-input v-model="form.name" placeholder="角色名称" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="标识" prop="code">
            <el-input v-model="form.code" :disabled="isEdit" placeholder="如 editor" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="角色等级" prop="level">
            <el-input-number v-model="form.level" :min="1" :max="9999" class="w-full" />
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
      </el-row>

      <el-form-item label="继承角色" prop="parentRoleIds">
        <el-select
          v-model="form.parentRoleIds"
          multiple
          collapse-tags
          collapse-tags-tooltip
          placeholder="可选，仅可继承等级更高的角色"
          class="w-full"
          :loading="optionsLoading"
        >
          <el-option
            v-for="role in parentOptions"
            :key="role.id"
            :label="`${role.name}（等级 ${role.level}）`"
            :value="role.id"
          />
        </el-select>
      </el-form-item>

      <el-form-item label="数据范围" prop="dataScope">
        <el-select v-model="form.dataScope" class="w-full">
          <el-option label="全部数据" value="all" />
          <el-option label="本部门及下级" value="dept_and_children" />
          <el-option label="本部门" value="dept" />
          <el-option label="仅本人" value="self" />
          <el-option label="自定义部门" value="custom" />
        </el-select>
      </el-form-item>

      <el-form-item v-if="form.dataScope === 'custom'" label="自定义部门" prop="departmentIds">
        <el-tree-select
          v-model="form.departmentIds"
          :data="departmentOptions"
          :props="{ label: 'name', children: 'children' }"
          node-key="id"
          multiple
          show-checkbox
          check-strictly
          collapse-tags
          collapse-tags-tooltip
          placeholder="请选择部门"
          class="w-full"
          :loading="optionsLoading"
        />
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
import { computed, reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { roleApi, type DataScope, type RoleModel } from '@/api/system/role';
import { deptApi, type DeptModel } from '@/api/system/dept';

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
const optionsLoading = ref(false);
const formRef = ref<FormInstance>();
const roleOptions = ref<RoleModel[]>([]);
const departmentOptions = ref<DeptModel[]>([]);

const initialForm = () => ({
  name: '',
  code: '',
  level: 100,
  dataScope: 'self' as DataScope,
  status: 1 as 0 | 1,
  remark: '',
  parentRoleIds: [] as number[],
  departmentIds: [] as number[]
});
const form = reactive<ReturnType<typeof initialForm>>(initialForm());

const parentOptions = computed(() =>
  roleOptions.value.filter((role) => role.id !== props.row?.id && role.level < form.level)
);

const rules: FormRules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  code: [
    { required: true, message: '请输入标识', trigger: 'blur' },
    { pattern: /^[a-zA-Z][a-zA-Z0-9_]*$/, message: '需以英文开头，仅支持字母、数字和下划线', trigger: 'blur' }
  ],
  level: [{ required: true, message: '请输入角色等级', trigger: 'change' }],
  dataScope: [{ required: true, message: '请选择数据范围', trigger: 'change' }],
  departmentIds: [{
    validator: (_rule, value: number[], callback) => {
      if (form.dataScope === 'custom' && (!value || value.length === 0)) callback(new Error('请选择自定义部门'));
      else callback();
    },
    trigger: 'change'
  }]
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
watch(() => form.level, () => {
  const allowed = new Set(parentOptions.value.map((role) => role.id));
  form.parentRoleIds = form.parentRoleIds.filter((id) => allowed.has(id));
});
watch(() => form.dataScope, (scope) => {
  if (scope !== 'custom') form.departmentIds = [];
});

function initForm() {
  Object.assign(form, initialForm());
  isEdit.value = !!props.row;
  if (props.row) {
    form.name = props.row.name;
    form.code = props.row.code;
    form.level = props.row.level;
    form.dataScope = props.row.dataScope;
    form.status = props.row.status;
    form.remark = props.row.remark || '';
    form.parentRoleIds = [...(props.row.parentRoleIds || [])];
    form.departmentIds = [...(props.row.departmentIds || [])];
  }
}

async function loadOptions() {
  optionsLoading.value = true;
  try {
    const [roles, departments] = await Promise.all([roleApi.parentOptions(), deptApi.tree()]);
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
    const payload = { ...form, parentRoleIds: [...form.parentRoleIds], departmentIds: [...form.departmentIds] };
    if (isEdit.value && props.row) await roleApi.update(props.row.id, payload);
    else await roleApi.create(payload);
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
