<template>
  <PageWrapper :title="t('enterpriseApplications.title')" :subtitle="t('enterpriseApplications.subtitle')">
    <div class="statistics mb-4 grid grid-cols-3 gap-3">
      <el-card><el-statistic title="全部应用" :value="statistics.total" /></el-card>
      <el-card><el-statistic title="已发布" :value="statistics.published" /></el-card>
      <el-card><el-statistic title="草稿/禁用" :value="statistics.inactive" /></el-card>
    </div>
    <div class="mb-4 flex gap-2">
      <el-input v-model="keyword" clearable placeholder="搜索应用名称或标识" class="max-w-80" @keyup.enter="load" />
      <el-button type="primary" @click="load">搜索</el-button>
      <el-button type="success" @click="openCreate">新建应用</el-button>
      <el-radio-group v-model="viewMode"><el-radio-button value="card">卡片</el-radio-button><el-radio-button value="list">列表</el-radio-button></el-radio-group>
    </div>
    <div v-loading="loading" :class="viewMode === 'card' ? 'grid gap-4 md:grid-cols-2 xl:grid-cols-3' : 'space-y-3'">
      <el-card v-for="item in applications" :key="item.id">
        <template #header><div class="flex items-center justify-between"><strong>{{ item.name }}</strong><el-tag>{{ item.status }}</el-tag></div></template>
        <p>{{ item.description || '-' }}</p><p class="text-sm text-gray-500">{{ item.code }} · {{ item.runtimeType }}</p>
        <div class="mt-4 flex flex-wrap gap-2"><el-button type="primary" :disabled="!canLaunchApplication(item)" @click="launch(item)">进入应用</el-button><el-button @click="openSettings(item)">设置</el-button><el-button v-if="item.status === 'draft'" type="success" @click="publish(item)">发布</el-button><el-button v-if="item.status === 'published'" type="warning" @click="disable(item)">停用</el-button><el-button v-if="item.status !== 'published'" type="danger" @click="remove(item)">删除</el-button></div>
      </el-card>
    </div>
    <el-drawer v-model="drawerVisible" :title="selectedId ? '应用设置' : '新建应用'" size="620px">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="基本信息" name="basic"><el-form label-width="110"><el-form-item label="名称"><el-input v-model="form.name" /></el-form-item><el-form-item label="标识"><el-input v-model="form.code" :disabled="selectedId > 0" /></el-form-item><el-form-item label="描述"><el-input v-model="form.description" type="textarea" /></el-form-item><el-form-item label="可见性"><el-select v-model="form.visibility"><el-option label="私有" value="private"/><el-option label="租户" value="tenant"/><el-option label="公开" value="public"/></el-select></el-form-item><el-form-item v-if="form.visibility === 'private'" label="所有者身份 ID"><el-input-number v-model="form.ownerIdentityUserId" :min="1" /></el-form-item><el-alert title="显式拒绝始终优先；私有仅所有者或显式用户允许可进入；租户与公开允许同租户已启用身份进入。" type="info" :closable="false"/></el-form></el-tab-pane>
        <el-tab-pane label="运行与数据" name="runtime"><el-form label-width="110"><el-form-item label="运行类型"><el-select v-model="form.runtimeType"><el-option label="内部" value="internal"/><el-option label="插件" value="plugin"/><el-option label="独立应用" value="standalone"/></el-select></el-form-item><el-form-item label="启动地址"><el-input v-model="form.launchUrl" /></el-form-item><el-form-item label="数据模式"><el-select v-model="database.mode"><el-option label="共享" value="shared"/><el-option label="独立" value="dedicated"/><el-option label="外部" value="external"/></el-select></el-form-item><el-form-item v-if="database.mode !== 'shared'" label="凭证引用"><el-input v-model="database.credentialRef" placeholder="vault://..." /></el-form-item><el-form-item label="健康路径"><el-input v-model="database.healthPath" placeholder="/health" /></el-form-item></el-form></el-tab-pane>
        <el-tab-pane label="域名" name="domains"><el-form label-width="120"><el-form-item label="Identity 回调"><el-input v-model="domain.identityCallback" @input="domainTouched = true" /></el-form-item><el-form-item label="Logout 回调"><el-input v-model="domain.logoutCallback" @input="domainTouched = true" /></el-form-item></el-form></el-tab-pane>
        <el-tab-pane label="访问范围" name="assignments"><el-alert title="支持全部、用户、部门、角色的 allow/deny；显式拒绝始终优先。私有应用只有 user allow 可额外授权。" type="info" :closable="false"/><el-form class="mt-3" label-width="100"><el-form-item label="主体"><el-select v-model="assignment.subjectType"><el-option label="全部" value="all"/><el-option label="用户" value="user"/><el-option label="部门" value="department"/><el-option label="角色" value="role"/></el-select></el-form-item><el-form-item v-if="assignment.subjectType !== 'all'" label="主体 ID"><el-input-number v-model="assignment.subjectId" :min="1" /></el-form-item><el-form-item label="效果"><el-radio-group v-model="assignment.effect"><el-radio value="allow">允许</el-radio><el-radio value="deny">拒绝</el-radio></el-radio-group></el-form-item></el-form></el-tab-pane>
        <el-tab-pane label="品牌" name="brand"><el-form label-width="100"><el-form-item label="Logo"><el-input v-model="form.logoUrl" /></el-form-item><el-form-item label="主色"><el-color-picker v-model="brandColor" /></el-form-item></el-form></el-tab-pane>
        <el-tab-pane label="OAuth" name="oauth"><el-empty description="OAuth 客户端配置将在后续阶段开放" /></el-tab-pane>
      </el-tabs>
      <template #footer><el-button @click="drawerVisible = false">取消</el-button><el-button type="primary" @click="saveSettings">保存</el-button></template>
    </el-drawer>
  </PageWrapper>
