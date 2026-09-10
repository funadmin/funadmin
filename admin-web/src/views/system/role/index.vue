<template>
  <PageWrapper title="角色管理" subtitle="维护角色层级、基本信息与授权">
    <template #extra>
      <div class="role-toolbar">
        <el-button type="primary" v-perm="'system:role:add'" @click="startCreate()"><i class="i-ep-plus" /> 添加顶级角色</el-button>
        <el-input v-model="keyword" placeholder="搜索角色" clearable class="role-toolbar__search" />
        <el-button @click="setExpanded(true)"><i class="i-ep-expand" /> 展开全部</el-button>
        <el-button @click="setExpanded(false)"><i class="i-ep-fold" /> 折叠全部</el-button>
        <el-button :loading="loading" @click="loadRoles(activeRole?.id)"><i class="i-ep-refresh" /> 刷新</el-button>
      </div>
    </template>

    <div class="role-workspace">
      <aside class="role-workspace__tree">
        <el-tree ref="treeRef" :data="filteredTree" node-key="id" :props="{ label: 'name', children: 'children' }" highlight-current default-expand-all :current-node-key="activeRole?.id" @node-click="selectRole" @node-contextmenu="openContextMenu">
          <template #default="{ data }"><span class="role-node"><span>{{ data.name }}</span><el-tag :type="data.status === 1 ? 'success' : 'info'" size="small">{{ data.status === 1 ? '启用' : '禁用' }}</el-tag></span></template>
        </el-tree>
      </aside>

      <main class="role-workspace__detail">
        <el-empty v-if="!activeRole && mode === 'view'" description="请选择角色或添加顶级角色" />
        <template v-else>
          <header class="role-detail__header">
            <div><h3>{{ mode === 'create' ? '新增角色' : activeRole?.name }}</h3><span>{{ mode === 'edit' ? '正在编辑基本信息' : '角色配置工作区' }}</span></div>
            <el-button v-if="activeRole && mode === 'view'" v-perm="'system:role:edit'" @click="startEdit(activeRole)">修改角色</el-button>
          </header>
          <el-tabs v-model="activeTab">
            <el-tab-pane label="基本信息" name="basic">
              <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" class="role-form">
                <el-row :gutter="16"><el-col :span="12"><el-form-item label="名称" prop="name"><el-input v-model="form.name" :disabled="mode === 'view'" /></el-form-item></el-col><el-col :span="12"><el-form-item label="标识" prop="code"><el-input v-model="form.code" :disabled="mode !== 'create'" /></el-form-item></el-col></el-row>
                <el-row :gutter="16"><el-col :span="12"><el-form-item label="角色等级" prop="level"><el-input-number v-model="form.level" :disabled="mode === 'view'" :min="1" :max="9999" class="w-full" /></el-form-item></el-col><el-col :span="12"><el-form-item label="状态"><el-radio-group v-model="form.status" :disabled="mode === 'view'"><el-radio-button :value="1">启用</el-radio-button><el-radio-button :value="0">禁用</el-radio-button></el-radio-group></el-form-item></el-col></el-row>
                <el-form-item label="主父级" prop="parentId"><el-tree-select v-model="form.parentId" :data="parentOptions" :props="{ label: 'name', children: 'children' }" node-key="id" check-strictly clearable :disabled="mode === 'view'" placeholder="顶级角色" class="w-full" /><div class="form-tip">主父级决定角色树中的位置，可在新增子角色后调整</div></el-form-item>
                <el-form-item label="额外继承"><el-tree-select v-model="form.parentRoleIds" :data="additionalParentOptions" :props="{ label: 'name', children: 'children' }" node-key="id" multiple show-checkbox check-strictly clearable :disabled="mode === 'view'" class="w-full" /></el-form-item>
                <el-form-item label="数据范围" prop="dataScope"><el-select v-model="form.dataScope" :disabled="mode === 'view'" class="w-full"><el-option label="全部数据" value="all" /><el-option label="本部门及下级" value="dept_and_children" /><el-option label="本部门" value="dept" /><el-option label="仅本人" value="self" /><el-option label="自定义部门" value="custom" :disabled="!can('system:dept:list')" /></el-select></el-form-item>
                <el-form-item v-if="form.dataScope === 'custom'" label="自定义部门" prop="departmentIds"><div class="w-full"><el-alert v-if="!can('system:dept:list')" title="无部门查看权限，无法配置自定义部门" type="warning" :closable="false" show-icon class="mb-2" /><el-tree-select v-model="form.departmentIds" :data="departmentOptions" :props="{ label: 'name', children: 'children' }" node-key="id" multiple show-checkbox check-strictly collapse-tags collapse-tags-tooltip clearable :disabled="mode === 'view' || !can('system:dept:list')" :loading="departmentsLoading" placeholder="请选择部门" class="w-full" /></div></el-form-item>
                <el-form-item label="备注"><el-input v-model="form.remark" type="textarea" :rows="3" :disabled="mode === 'view'" /></el-form-item>
                <div v-if="mode !== 'view'" class="role-form__actions"><el-button @click="cancelEdit">取消</el-button><el-button type="primary" :loading="saving" v-perm="mode === 'create' ? 'system:role:add' : 'system:role:edit'" @click="saveRole">保存</el-button></div>
              </el-form>
            </el-tab-pane>
            <el-tab-pane v-if="canManageAuthorization" label="功能权限" name="permissions">
              <el-alert v-if="authorizationError" :title="authorizationError" type="error" :closable="false" show-icon><el-button link type="primary" @click="reloadAuthorization">重新加载</el-button></el-alert>
              <RoleAuthorizationPanel v-if="activeRole && authorization" :role-id="activeRole.id" section="permissions" :authorization="authorization" @reload="reloadAuthorization" />
            </el-tab-pane>
            <el-tab-pane v-if="canManageAuthorization" label="字段权限" name="fields">
              <RoleAuthorizationPanel v-if="activeRole && authorization" :role-id="activeRole.id" section="fields" :authorization="authorization" @reload="reloadAuthorization" />
            </el-tab-pane>
            <el-tab-pane v-if="canManageAuthorization" label="数据授权" name="data">
              <RoleAuthorizationPanel v-if="activeRole && authorization" :role-id="activeRole.id" section="data" :authorization="authorization" @reload="reloadAuthorization" />
            </el-tab-pane>
            <el-tab-pane v-if="canManageAuthorization" label="继承关系" name="inheritance">
              <div class="inheritance-info">
                <section><h4>主父级</h4><el-tag v-if="primaryParent" type="primary">{{ primaryParent.roleName }}</el-tag><span v-else>顶级角色</span></section>
                <section><h4>额外父级</h4><div v-if="additionalParents.length" class="inheritance-tags"><el-tag v-for="parent in additionalParents" :key="parent.roleId" type="info">{{ parent.roleName }}</el-tag></div><span v-else>无</span></section>
                <section><h4>有效祖先链 / 继承来源</h4><el-timeline v-if="inheritanceAncestors.length"><el-timeline-item v-for="ancestor in inheritanceAncestors" :key="ancestor.roleId"><strong>{{ ancestor.roleName }}</strong><span class="inheritance-source">来源：{{ ancestor.sourceRoleNames.join('、') }}</span></el-timeline-item></el-timeline><span v-else>无传递祖先</span></section>
              </div>
            </el-tab-pane>
          </el-tabs>
        </template>
      </main>
    </div>

    <Teleport to="body"><div v-if="contextVisible" class="role-context-menu" :style="{ left: `${contextPosition.x}px`, top: `${contextPosition.y}px` }" @click.stop>
      <button @click="runContext('detail')">查看详情</button><button v-if="can('system:role:edit')" @click="runContext('edit')">修改角色</button><button v-if="can('system:role:add')" @click="runContext('add')">添加子角色</button><button v-if="can('system:role:perm')" data-context-action="permission" @click="runContext('permission')">配置权限</button><button v-if="can('system:role:perm') && can('system:role:perm-copy')" @click="runContext('copy')">复制授权</button><button v-if="can('system:role:edit')" @click="runContext('status')">{{ contextRole?.status === 1 ? '禁用' : '启用' }}</button><button v-if="can('system:role:delete')" class="danger" @click="runContext('delete')">删除</button>
    </div></Teleport>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { ElMessageBox, type FormInstance, type FormRules } from 'element-plus';
