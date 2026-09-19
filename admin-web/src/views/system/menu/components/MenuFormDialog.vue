<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? t('systemMenu.dialogEdit', '编辑菜单') : t('systemMenu.dialogAdd', '新增菜单')"
    width="640px"
    :close-on-click-modal="false"
    @closed="onClosed"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="px-2">
      <el-form-item :label="t('systemMenu.type', '类型')" prop="type">
        <el-radio-group v-model="form.type">
          <el-radio-button value="M">{{ t('systemMenu.typeDir', '目录') }}</el-radio-button>
          <el-radio-button value="C">{{ t('systemMenu.typeMenu', '菜单') }}</el-radio-button>
        </el-radio-group>
      </el-form-item>

      <el-form-item :label="t('systemMenu.parentMenu', '上级菜单')" prop="parentId">
        <el-tree-select
          v-model="form.parentId"
          :data="parentOptions"
          :props="{ label: 'name', children: 'children' }"
          node-key="id"
          check-strictly
          :placeholder="t('systemMenu.topPlaceholder', '顶级')"
          clearable
          class="w-full"
        />
      </el-form-item>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item :label="t('systemMenu.colName', '名称')" prop="name">
            <el-input v-model="form.name" :placeholder="t('systemMenu.nameDisplayPlaceholder', '显示名称')" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item :label="t('systemMenu.sort', '排序')" prop="sort">
            <el-input-number v-model="form.sort" :min="0" :max="9999" class="w-full" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('systemMenu.routeName', '路由 name')" prop="routeName">
              <el-input v-model="form.routeName" :placeholder="t('systemMenu.routeNamePlaceholder', '对应 RouteName')" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('systemMenu.routePath', '路由 path')" prop="path">
              <el-input v-model="form.path" placeholder="/system/user" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item v-if="form.type === 'C'" :label="t('systemMenu.component', '组件')" prop="component">
          <el-input v-model="form.component" placeholder="views/system/user/index.vue" />
        </el-form-item>

        <el-form-item v-if="form.type === 'M'" :label="t('systemMenu.redirect', '重定向')" prop="redirect">
          <el-input v-model="form.redirect" :placeholder="t('systemMenu.redirectPlaceholder', '可选，目录的默认重定向')" />
        </el-form-item>

      <el-form-item :label="t('systemMenu.icon', '图标')" prop="icon">
        <IconSelect v-model="form.icon" />
      </el-form-item>

      <el-form-item v-if="form.type === 'C'" :label="t('systemMenu.permissionResource', '权限资源')" prop="permissionId">
        <el-select
          v-model="form.permissionId"
          filterable
          clearable
          class="w-full"
          :loading="permissionLoading"
          :placeholder="t('systemMenu.permissionPlaceholder', '搜索并选择已启用权限资源')"
        >
          <el-option
            v-for="option in permissionOptions"
            :key="option.id"
            :label="`${option.name} (${option.code})`"
            :value="option.id"
          />
        </el-select>
        <div class="mt-1 text-xs text-gray-400">{{ t('systemMenu.permissionTip', '可绑定已启用的路由或能力资源，保存后以资源 ID 稳定关联。') }}</div>
      </el-form-item>

      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item :label="t('systemMenu.hidden', '隐藏')">
            <el-switch v-model="form.hidden" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item :label="t('systemMenu.keepAlive', '缓存')">
            <el-switch v-model="form.keepAlive" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item :label="t('systemMenu.affix', '固定标签')">
            <el-switch v-model="form.affix" />
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">{{ t('common.cancel', '取消') }}</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">{{ t('common.confirm', '确定') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { menuApi } from '@/api/system/menu';
import type { PermissionModel } from '@/api/system/permission';
import { treeToList } from '@/utils/tree';
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

const { t } = useI18n();
const visible = ref(false);
const isEdit = ref(false);
const saving = ref(false);
const permissionLoading = ref(false);
const permissionTree = ref<PermissionModel[]>([]);
const formRef = ref<FormInstance>();

const initialForm = (): Partial<API.MenuItem> => ({
  parentId: 0,
  type: 'C',
  name: '',
  routeName: '',
  path: '',
  component: '',
  redirect: '',
  icon: '',
  permissionId: undefined,
  permission: '',
  sort: 0,
  hidden: false,
  keepAlive: false,
  affix: false
});
const form = reactive<Partial<API.MenuItem>>(initialForm());

const rules = computed<FormRules>(() => ({
  name: [{ required: true, message: t('systemMenu.nameRequired', '请输入名称'), trigger: 'blur' }],
  type: [{ required: true, message: t('systemMenu.typeRequired', '请选择类型'), trigger: 'change' }],
  path: [{ required: true, message: t('systemMenu.pathRequired', '请输入 path'), trigger: 'blur' }],
  component: form.type === 'C' ? [{ required: true, message: t('systemMenu.componentRequired', '请输入组件'), trigger: 'blur' }] : [],
  permissionId: form.type === 'C' ? [{ required: true, message: t('systemMenu.permissionRequired', '请选择权限资源'), trigger: 'change' }] : []
}));

const permissionOptions = computed(() =>
  treeToList(permissionTree.value)
    .filter((item) => ['route', 'capability'].includes(item.resourceType) && item.status === 1 && item.code)
    .map((item) => ({ id: item.id, name: item.name, code: item.code }))
);

const parentOptions = computed<API.MenuItem[]>(() => {
  const onlyDir = (list: API.MenuItem[]): API.MenuItem[] =>
    list
      .filter((it) => it.type === 'M')
      .map((it) => ({ ...it, children: it.children ? onlyDir(it.children) : undefined }));
  return [
    { id: 0, parentId: 0, name: t('systemMenu.noParent', '无上级'), type: 'M', path: '', routeName: 'RootMenu' } as API.MenuItem,
    ...onlyDir(props.tree || [])
  ];
});

watch(
  () => props.modelValue,
  (v) => {
    visible.value = v;
    if (v) {
      initForm();
      void loadPermissions();
    }
  }
);
watch(visible, (v) => emit('update:modelValue', v));

async function loadPermissions() {
  permissionLoading.value = true;
  try {
    permissionTree.value = await menuApi.permissionOptions();
  } finally {
    permissionLoading.value = false;
  }
}

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
