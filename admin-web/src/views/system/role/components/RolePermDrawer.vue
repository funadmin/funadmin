<template>
  <el-drawer v-model="visible" title="角色授权工作区" direction="rtl" size="92%" destroy-on-close>
    <div v-loading="loading" class="role-authorization">
      <aside class="role-authorization__sidebar">
        <el-input v-model="roleKeyword" placeholder="角色搜索" clearable prefix-icon="Search" />
        <el-tree
          :data="filteredRoles"
          node-key="id"
          :props="{ label: 'name', children: 'children' }"
          highlight-current
          default-expand-all
          :current-node-key="activeRoleId"
          @node-click="selectRole"
        />
      </aside>

      <main class="role-authorization__main">
        <header class="role-authorization__header">
          <div>
            <h3>{{ activeRole?.name || row?.name || '角色授权' }}</h3>
            <span>直接授权可编辑，继承授权只读并显示来源</span>
          </div>
          <div class="role-authorization__copy">
            <el-select v-model="copySourceRoleId" placeholder="选择授权来源角色" filterable clearable>
              <el-option
                v-for="role in copySourceOptions"
                :key="role.id"
                :label="role.name"
                :value="role.id"
              />
            </el-select>
            <el-button :disabled="!copySourceRoleId" :loading="copying" @click="copyAuthorization">
              复制授权
            </el-button>
          </div>
        </header>

        <el-tabs v-model="activeTab" class="role-authorization__tabs">
          <el-tab-pane label="功能权限" name="permissions">
            <el-collapse v-model="expandedGroups">
              <el-collapse-item v-for="group in permissionGroups" :key="group.id" :name="group.id">
                <template #title><strong>{{ group.name }}</strong></template>
                <el-table :data="group.resources" border>
                  <el-table-column prop="name" label="资源" min-width="180" fixed />
                  <el-table-column label="动作" min-width="520">
                    <template #default="{ row: resource }">
                      <div class="permission-actions">
                        <el-tooltip
                          v-for="action in resource.actions"
                          :key="action.id"
                          :content="inheritanceText(action.inheritedFrom)"
                          :disabled="!action.inherited"
                        >
                          <el-checkbox
                            :model-value="action.direct || action.inherited"
                            :disabled="action.inherited"
                            @change="setPermission(action, $event)"
                          >
                            {{ action.name }}
                            <el-tag v-if="action.inherited" size="small" type="info">继承</el-tag>
                          </el-checkbox>
                        </el-tooltip>
                      </div>
                    </template>
                  </el-table-column>
                </el-table>
              </el-collapse-item>
            </el-collapse>
          </el-tab-pane>

          <el-tab-pane label="字段权限" name="fields">
            <el-table :data="fields" border row-key="id">
              <el-table-column prop="resource" label="资源" min-width="180" />
              <el-table-column prop="name" label="字段" min-width="180">
                <template #default="{ row: field }">
                  {{ field.name }} <span class="field-code">{{ field.field }}</span>
                </template>
              </el-table-column>
              <el-table-column label="查看" width="150" align="center">
                <template #default="{ row: field }">
                  <el-tooltip :content="inheritanceText(field.inheritedFrom)" :disabled="!field.inheritedView">
                    <el-checkbox
                      :model-value="field.view || field.inheritedView"
                      :disabled="field.inheritedView"
                      @change="setFieldView(field as FieldPermission, $event)"
                    >继承</el-checkbox>
                  </el-tooltip>
                </template>
              </el-table-column>
              <el-table-column label="编辑" width="150" align="center">
                <template #default="{ row: field }">
                  <el-tooltip :content="inheritanceText(field.inheritedFrom)" :disabled="!field.inheritedEdit">
                    <el-checkbox
                      :model-value="field.edit || field.inheritedEdit"
                      :disabled="field.inheritedEdit"
                      @change="setFieldEdit(field as FieldPermission, $event)"
                    >继承</el-checkbox>
                  </el-tooltip>
                </template>
              </el-table-column>
            </el-table>
            <el-empty v-if="!fields.length" description="暂无已登记的字段白名单" />
          </el-tab-pane>

          <el-tab-pane label="数据授权" name="data">
            <el-radio-group v-model="dataScope" class="data-scopes">
              <el-radio value="all">全部数据</el-radio>
              <el-radio value="dept_and_children">本部门及下级</el-radio>
              <el-radio value="dept">本部门</el-radio>
              <el-radio value="self">仅本人</el-radio>
              <el-radio value="custom">自定义部门</el-radio>
            </el-radio-group>
            <el-tree
              v-if="dataScope === 'custom'"
              ref="departmentTreeRef"
              :data="departmentTree"
              node-key="id"
              :props="{ label: 'name', children: 'children' }"
              show-checkbox
              default-expand-all
              check-strictly
              class="department-tree"
            />
          </el-tab-pane>
        </el-tabs>
      </main>
    </div>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" :disabled="!activeRoleId" @click="save">
        保存整套授权
      </el-button>
    </template>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import type { ElTree } from 'element-plus';
