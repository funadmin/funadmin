<template>
  <PageWrapper title="后台管理" subtitle="Webman 基础管理：管理员、角色、功能权限和菜单">
    <el-card shadow="never">
      <el-tabs v-model="resource" @tab-change="changeResource">
        <el-tab-pane v-for="tab in availableTabs" :key="tab.key" :label="tab.label" :name="tab.key" />
      </el-tabs>
      <div class="management-toolbar">
        <el-input v-model="keyword" placeholder="搜索账号或名称" clearable @keyup.enter="search" />
        <el-button :loading="loading" @click="search">查询</el-button>
        <el-button v-if="isSuper" type="primary" @click="open()">新增{{ resourceLabel }}</el-button>
      </div>
      <el-alert v-if="!isSuper" title="当前为只读权限，授权配置由超级管理员维护。" type="info" :closable="false" />
      <el-table v-loading="loading" :data="rows" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column :prop="resource === 'users' ? 'username' : 'name'" :label="resource === 'users' ? '账号' : '名称'" min-width="140" />
        <el-table-column v-if="resource === 'users'" prop="nickname" label="昵称" min-width="140" />
        <el-table-column v-if="resource === 'roles' || resource === 'permissions'" prop="code" label="标识" min-width="180" />
        <el-table-column v-if="resource === 'menus'" prop="path" label="路径" min-width="160" />
        <el-table-column v-if="resource === 'menus'" prop="permission" label="所需权限" min-width="180" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }"><el-tag :type="row.status ? 'success' : 'info'">{{ row.status ? '启用' : '停用' }}</el-tag></template>
        </el-table-column>
        <el-table-column v-if="isSuper" label="操作" width="160">
          <template #default="{ row }">
            <template v-if="!row.is_super_admin">
              <el-button link type="primary" @click="open(row as ManagementRow)">编辑</el-button>
              <el-button link type="danger" @click="remove(row as ManagementRow)">删除</el-button>
            </template>
            <span v-else>CLI 维护</span>
          </template>
        </el-table-column>
      </el-table>
      <div class="app-pagination-bar">
        <el-pagination v-model:current-page="page" :page-size="20" :total="total" layout="total, prev, pager, next" @current-change="load" />
      </div>
    </el-card>
    <el-dialog v-model="visible" :title="`${editing ? '编辑' : '新增'}${resourceLabel}`" width="560px" :close-on-click-modal="false">
      <el-form label-width="100px" @submit.prevent>
        <template v-if="resource === 'users'">
          <el-form-item label="账号" required><el-input v-model="form.username" maxlength="64" /></el-form-item>
          <el-form-item label="昵称"><el-input v-model="form.nickname" maxlength="100" /></el-form-item>
          <el-form-item label="邮箱"><el-input v-model="form.email" maxlength="100" /></el-form-item>
          <el-form-item label="手机"><el-input v-model="form.mobile" maxlength="30" /></el-form-item>
          <el-form-item :label="editing ? '新密码' : '密码'" :required="!editing"><el-input v-model="form.password" type="password" show-password placeholder="12～72 字节；编辑时留空不修改" /></el-form-item>
          <el-form-item label="角色"><el-select v-model="form.roleIds" multiple filterable class="w-full"><el-option v-for="role in roleOptions" :key="role.id" :label="role.name" :value="role.id" /></el-select></el-form-item>
        </template>
        <template v-else>
          <el-form-item label="名称" required><el-input v-model="form.name" maxlength="100" /></el-form-item>
          <el-form-item v-if="resource !== 'menus'" label="标识" required><el-input v-model="form.code" placeholder="如 system:user:list" maxlength="100" /></el-form-item>
          <el-form-item v-if="resource === 'roles'" label="功能权限"><el-select v-model="form.permissionIds" multiple filterable class="w-full"><el-option v-for="permission in permissionOptions" :key="permission.id" :label="`${permission.name} (${permission.code})`" :value="permission.id" /></el-select></el-form-item>
          <template v-if="resource === 'menus'">
            <el-form-item label="路径" required><el-input v-model="form.path" placeholder="如 /management" /></el-form-item>
            <el-form-item label="路由名称" required><el-input v-model="form.routeName" placeholder="如 WebmanManagement" /></el-form-item>
            <el-form-item label="绑定权限" required><el-select v-model="form.permission" filterable class="w-full"><el-option v-for="permission in permissionOptions" :key="permission.id" :label="permission.code" :value="permission.code || ''" /></el-select></el-form-item>
            <el-alert title="当前菜单指向本管理页面；其他业务页面按后续迁移批次接入。" :closable="false" />
          </template>
        </template>
        <el-form-item label="状态"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="visible = false">取消</el-button><el-button type="primary" :loading="saving" @click="save">保存</el-button></template>
    </el-dialog>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { managementApi, type ManagementResource, type ManagementRow } from '@/api/webman/management';
