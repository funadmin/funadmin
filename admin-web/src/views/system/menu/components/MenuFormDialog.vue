<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑菜单' : '新增菜单'"
    width="640px"
    :close-on-click-modal="false"
    @closed="onClosed"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="px-2">
      <el-form-item label="类型" prop="type">
        <el-radio-group v-model="form.type">
          <el-radio-button value="M">目录</el-radio-button>
          <el-radio-button value="C">菜单</el-radio-button>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="上级菜单" prop="parentId">
        <el-tree-select
          v-model="form.parentId"
          :data="parentOptions"
          :props="{ label: 'title', children: 'children' }"
          node-key="id"
          check-strictly
          placeholder="顶级"
          clearable
          class="w-full"
        />
      </el-form-item>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="名称" prop="title">
            <el-input v-model="form.title" placeholder="显示名称" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="排序" prop="sort">
            <el-input-number v-model="form.sort" :min="0" :max="9999" class="w-full" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="路由 name" prop="name">
              <el-input v-model="form.name" placeholder="对应 RouteName" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="路由 path" prop="path">
              <el-input v-model="form.path" placeholder="/system/user" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item v-if="form.type === 'C'" label="组件" prop="component">
          <el-input v-model="form.component" placeholder="views/system/user/index.vue" />
        </el-form-item>

        <el-form-item v-if="form.type === 'M'" label="重定向" prop="redirect">
          <el-input v-model="form.redirect" placeholder="可选，目录的默认重定向" />
        </el-form-item>

      <el-form-item label="图标" prop="icon">
        <IconSelect v-model="form.icon" />
      </el-form-item>

      <el-form-item v-if="form.type === 'C'" label="权限标识" prop="permission">
        <el-input v-model="form.permission" placeholder="如 system:user:add" />
      </el-form-item>

      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="隐藏">
            <el-switch v-model="form.hidden" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="缓存">
            <el-switch v-model="form.keepAlive" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="固定标签">
            <el-switch v-model="form.affix" />
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
import { computed, reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { menuApi } from '@/api/system/menu';
import IconSelect from '@/components/IconSelect/index.vue';

interface Props {
  modelValue: boolean;
  row?: API.MenuItem | null;
  tree?: API.MenuItem[];
  defaultParentId?: number;
}
const props = withDefaults(defineProps<Props>(), {
  row: null,
  tree: () => [] as API.MenuItem[],
  defaultParentId: 0
});

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  success: [];
}>();

const visible = ref(false);
const isEdit = ref(false);
const saving = ref(false);
const formRef = ref<FormInstance>();

const initialForm = (): Partial<API.MenuItem> => ({
  parentId: 0,
  type: 'C',
  title: '',
  name: '',
  path: '',
  component: '',
  redirect: '',
  icon: '',
  permission: '',
  sort: 0,
  hidden: false,
  keepAlive: false,
  affix: false
});
const form = reactive<Partial<API.MenuItem>>(initialForm());

const rules = computed<FormRules>(() => ({
  title: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择类型', trigger: 'change' }],
  path: [{ required: true, message: '请输入 path', trigger: 'blur' }],
  component: form.type === 'C' ? [{ required: true, message: '请输入组件', trigger: 'blur' }] : [],
  permission: form.type === 'C' ? [{ required: true, message: '请输入权限标识', trigger: 'blur' }] : []
}));

const parentOptions = computed<API.MenuItem[]>(() => {
  const onlyDir = (list: API.MenuItem[]): API.MenuItem[] =>
    list
      .filter((it) => it.type === 'M')
      .map((it) => ({ ...it, children: it.children ? onlyDir(it.children) : undefined }));
  return onlyDir(props.tree || []);
});

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
    Object.assign(form, props.row);
  } else {
    form.parentId = props.defaultParentId || 0;
  }
}

async function onSubmit() {
  await formRef.value?.validate();
  saving.value = true;
  try {
    if (isEdit.value && props.row) {
      await menuApi.update(props.row.id, { ...form });
    } else {
      await menuApi.create({ ...form });
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