import {
  roleApi,
  type AuthorizationSource,
  type AuthorizationTreeNode,
  type DataScope,
  type FieldPermission,
  type PermissionAction,
  type PermissionGroup,
  type RoleModel
} from '@/api/system/role';
import { normalizeFieldGrant, toggleFieldEdit, toggleFieldView } from '../roleAuthorization';

interface Props { modelValue: boolean; row?: RoleModel | null }
const props = withDefaults(defineProps<Props>(), { row: null });
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; success: [] }>();

const visible = ref(false);
const loading = ref(false);
const saving = ref(false);
const copying = ref(false);
const activeTab = ref('permissions');
const activeRoleId = ref<number>();
const roleKeyword = ref('');
const roles = ref<AuthorizationTreeNode[]>([]);
const permissionGroups = ref<PermissionGroup[]>([]);
const fields = ref<FieldPermission[]>([]);
const dataScope = ref<DataScope>('self');
const departmentIds = ref<number[]>([]);
const departmentTree = ref<AuthorizationTreeNode[]>([]);
const expandedGroups = ref<number[]>([]);
const copySourceRoleId = ref<number>();
const departmentTreeRef = ref<InstanceType<typeof ElTree>>();

const flattenRoles = computed(() => {
  const result: AuthorizationTreeNode[] = [];
  const walk = (items: AuthorizationTreeNode[]) => items.forEach((item) => {
    result.push(item);
    if (item.children?.length) walk(item.children);
  });
  walk(roles.value);
  return result;
});
const activeRole = computed(() => flattenRoles.value.find((role) => role.id === activeRoleId.value));
const copySourceOptions = computed(() => flattenRoles.value.filter((role) => role.id !== activeRoleId.value));
const filteredRoles = computed(() => filterTree(roles.value, roleKeyword.value.trim().toLowerCase()));

watch(() => props.modelValue, async (value) => {
  visible.value = value;
  if (value && props.row) {
    activeRoleId.value = props.row.id;
    await load(props.row.id);
  }
});
watch(visible, (value) => emit('update:modelValue', value));

const filterTree = (items: AuthorizationTreeNode[], keyword: string): AuthorizationTreeNode[] => items.flatMap((item) => {
  const children = filterTree(item.children || [], keyword);
  const matched = !keyword || `${item.name} ${item.code || ''}`.toLowerCase().includes(keyword);
  return matched || children.length ? [{ ...item, children }] : [];
});

const load = async (roleId: number) => {
  loading.value = true;
  try {
    const authorization = await roleApi.authorization(roleId);
    roles.value = authorization.roles;
    if (!flattenRoles.value.some((role) => role.id === roleId) && props.row) {
      roles.value = [{ id: props.row.id, parentId: props.row.parentId, name: props.row.name, code: props.row.code }];
    }
    permissionGroups.value = authorization.permissionGroups;
    fields.value = authorization.fields;
    dataScope.value = authorization.dataScope;
    departmentIds.value = authorization.departmentIds;
    departmentTree.value = authorization.departmentTree;
    expandedGroups.value = authorization.permissionGroups.map((group) => group.id);
    await nextTick();
    departmentTreeRef.value?.setCheckedKeys(departmentIds.value);
  } finally {
    loading.value = false;
  }
};

