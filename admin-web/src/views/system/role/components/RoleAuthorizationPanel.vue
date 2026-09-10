<template>
  <div v-loading="loading" class="role-authorization-panel">
    <div class="role-authorization-panel__toolbar">
      <span>直接授权可编辑，继承授权只读并显示来源</span>
      <div class="flex gap-2">
        <el-select v-model="copySourceRoleId" placeholder="选择授权来源角色" filterable clearable>
          <el-option v-for="role in flatRoles" :key="role.id" :label="role.name" :value="role.id" />
        </el-select>
        <el-button :disabled="!copySourceRoleId" :loading="copying" v-perm="'system:role:perm-copy'" @click="copyAuthorization">复制授权</el-button>
      </div>
    </div>

    <div v-if="section === 'permissions'">
        <el-collapse v-model="expandedGroups">
          <el-collapse-item v-for="group in permissionGroups" :key="group.id" :name="group.id">
            <template #title><strong>{{ group.name }}</strong></template>
            <el-table :data="group.resources" border>
              <el-table-column prop="name" label="资源" min-width="180" />
              <el-table-column label="动作" min-width="520">
                <template #default="{ row: resource }">
                  <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <el-tooltip v-for="action in resource.actions" :key="action.id" :content="inheritanceText(action.inheritedFrom)" :disabled="!action.inherited">
                      <el-checkbox :model-value="action.direct || action.inherited" :disabled="action.inherited" @change="setPermission(action, $event)">
                        {{ action.name }}<el-tag v-if="action.inherited" size="small" type="info">继承</el-tag>
                      </el-checkbox>
                    </el-tooltip>
                  </div>
                </template>
              </el-table-column>
            </el-table>
          </el-collapse-item>
        </el-collapse>
    </div>
    <div v-else-if="section === 'fields'">
        <el-table :data="fields" border row-key="id">
          <el-table-column prop="resource" label="资源" min-width="180" />
          <el-table-column prop="name" label="字段" min-width="180"><template #default="{ row }">{{ row.name }} <span class="field-code">{{ row.field }}</span></template></el-table-column>
          <el-table-column label="查看" width="150" align="center"><template #default="{ row }"><el-checkbox :model-value="row.view || row.inheritedView" :disabled="row.inheritedView" @change="setFieldView(row as FieldPermission, $event)">查看</el-checkbox></template></el-table-column>
          <el-table-column label="编辑" width="150" align="center"><template #default="{ row }"><el-checkbox :model-value="row.edit || row.inheritedEdit" :disabled="row.inheritedEdit" @change="setFieldEdit(row as FieldPermission, $event)">编辑</el-checkbox></template></el-table-column>
        </el-table>
        <el-empty v-if="!fields.length" description="暂无已登记的字段白名单" />
    </div>
    <div v-else>
        <el-radio-group v-model="dataScope" class="data-scopes">
          <el-radio value="all">全部数据</el-radio><el-radio value="dept_and_children">本部门及下级</el-radio><el-radio value="dept">本部门</el-radio><el-radio value="self">仅本人</el-radio><el-radio value="custom">自定义部门</el-radio>
        </el-radio-group>
        <el-tree v-if="dataScope === 'custom'" ref="departmentTreeRef" :data="departmentTree" node-key="id" :props="{ label: 'name', children: 'children' }" show-checkbox default-expand-all check-strictly />
    </div>
    <div class="role-authorization-panel__footer"><el-button type="primary" :loading="saving" v-perm="'system:role:perm'" @click="save">保存整套授权</el-button></div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { ElMessageBox } from 'element-plus';
import { roleApi, type AuthorizationSource, type AuthorizationTreeNode, type DataScope, type FieldPermission, type PermissionAction, type PermissionGroup } from '@/api/system/role';
import { normalizeFieldGrant, toggleFieldEdit, toggleFieldView } from '../roleAuthorization';

