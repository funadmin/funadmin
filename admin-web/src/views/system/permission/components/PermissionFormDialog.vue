<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑权限资源' : '新增权限资源'"
    width="620px"
    :close-on-click-modal="false"
    destroy-on-close
    @closed="onClosed"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
      <el-form-item label="资源类型" prop="resourceType">
        <el-radio-group v-model="form.resourceType">
          <el-radio-button value="group">目录</el-radio-button>
          <el-radio-button value="route">路由</el-radio-button>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="上级资源" prop="parentId">
        <el-tree-select
          v-model="form.parentId"
          :data="parentOptions"
          :props="{ label: 'title', children: 'children' }"
          node-key="id"
          check-strictly
          clearable
          filterable
          placeholder="顶级资源"
          class="w-full"
        />
      </el-form-item>

      <el-form-item label="资源名称" prop="title">
        <el-input v-model="form.title" maxlength="100" show-word-limit placeholder="请输入资源名称" />
      </el-form-item>

      <el-form-item label="应用标识" prop="module">
        <el-input v-model="form.module" maxlength="50" placeholder="如 backend" />
      </el-form-item>

      <template v-if="form.resourceType === 'route'">
        <el-form-item label="控制器" prop="object">
          <el-input v-model="form.object" maxlength="190" placeholder="如 systempermission" />
        </el-form-item>
        <el-form-item label="动作" prop="action">
          <el-input v-model="form.action" maxlength="100" placeholder="如 tree" />
        </el-form-item>
      </template>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="排序" prop="sort">
            <el-input-number v-model="form.sort" :min="0" :max="9999" class="w-full" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="状态" prop="status">
            <el-radio-group v-model="form.status">
              <el-radio :value="1">启用</el-radio>
              <el-radio :value="0">停用</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="登录后共享" prop="isPublic">
        <el-switch v-model="form.isPublic" :active-value="1" :inactive-value="0" />
        <span class="ml-3 text-xs text-[var(--el-text-color-secondary)]">
          开启后所有已登录管理员都可访问该路由，请谨慎使用
        </span>
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
import { permissionApi, type PermissionModel } from '@/api/system/permission';

interface Props {
  modelValue: boolean;
  row?: PermissionModel | null;
  tree: PermissionModel[];
  defaultParentId?: number;
}

const props = withDefaults(defineProps<Props>(), {
  row: null,
  defaultParentId: 0
});
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void;
  (event: 'success'): void;
}>();

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});
const isEdit = computed(() => Boolean(props.row?.id));
const formRef = ref<FormInstance>();
const saving = ref(false);

const initialForm = (): Partial<PermissionModel> => ({
  parentId: 0,
  module: 'backend',
  title: '',
  object: '',
  action: '',
  resourceType: 'route',
  status: 1,
  isPublic: 0,
  sort: 999
});
const form = reactive<Partial<PermissionModel>>(initialForm());

const rules = computed<FormRules>(() => ({
  title: [{ required: true, message: '请输入资源名称', trigger: 'blur' }],
  module: [
    { required: true, message: '请输入应用标识', trigger: 'blur' },
    { pattern: /^[a-z][a-z0-9_]{0,49}$/, message: '仅支持小写字母、数字和下划线', trigger: 'blur' }
  ],
  object:
    form.resourceType === 'route'
      ? [{ required: true, message: '请输入控制器', trigger: 'blur' }]
      : [],
  action:
    form.resourceType === 'route'
      ? [{ required: true, message: '请输入动作', trigger: 'blur' }]
      : []
}));

function withoutNode(nodes: PermissionModel[], id: number): PermissionModel[] {
  return nodes
    .filter((node) => node.id !== id)
    .map((node) => ({
      ...node,
      children: node.children ? withoutNode(node.children, id) : undefined
    }));
}

const parentOptions = computed<PermissionModel[]>(() => [
  { id: 0, parentId: 0, title: '无上级' } as PermissionModel,
  ...(props.row?.id ? withoutNode(props.tree, props.row.id) : props.tree)
]);

watch(
  () => [props.modelValue, props.row, props.defaultParentId] as const,
  ([open]) => {
    if (!open) return;
    Object.assign(form, initialForm(), props.row || {}, {
      parentId: props.row?.parentId ?? props.defaultParentId
    });
  },
  { immediate: true }
);

watch(
  () => form.resourceType,
  (type) => {
    if (type === 'group') {
      form.object = '';
      form.action = '';
      form.isPublic = 0;
    }
  }
);

async function onSubmit() {
  await formRef.value?.validate();
  saving.value = true;
  try {
    const payload = { ...form };
    if (isEdit.value && props.row) {
      await permissionApi.update(props.row.id, payload);
    } else {
      await permissionApi.create(payload);
    }
    visible.value = false;
    emit('success');
  } finally {
    saving.value = false;
  }
}

function onClosed() {
  formRef.value?.resetFields();
  Object.assign(form, initialForm());
}
</script>