import { useUserStore } from '@/store/modules/user';

const user = useUserStore();
const isSuper = computed(() => user.roles.includes('super_admin'));
const tabs: Array<{ key: ManagementResource; label: string }> = [
  { key: 'users', label: '管理员' }, { key: 'roles', label: '角色' }, { key: 'permissions', label: '权限' }, { key: 'menus', label: '菜单' }
];
const availableTabs = computed(() => tabs.filter((tab) => isSuper.value || user.permissions.includes(`system:${({ users: 'user', roles: 'role', permissions: 'permission', menus: 'menu' } as const)[tab.key]}:list`)));
const resource = ref<ManagementResource>(availableTabs.value[0]?.key || 'users');
const resourceLabel = computed(() => tabs.find((tab) => tab.key === resource.value)?.label || '');
const rows = ref<ManagementRow[]>([]);
const roleOptions = ref<ManagementRow[]>([]);
const permissionOptions = ref<ManagementRow[]>([]);
const keyword = ref('');
const page = ref(1);
const total = ref(0);
const loading = ref(false);
const saving = ref(false);
const visible = ref(false);
const editing = ref<number | null>(null);
const initial = () => ({ username: '', nickname: '', email: '', mobile: '', password: '', name: '', code: '', path: '', routeName: '', permission: '', status: 1, roleIds: [] as number[], permissionIds: [] as number[] });
const form = reactive(initial());
let loadVersion = 0;

async function load() {
  const version = ++loadVersion;
  loading.value = true;
  try {
    const result = await managementApi.list(resource.value, { page: page.value, keyword: keyword.value });
    if (version !== loadVersion) return;
    rows.value = result.list;
    total.value = result.total;
  } catch { if (version === loadVersion) rows.value = []; }
  finally { if (version === loadVersion) loading.value = false; }
}
function search() { page.value = 1; void load(); }
function changeResource() { keyword.value = ''; search(); }
async function options(target: ManagementResource) {
  const result: ManagementRow[] = [];
  for (let current = 1; ; current++) {
    const batch = await managementApi.list(target, { page: current, pageSize: 100 });
    result.push(...batch.list);
    if (result.length >= batch.total || !batch.list.length) return result;
  }
}
async function open(row?: ManagementRow) {
  Object.assign(form, initial(), row || {});
  form.password = '';
  editing.value = row?.id || null;
  try {
    if (resource.value === 'users') roleOptions.value = await options('roles');
    if (resource.value === 'roles' || resource.value === 'menus') permissionOptions.value = await options('permissions');
    visible.value = true;
  } catch { /* 请求层显示错误，不打开缺少授权选项的表单。 */ }
}
async function save() {
  const target = resource.value;
  let payload: Record<string, unknown> = { status: form.status };
  if (target === 'users') {
    payload = { ...payload, username: form.username, nickname: form.nickname, email: form.email, mobile: form.mobile, roleIds: [...form.roleIds] };
    if (form.password || !editing.value) payload.password = form.password;
  } else if (target === 'menus') {
    Object.assign(payload, { name: form.name, path: form.path, routeName: form.routeName, permission: form.permission });
  } else {
    Object.assign(payload, { name: form.name, code: form.code });
    if (target === 'roles') payload.permissionIds = [...form.permissionIds];
  }
  saving.value = true;
  try {
    if (editing.value) await managementApi.update(target, editing.value, payload);
    else await managementApi.create(target, payload);
    visible.value = false;
    ElMessage.success('保存成功');
    await load();
  } catch { /* 请求层显示验证错误。 */ }
  finally { saving.value = false; }
}
async function remove(row: ManagementRow) {
  try {
    await ElMessageBox.confirm('确认删除该记录？', '删除确认', { type: 'warning' });
    await managementApi.remove(resource.value, row.id);
    await load();
  } catch { /* 取消操作或请求层已显示错误。 */ }
}
onMounted(load);
</script>

<style scoped>
.management-toolbar { display: flex; gap: 12px; margin-bottom: 16px; }
.management-toolbar .el-input { max-width: 280px; }
</style>