import { roleApi, type DataScope, type RoleAuthorization, type RoleModel } from '@/api/system/role';
import { deptApi, type DeptModel } from '@/api/system/dept';
import { useUserStore } from '@/store/modules/user';
import { additionalParentRoleIds, childRoleLevel, filterRoleTree, parentRoleOptions, roleTree, treeParentValue, type RoleTreeNode } from './roleHierarchy';
import RoleAuthorizationPanel from './components/RoleAuthorizationPanel.vue';

defineOptions({ name: 'SystemRole' });
type Mode = 'view' | 'create' | 'edit';
const userStore = useUserStore(); const loading = ref(false); const saving = ref(false); const departmentsLoading = ref(false); const roles = ref<RoleModel[]>([]); const departmentOptions = ref<DeptModel[]>([]); const keyword = ref('');
const activeRole = ref<RoleModel>(); const mode = ref<Mode>('view'); const activeTab = ref('basic'); const authorization = ref<RoleAuthorization>(); const authorizationError = ref(''); let authorizationRequest: Promise<RoleAuthorization> | undefined; const treeRef = ref<{ setCurrentKey: (key?: number) => void; store?: { nodesMap?: Record<string, { expanded: boolean }> } }>(); const formRef = ref<FormInstance>();
const contextVisible = ref(false); const contextRole = ref<RoleModel>(); const contextPosition = reactive({ x: 0, y: 0 });
const initialForm = () => ({ name: '', code: '', level: 1, dataScope: 'self' as DataScope, status: 1 as 0 | 1, remark: '', parentId: null as number | null, parentRoleIds: [] as number[], departmentIds: [] as number[] });
const form = reactive(initialForm());
const rules: FormRules = { name: [{ required: true, message: '请输入名称', trigger: 'blur' }], code: [{ required: true, message: '请输入标识', trigger: 'blur' }, { pattern: /^[a-zA-Z][a-zA-Z0-9_]*$/, message: '需以英文开头，仅支持字母、数字和下划线', trigger: 'blur' }], level: [{ required: true, message: '请输入角色等级', trigger: 'change' }], dataScope: [{ required: true, message: '请选择数据范围', trigger: 'change' }], departmentIds: [{ validator: (_rule, value: number[], callback) => { if (form.dataScope === 'custom' && (!value || value.length === 0)) callback(new Error('请选择自定义部门')); else callback(); }, trigger: 'change' }] };
const filteredTree = computed(() => filterRoleTree(roleTree(roles.value), (role) => !keyword.value.trim() || `${role.name} ${role.code}`.toLowerCase().includes(keyword.value.trim().toLowerCase())));
const canManageAuthorization = computed(() => can('system:role:perm'));
const parentOptions = computed(() => parentRoleOptions(roles.value, mode.value === 'edit' ? activeRole.value?.id : undefined));
const additionalParentOptions = computed(() => parentOptions.value.filter((role) => role.id !== form.parentId));
const primaryParent = computed(() => authorization.value?.inheritance?.directParents.find((parent) => parent.relation === 'primary'));
const additionalParents = computed(() => authorization.value?.inheritance?.directParents.filter((parent) => parent.relation === 'additional') || []);
const inheritanceAncestors = computed(() => authorization.value?.inheritance?.ancestors || []);
watch(() => [form.parentId, ...form.parentRoleIds], () => { const ids = additionalParentRoleIds(form.parentRoleIds, form.parentId); if (ids !== form.parentRoleIds) form.parentRoleIds = ids; if (mode.value !== 'view') form.level = childRoleLevel([form.parentId, ...ids].filter((id): id is number => typeof id === 'number' && id > 0), roles.value, form.level); });
watch(() => form.dataScope, (scope) => { if (scope !== 'custom') form.departmentIds = []; if (can('system:dept:list') && mode.value !== 'view' && scope === 'custom') void loadDepartments(); });
watch(activeTab, (tab) => { if (activeRole.value && (tab === 'inheritance' || ['permissions', 'fields', 'data'].includes(tab))) void ensureAuthorization(activeRole.value.id); });
function can(permission: string) { return userStore.permissions.some((item) => item === '*' || item === '*:*:*' || item === permission); }
function fillForm(role?: RoleModel) { Object.assign(form, initialForm()); if (role) Object.assign(form, { ...role, parentId: treeParentValue(role.parentId), parentRoleIds: [...(role.parentRoleIds || [])], departmentIds: [...(role.departmentIds || [])] }); }
async function loadRoles(selectedId?: number) { loading.value = true; try { roles.value = await roleApi.all(); const selected = roles.value.find((role) => role.id === selectedId) || roles.value[0]; activeRole.value = selected; resetAuthorization(); if (mode.value === 'view') fillForm(selected); treeRef.value?.setCurrentKey(selected?.id); } finally { loading.value = false; } }
function resetAuthorization() { authorization.value = undefined; authorizationError.value = ''; authorizationRequest = undefined; }
async function ensureAuthorization(roleId: number) {
  if (authorization.value?.roleId === roleId) return;
  const request = authorizationRequest ??= roleApi.authorization(roleId);
  try {
    const detail = await request;
    if (authorizationRequest !== request || activeRole.value?.id !== roleId) return;
    authorization.value = detail;
    authorizationError.value = '';
  } catch (error) {
    if (authorizationRequest !== request || activeRole.value?.id !== roleId) return;
    authorizationRequest = undefined;
    authorizationError.value = error instanceof Error ? error.message : '授权详情加载失败';
  }
}
async function reloadAuthorization() { if (!activeRole.value) return; resetAuthorization(); await ensureAuthorization(activeRole.value.id); }
async function loadDepartments() { if (!can('system:dept:list') || departmentsLoading.value || departmentOptions.value.length > 0) return; departmentsLoading.value = true; try { departmentOptions.value = await deptApi.tree(); } finally { departmentsLoading.value = false; } }
function selectRole(role: RoleTreeNode) { activeRole.value = role; mode.value = 'view'; activeTab.value = 'basic'; resetAuthorization(); fillForm(role); contextVisible.value = false; }
function startCreate(parent?: RoleModel) { mode.value = 'create'; activeTab.value = 'basic'; activeRole.value = undefined; Object.assign(form, initialForm(), { parentId: parent?.id ?? 0, level: parent ? parent.level + 1 : 1 }); }
function startEdit(role: RoleModel) { activeRole.value = role; mode.value = 'edit'; activeTab.value = 'basic'; fillForm(role); if (can('system:dept:list') && form.dataScope === 'custom') void loadDepartments(); }
function cancelEdit() { mode.value = 'view'; fillForm(activeRole.value); }
async function saveRole() { await formRef.value?.validate(); saving.value = true; try { const payload = { ...form, parentId: form.parentId ?? 0, parentRoleIds: [...form.parentRoleIds], departmentIds: [...form.departmentIds] }; const saved = mode.value === 'edit' && activeRole.value ? await roleApi.update(activeRole.value.id, payload) : await roleApi.create(payload); mode.value = 'view'; await loadRoles(saved.id); } finally { saving.value = false; } }
function setExpanded(expanded: boolean) { Object.values(treeRef.value?.store?.nodesMap || {}).forEach((node) => { node.expanded = expanded; }); }
function openContextMenu(event: Event, role: RoleTreeNode) { event.preventDefault(); const mouseEvent = event as MouseEvent; contextRole.value = role; contextPosition.x = Math.min(mouseEvent.clientX, window.innerWidth - 180); contextPosition.y = Math.min(mouseEvent.clientY, window.innerHeight - 300); contextVisible.value = true; }
async function removeRole(role: RoleModel) { await ElMessageBox.confirm(`确认删除角色 ${role.name}？此操作不可恢复`, '删除确认', { type: 'warning' }); await roleApi.remove(role.id); mode.value = 'view'; await loadRoles(); }
async function toggleStatus(role: RoleModel) { await ElMessageBox.confirm(`确认${role.status === 1 ? '禁用' : '启用'}角色 ${role.name}？`, '状态确认', { type: 'warning' }); const saved = await roleApi.update(role.id, { ...role, status: role.status === 1 ? 0 : 1 }); await loadRoles(saved.id); }
function runContext(action: 'detail' | 'edit' | 'add' | 'permission' | 'copy' | 'status' | 'delete') { const role = contextRole.value; contextVisible.value = false; if (!role) return; if (action === 'detail') selectRole(role as RoleTreeNode); else if (action === 'edit') startEdit(role); else if (action === 'add') startCreate(role); else if (action === 'permission' || action === 'copy') { activeRole.value = role; mode.value = 'view'; resetAuthorization(); activeTab.value = 'permissions'; fillForm(role); void ensureAuthorization(role.id); } else if (action === 'status') void toggleStatus(role); else void removeRole(role); }
function closeContextMenu() { contextVisible.value = false; }
onMounted(() => { document.addEventListener('click', closeContextMenu); void loadRoles(); }); onBeforeUnmount(() => document.removeEventListener('click', closeContextMenu));
</script>