</template>
<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { applicationApi, serializeDomainChange, type ApplicationInput, type AssignmentInput, type DatabaseInput, type DomainInput, type EnterpriseApplication } from '@/api/identity/applications';
import { canLaunchApplication } from './applicationPolicy';
defineOptions({ name: 'EnterpriseApplicationCenter' });
const { t } = useI18n();
const applications = ref<EnterpriseApplication[]>([]); const loading = ref(false); const keyword = ref(''); const viewMode = ref<'card'|'list'>('card');
const drawerVisible = ref(false); const activeTab = ref('basic'); const selectedId = ref(0); const brandColor = ref('#409eff');
const domainTouched = ref(false); const expectedDomainIds = ref<number[]>([]);
const defaultForm = (): ApplicationInput => ({ code: '', name: '', description: '', runtimeType: 'internal', launchUrl: '', databaseMode: 'shared', visibility: 'tenant' });
const form = reactive<ApplicationInput>(defaultForm());
const database = reactive<DatabaseInput>({ mode: 'shared', credentialRef: '', healthPath: '' });
const domain = reactive<DomainInput>({ identityCallback: '', logoutCallback: '', domainType: 'web' });
const assignment = reactive<AssignmentInput>({ subjectType: 'all', effect: 'allow' });
const statistics = computed(() => ({ total: applications.value.length, published: applications.value.filter((item) => item.status === 'published').length, inactive: applications.value.filter((item) => item.status !== 'published').length }));
async function load() { loading.value = true; try { applications.value = (await applicationApi.list({ page: 1, pageSize: 100, keyword: keyword.value })).list; } finally { loading.value = false; } }
async function launch(item: EnterpriseApplication) {
  if (!canLaunchApplication(item)) return;
  try {
    const { launchUrl } = await applicationApi.launch(item.id);
    window.location.assign(launchUrl);
  } catch (caught) {
    const error = caught as { code?: number; msg?: string };
    if (error?.code === 403) ElMessage.error(error.msg || '当前账号无权进入该应用');
  }
}
function resetSettings() {
  Object.assign(form, defaultForm());
  Object.assign(database, { mode: 'shared', credentialRef: '', healthPath: '', credentialConfigured: false });
  Object.assign(domain, { id: undefined, identityCallback: '', logoutCallback: '', domainType: 'web' });
  Object.assign(assignment, { id: undefined, subjectType: 'all', subjectId: undefined, effect: 'allow' });
  brandColor.value = '#409eff'; domainTouched.value = false; expectedDomainIds.value = []; activeTab.value = 'basic';
}
function openCreate() { selectedId.value = 0; resetSettings(); drawerVisible.value = true; }
async function openSettings(item: EnterpriseApplication) {
  selectedId.value = item.id; resetSettings();
  Object.assign(form, { code: item.code, name: item.name, description: item.description, runtimeType: item.runtimeType, launchUrl: item.launchUrl, logoUrl: item.logoUrl, databaseMode: item.databaseMode, visibility: item.visibility, baseUrl: item.baseUrl, owner: item.owner, ownerIdentityUserId: item.ownerIdentityUserId });
  brandColor.value = typeof item.brandConfig.color === 'string' ? item.brandConfig.color : '#409eff';
  const [db, domains, assignments] = await Promise.all([applicationApi.database(item.id), applicationApi.domains(item.id), applicationApi.assignments(item.id)]);
  Object.assign(database, db); expectedDomainIds.value = domains.flatMap((entry) => entry.id === undefined ? [] : [entry.id]);
  if (domains[0]) Object.assign(domain, domains[0]);
  if (assignments[0]) Object.assign(assignment, assignments[0]);
  drawerVisible.value = true;
}
async function saveSettings() {
  const input = { ...form, databaseMode: database.mode, brandConfig: { ...form.brandConfig, color: brandColor.value } };
  const saved = selectedId.value ? await applicationApi.update(selectedId.value, input) : await applicationApi.create(input);
  selectedId.value = saved.id;
  const domainChange = serializeDomainChange(domain, expectedDomainIds.value, selectedId.value === saved.id && (domainTouched.value || expectedDomainIds.value.length > 0 || domain.identityCallback !== '' || domain.logoutCallback !== ''));
  const writes: Promise<unknown>[] = [applicationApi.saveDatabase(saved.id, database), applicationApi.saveAssignments(saved.id, [assignment])];
  if (domainChange) writes.push(applicationApi.saveDomains(saved.id, domainChange));
  await Promise.all(writes); drawerVisible.value = false; await load();
}
async function publish(item: EnterpriseApplication) { await applicationApi.publish(item.id); await load(); }
async function disable(item: EnterpriseApplication) { await applicationApi.disable(item.id); await load(); }
async function remove(item: EnterpriseApplication) { await ElMessageBox.confirm(`确定删除应用“${item.name}”吗？`, '删除确认', { type: 'warning' }); await applicationApi.remove(item.id); await load(); }
onMounted(load);
</script>
