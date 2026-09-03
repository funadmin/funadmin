<template>
  <el-drawer
    v-model="visible"
    :title="`分配权限 - ${row?.name || ''}`"
    direction="rtl"
    size="420px"
  >
    <div v-loading="loading" class="role-perm">
      <div class="role-perm__toolbar">
        <el-button size="small" @click="expandAll(true)">展开全部</el-button>
        <el-button size="small" @click="expandAll(false)">折叠全部</el-button>
        <el-button size="small" @click="checkAll(true)">全选</el-button>
        <el-button size="small" @click="checkAll(false)">取消</el-button>
        <el-checkbox v-model="strictly" class="ml-auto">父子独立</el-checkbox>
      </div>

      <el-tree
        ref="treeRef"
        :data="tree"
        node-key="id"
        :props="{ label: 'title', children: 'children' }"
        show-checkbox
        :check-strictly="strictly"
        :default-expand-all="false"
        class="role-perm__tree"
      />
    </div>

    <template #footer>
      <div class="text-right">
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSubmit">保存</el-button>
      </div>
    </template>
  </el-drawer>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import type { ElTree } from 'element-plus';
import { roleApi, type RoleModel } from '@/api/system/role';
import { menuApi } from '@/api/system/menu';

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
const loading = ref(false);
const saving = ref(false);
const strictly = ref(false);
const tree = ref<API.MenuItem[]>([]);
const treeRef = ref<InstanceType<typeof ElTree>>();

watch(
  () => props.modelValue,
  async (v) => {
    visible.value = v;
    if (v && props.row) await load();
  }
);
watch(visible, (v) => emit('update:modelValue', v));

async function load() {
  loading.value = true;
  try {
    tree.value = await menuApi.tree();
    await nextTick();
    if (props.row?.menuIds) {
      strictly.value = true;
      treeRef.value?.setCheckedKeys(props.row.menuIds);
      strictly.value = false;
    }
  } finally {
    loading.value = false;
  }
}

function expandAll(open: boolean) {
  const inst = treeRef.value as any;
  if (!inst) return;
  const nodes = inst.store?.nodesMap || {};
  Object.values(nodes).forEach((n: any) => (n.expanded = open));
}

function checkAll(checked: boolean) {
  if (!treeRef.value) return;
  if (checked) {
    const allIds = collectIds(tree.value);
    treeRef.value.setCheckedKeys(allIds);
  } else {
    treeRef.value.setCheckedKeys([]);
  }
}

function collectIds(list: API.MenuItem[]): number[] {
  const result: number[] = [];
  const walk = (arr: API.MenuItem[]) => {
    arr.forEach((it) => {
      result.push(it.id);
      if (it.children?.length) walk(it.children);
    });
  };
  walk(list);
  return result;
}

async function onSubmit() {
  if (!props.row || !treeRef.value) return;
  const checked = treeRef.value.getCheckedKeys() as number[];
  const halfChecked = treeRef.value.getHalfCheckedKeys() as number[];
  const ids = strictly.value ? checked : [...checked, ...halfChecked];
  saving.value = true;
  try {
    await roleApi.assignMenus(props.row.id, ids);
    emit('success');
    visible.value = false;
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.role-perm {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: 0 16px;
}
.role-perm__toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--el-border-color-lighter);
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.role-perm__tree {
  flex: 1;
  overflow: auto;
}
</style>