const props = defineProps<{ roleId?: number; section: 'permissions' | 'fields' | 'data' }>();
const emit = defineEmits<{ success: [] }>();
const loading = ref(false); const saving = ref(false); const copying = ref(false);
const roles = ref<AuthorizationTreeNode[]>([]); const permissionGroups = ref<PermissionGroup[]>([]); const fields = ref<FieldPermission[]>([]);
const dataScope = ref<DataScope>('self'); const departmentTree = ref<AuthorizationTreeNode[]>([]); const departmentIds = ref<number[]>([]); const expandedGroups = ref<number[]>([]); const copySourceRoleId = ref<number>();
const departmentTreeRef = ref<{ setCheckedKeys: (keys: number[]) => void; getCheckedKeys: () => Array<number | string> }>();
const flatRoles = computed(() => {
  const result: AuthorizationTreeNode[] = [];
  const walk = (items: AuthorizationTreeNode[]) => items.forEach((item) => { result.push(item); walk(item.children || []); });
  walk(roles.value);
  return result.filter((role) => role.id !== props.roleId);
});

watch(() => props.roleId, (id) => { if (id) void load(id); }, { immediate: true });
async function load(id: number) {
  loading.value = true;
  try {
    const authorization = await roleApi.authorization(id);
    roles.value = authorization.roles; permissionGroups.value = authorization.permissionGroups; fields.value = authorization.fields;
    dataScope.value = authorization.dataScope; departmentIds.value = authorization.departmentIds; departmentTree.value = authorization.departmentTree;
    expandedGroups.value = authorization.permissionGroups.map((group) => group.id);
    await nextTick(); departmentTreeRef.value?.setCheckedKeys(departmentIds.value);
  } finally { loading.value = false; }
}
function setPermission(action: PermissionAction, checked: unknown) { if (!action.inherited) action.direct = Boolean(checked); }
function setFieldView(field: FieldPermission, checked: unknown) { Object.assign(field, toggleFieldView(field, Boolean(checked))); }
function setFieldEdit(field: FieldPermission, checked: unknown) { Object.assign(field, toggleFieldEdit(field, Boolean(checked))); }
function inheritanceText(sources: AuthorizationSource[]) { return sources.length ? `继承自：${sources.map((source) => source.roleName).join('、')}` : ''; }
async function save() {
  if (!props.roleId) return; saving.value = true;
  try {
    const permissionIds = permissionGroups.value.flatMap((group) => group.resources).flatMap((resource) => resource.actions).filter((action) => action.direct).map((action) => action.id);
    const fieldPermissions = fields.value.map((field) => ({ fieldId: field.id, ...normalizeFieldGrant(field) })).filter((field) => field.view || field.edit);
    const checked = dataScope.value === 'custom' ? (departmentTreeRef.value?.getCheckedKeys() || []).map(Number) : [];
    await roleApi.saveAuthorization(props.roleId, { permissionIds, fieldPermissions, dataScope: dataScope.value, departmentIds: checked }); emit('success'); await load(props.roleId);
  } finally { saving.value = false; }
}
async function copyAuthorization() {
  if (!props.roleId || !copySourceRoleId.value) return;
  await ElMessageBox.confirm('确认复制所选角色的整套授权吗？', '复制授权', { type: 'warning' }); copying.value = true;
  try { await roleApi.copyAuthorization(props.roleId, copySourceRoleId.value); await load(props.roleId); emit('success'); } finally { copying.value = false; }
}
</script>

<style scoped>
.role-authorization-panel { min-height: 420px; display: flex; flex-direction: column; }
.role-authorization-panel__toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--el-border-color-lighter); color: var(--el-text-color-secondary); }
.role-authorization-panel__toolbar .el-select { width: 220px; }
.role-authorization-panel__footer { display: flex; justify-content: flex-end; padding-top: 16px; }
.data-scopes { display: flex; flex-direction: column; align-items: flex-start; gap: 16px; padding: 20px; }
.field-code { margin-left: 6px; color: var(--el-text-color-secondary); font-size: 12px; }
</style>