const selectRole = async (role: AuthorizationTreeNode) => {
  if (role.id === activeRoleId.value) return;
  activeRoleId.value = role.id;
  copySourceRoleId.value = undefined;
  await load(role.id);
};

const setPermission = (action: PermissionAction, checked: unknown) => {
  if (!action.inherited) action.direct = Boolean(checked);
};
const setFieldView = (field: FieldPermission, checked: unknown) => {
  Object.assign(field, toggleFieldView(field, Boolean(checked)));
};
const setFieldEdit = (field: FieldPermission, checked: unknown) => {
  Object.assign(field, toggleFieldEdit(field, Boolean(checked)));
};
const inheritanceText = (sources: AuthorizationSource[]) => sources.length
  ? `继承自：${sources.map((source) => source.roleName).join('、')}`
  : '';

const save = async () => {
  if (!activeRoleId.value) return;
  saving.value = true;
  try {
    const permissionIds = permissionGroups.value.flatMap((group) => group.resources)
      .flatMap((resource) => resource.actions)
      .filter((action) => action.direct)
      .map((action) => action.id);
    const fieldPermissions = fields.value.map((field) => ({ fieldId: field.id, ...normalizeFieldGrant(field) }))
      .filter((field) => field.view || field.edit);
    const customDepartmentIds = dataScope.value === 'custom'
      ? (departmentTreeRef.value?.getCheckedKeys() as number[] || [])
      : [];
    await roleApi.saveAuthorization(activeRoleId.value, {
      permissionIds,
      fieldPermissions,
      dataScope: dataScope.value,
      departmentIds: customDepartmentIds
    });
    emit('success');
    await load(activeRoleId.value);
  } finally {
    saving.value = false;
  }
};

const copyAuthorization = async () => {
  if (!activeRoleId.value || !copySourceRoleId.value) return;
  copying.value = true;
  try {
    await roleApi.copyAuthorization(activeRoleId.value, copySourceRoleId.value);
    await load(activeRoleId.value);
    emit('success');
  } finally {
    copying.value = false;
  }
};
</script>

<style scoped>
.role-authorization { display: grid; grid-template-columns: 260px minmax(0, 1fr); height: calc(100vh - 150px); border: 1px solid var(--el-border-color-lighter); }
.role-authorization__sidebar { padding: 16px; overflow: auto; border-right: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-lighter); }
.role-authorization__sidebar .el-tree { margin-top: 14px; background: transparent; }
.role-authorization__main { display: flex; min-width: 0; flex-direction: column; padding: 0 20px 20px; overflow: hidden; }
.role-authorization__header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid var(--el-border-color-lighter); }
.role-authorization__header h3 { margin: 0 0 4px; }
.role-authorization__header span { color: var(--el-text-color-secondary); font-size: 13px; }
.role-authorization__copy { display: flex; gap: 8px; }
.role-authorization__copy .el-select { width: 220px; }
.role-authorization__tabs { flex: 1; min-height: 0; overflow: auto; }
.permission-actions { display: flex; flex-wrap: wrap; gap: 8px 24px; }
.field-code { margin-left: 6px; color: var(--el-text-color-secondary); font-size: 12px; }
.data-scopes { display: flex; flex-direction: column; align-items: flex-start; gap: 16px; padding: 20px; }
.department-tree { max-width: 520px; margin: 0 20px; padding: 16px; border: 1px solid var(--el-border-color-lighter); }
@media (max-width: 900px) { .role-authorization { grid-template-columns: 210px minmax(0, 1fr); } .role-authorization__header { align-items: flex-start; flex-direction: column; } }
</style>