<style scoped>
.role-toolbar { display: flex; flex-wrap: wrap; gap: 8px; }.role-toolbar__search { width: 220px; }.role-workspace { display: grid; grid-template-columns: 300px minmax(0, 1fr); min-height: calc(100vh - 190px); border: 1px solid var(--el-border-color-lighter); border-radius: 8px; overflow: hidden; }.role-workspace__tree { padding: 16px; overflow: auto; border-right: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-lighter); }.role-workspace__detail { min-width: 0; padding: 0 20px 20px; overflow: auto; }.role-node { display: flex; width: 100%; justify-content: space-between; align-items: center; padding-right: 8px; }.role-detail__header { display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid var(--el-border-color-lighter); }.role-detail__header h3 { margin: 0 0 4px; }.role-detail__header span,.form-tip { color: var(--el-text-color-secondary); font-size: 12px; }.role-form { max-width: 820px; padding-top: 20px; }.role-form__actions { display: flex; justify-content: flex-end; gap: 8px; }.inheritance-info { display: grid; gap: 20px; padding: 20px; }.inheritance-info h4 { margin: 0 0 10px; }.inheritance-tags { display: flex; flex-wrap: wrap; gap: 8px; }.inheritance-source { margin-left: 12px; color: var(--el-text-color-secondary); }.role-context-menu { position: fixed; z-index: 4000; width: 168px; padding: 6px; border: 1px solid var(--el-border-color); border-radius: 6px; background: var(--el-bg-color-overlay); box-shadow: var(--el-box-shadow-light); }.role-context-menu button { display: block; width: 100%; padding: 8px 12px; border: 0; background: transparent; text-align: left; cursor: pointer; }.role-context-menu button:hover { background: var(--el-fill-color-light); }.role-context-menu .danger { color: var(--el-color-danger); }@media (max-width: 900px) { .role-workspace { grid-template-columns: 220px minmax(0, 1fr); } }
</style>
