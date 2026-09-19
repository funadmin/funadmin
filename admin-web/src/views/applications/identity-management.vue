<template>
  <PageWrapper :title="title" :subtitle="t('applications.identitySubtitle', '统一身份管理控制台')">
    <div class="toolbar">
      <el-input v-if="mode === 'users'" v-model="keyword" class="search" clearable :aria-label="t('applications.identitySearchUsersAria', '搜索身份用户')" :placeholder="t('applications.identitySearchUsers', '搜索用户')" @keyup.enter="load" />
      <el-select v-if="mode === 'audit'" v-model="auditOutcome" clearable :aria-label="t('applications.identityAuditOutcomeAria', '审计结果')" :placeholder="t('applications.identityAllResults', '全部结果')">
        <el-option :label="t('common.success', '成功')" value="success" /><el-option :label="t('common.failed', '失败')" value="failure" />
      </el-select>
      <el-button @click="load">{{ t('common.refresh', '刷新') }}</el-button>
      <el-button v-if="mode === 'scopes'" type="primary" plain @click="openScope()">{{ t('applications.createScope', '新建 Scope') }}</el-button>
      <el-button v-if="mode === 'keys'" type="primary" plain @click="rotateKey">{{ t('applications.rotateKey', '轮换密钥') }}</el-button>
    </div>

    <el-tabs v-if="mode === 'sessions'" v-model="sessionTab" @tab-change="load">
      <el-tab-pane :label="t('applications.tabSessions', '活动会话')" name="sessions" /><el-tab-pane :label="t('applications.tabDeliveries', '退出投递')" name="deliveries" />
    </el-tabs>

    <el-table v-loading="loading" :data="rows" stripe :aria-label="title">
      <el-table-column v-for="column in columns" :key="column.prop" :prop="column.prop" :label="column.label" :min-width="column.width" show-overflow-tooltip />
      <el-table-column v-if="mode === 'users'" :label="t('common.operation', '操作')" width="120"><template #default="scope"><el-button link type="primary" @click="showUser(scope.row.id)">{{ t('common.detail', '详情') }}</el-button></template></el-table-column>
      <el-table-column v-if="mode === 'sessions'" :label="t('common.operation', '操作')" width="140"><template #default="scope"><el-button v-if="sessionTab === 'sessions'" link type="danger" @click="revokeSession(scope.row.id)">{{ t('applications.revoke', '撤销') }}</el-button><el-button v-else link type="primary" :disabled="scope.row.status === 'delivered'" @click="retryDelivery(scope.row.id)">{{ t('applications.retry', '重试') }}</el-button></template></el-table-column>
      <el-table-column v-if="mode === 'scopes'" :label="t('common.operation', '操作')" width="160"><template #default="scope"><el-button link type="primary" @click="openScope(scope.row as ScopeClaim)">{{ t('common.edit', '编辑') }}</el-button><el-button link type="danger" :disabled="scope.row.isBuiltin" @click="removeScope(scope.row.id)">{{ t('common.remove', '删除') }}</el-button></template></el-table-column>
    </el-table>

    <el-pagination v-if="['users', 'sessions', 'audit'].includes(mode)" class="pagination" v-model:current-page="page" v-model:page-size="pageSize" layout="total, prev, pager, next" :total="total" @current-change="load" />

    <el-dialog v-model="scopeVisible" :title="t('applications.scopeDialogTitle', 'Scope 与 Claim')" width="min(560px, 92vw)">
      <el-form label-width="100px"><el-form-item :label="t('applications.name', '名称')"><el-input v-model="scopeForm.name" /></el-form-item><el-form-item :label="t('applications.desc', '说明')"><el-input v-model="scopeForm.description" /></el-form-item><el-form-item label="Claims"><el-select v-model="scopeForm.claims" multiple allow-create filterable class="full-width" /></el-form-item><el-form-item :label="t('common.enable', '启用')"><el-switch v-model="scopeForm.status" /></el-form-item></el-form>
      <template #footer><el-button @click="scopeVisible = false">{{ t('common.cancel', '取消') }}</el-button><el-button type="primary" @click="saveScope">{{ t('common.save', '保存') }}</el-button></template>
    </el-dialog>

    <el-drawer v-model="userVisible" :title="t('applications.userDetailTitle', '身份用户详情')" size="min(760px, 96vw)">
      <el-descriptions v-if="activeUser" :column="1" border><el-descriptions-item :label="t('applications.detailUser', '用户')">{{ activeUser.displayName }}（{{ activeUser.username }}）</el-descriptions-item><el-descriptions-item label="Realm">{{ activeUser.realm }}</el-descriptions-item><el-descriptions-item :label="t('applications.detailEmail', '邮箱')">{{ activeUser.email || '-' }}</el-descriptions-item><el-descriptions-item :label="t('applications.detailMobile', '手机')">{{ activeUser.mobile || '-' }}</el-descriptions-item></el-descriptions>
      <h3>{{ t('applications.linksTitle', '身份关联') }}</h3><el-table :data="userLinks"><el-table-column prop="type" :label="t('applications.colType', '类型')" /><el-table-column prop="reference" :label="t('applications.colRefId', '关联 ID')" /></el-table>
      <h3>{{ t('applications.sessionsTitle', '会话') }}</h3><el-table :data="activeUser?.sessions || []"><el-table-column prop="sid" label="SID" /><el-table-column prop="status" :label="t('common.status', '状态')" /><el-table-column prop="expires_at" :label="t('applications.colExpiresAt', '过期时间')" /></el-table>
      <h3>{{ t('applications.authorizationsTitle', '授权') }}</h3><el-table :data="activeUser?.authorizations || []"><el-table-column prop="id" label="ID" /><el-table-column prop="client_id" label="Client" /><el-table-column prop="status" :label="t('common.status', '状态')" /></el-table>
      <div class="drawer-actions"><el-button type="danger" @click="revokeUserSessions">{{ t('applications.revokeAllSessions', '撤销全部会话') }}</el-button><el-button type="warning" @click="revokeUserAuthorizations">{{ t('applications.revokeAllAuthorizations', '撤销全部授权') }}</el-button></div>
    </el-drawer>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { ElMessageBox } from 'element-plus';
import { applicationApi } from '@/api/identity/applications';
import { identityAuditApi, identitySessionApi, identityUserApi, scopeClaimApi, type IdentityUser, type ScopeClaim } from '@/api/identity/management';
import { signingKeyApi } from '@/api/identity/oauthClients';

defineOptions({ name: 'IdentityManagement' });
const { t } = useI18n();
type Mode = 'domains' | 'scopes' | 'users' | 'sessions' | 'keys' | 'audit';
type TableRow = Record<string, unknown>;
const route = useRoute();
const rows = ref<TableRow[]>([]); const loading = ref(false); const keyword = ref('');
const page = ref(1); const pageSize = ref(20); const total = ref(0); const auditOutcome = ref('');
const sessionTab = ref<'sessions' | 'deliveries'>('sessions');
const scopeVisible = ref(false); const userVisible = ref(false); const activeUser = ref<IdentityUser>();
const mode = computed<Mode>(() => route.path.endsWith('/scopes') ? 'scopes' : route.path.endsWith('/users') ? 'users' : route.path.endsWith('/sessions') ? 'sessions' : route.path.endsWith('/signing-keys') ? 'keys' : route.path.endsWith('/audit') ? 'audit' : 'domains');
const titles = computed<Record<Mode, string>>(() => ({ domains: t('applications.modeDomains', '域名管理'), scopes: t('applications.modeScopes', 'Scope 与 Claim'), users: t('applications.modeUsers', '身份用户'), sessions: t('applications.modeSessions', '会话与授权'), keys: t('applications.modeKeys', '签名密钥'), audit: t('applications.modeAudit', '登录审计') }));
const title = computed(() => titles.value[mode.value]);
const sessionColumns = computed(() => sessionTab.value === 'sessions' ? [['sid', 'SID'], ['user_id', t('applications.colUserId', '用户 ID')], ['status', t('common.status', '状态')], ['expires_at', t('applications.colExpiresAt', '过期时间')]] : [['client_id', 'Client'], ['status', t('applications.deliveryStatus', '投递状态')], ['attempts', t('applications.attempts', '尝试次数')], ['response_status', t('applications.httpStatus', 'HTTP 状态')]]);
const columnMap = computed<Record<Mode, string[][]>>(() => ({ scopes: [['name', t('applications.name', '名称')], ['description', t('applications.desc', '说明')], ['claims', 'Claims']], users: [['displayName', t('applications.colDisplayName', '显示名')], ['username', t('applications.colUsername', '用户名')], ['realm', 'Realm'], ['lastLoginAt', t('applications.colLastLogin', '最后登录')]], sessions: sessionColumns.value, keys: [['kid', 'KID'], ['algorithm', t('applications.colAlgorithm', '算法')], ['status', t('common.status', '状态')], ['publishUntil', t('applications.colPublishUntil', '发布至')]], audit: [['event_type', t('applications.colEvent', '事件')], ['outcome', t('applications.colOutcome', '结果')], ['user_id', t('applications.colUserId', '用户 ID')], ['created_at', t('applications.colTime', '时间')]], domains: [['application', t('applications.colApp', '应用')], ['type', t('applications.colType', '类型')], ['callback', t('applications.colCallback', '回调地址')], ['validation', t('applications.colValidation', '校验')]] }));
const columns = computed(() => columnMap.value[mode.value].map(([prop, label]) => ({ prop, label, width: 150 })));
const scopeForm = reactive({ id: 0, name: '', description: '', claims: [] as string[], status: true });
const userLinks = computed(() => { const links = activeUser.value?.links as { admin?: Array<{ admin_id: number }>; member?: Array<{ member_id: number }> } | undefined; return [...(links?.admin || []).map((item) => ({ type: 'Admin', reference: item.admin_id })), ...(links?.member || []).map((item) => ({ type: 'Member', reference: item.member_id }))]; });

const loadDomains = async () => {
  const apps = (await applicationApi.list({ page: 1, pageSize: 100 })).list;
  const nested = await Promise.all(apps.map(async (app) => (await applicationApi.domains(app.id)).flatMap((domain) => [['Identity callback', domain.identityCallback], ['Logout callback', domain.logoutCallback]].filter(([, value]) => value).map(([type, callback]) => ({ application: app.name, type, callback, validation: callback.startsWith('https://') || /^http:\/\/(localhost|127\.0\.0\.1)/.test(callback) ? t('applications.validationValid', '有效') : t('applications.validationHttps', '需 HTTPS') })))));
  rows.value = nested.flat(); total.value = rows.value.length;
};
const load = async () => { loading.value = true; try { if (mode.value === 'domains') await loadDomains(); else if (mode.value === 'scopes') { const result = await scopeClaimApi.list({ page: page.value, pageSize: pageSize.value }); rows.value = result.list as unknown as TableRow[]; total.value = result.total; } else if (mode.value === 'users') { const result = await identityUserApi.list({ page: page.value, pageSize: pageSize.value, keyword: keyword.value }); rows.value = result.list as unknown as TableRow[]; total.value = result.total; } else if (mode.value === 'sessions') { const result = sessionTab.value === 'sessions' ? await identitySessionApi.list({ page: page.value, pageSize: pageSize.value }) : await identitySessionApi.deliveries({ page: page.value, pageSize: pageSize.value }); rows.value = result.list; total.value = result.total; } else if (mode.value === 'keys') { rows.value = (await signingKeyApi.list()) as unknown as TableRow[]; total.value = rows.value.length; } else { const result = await identityAuditApi.list({ page: page.value, pageSize: pageSize.value, outcome: auditOutcome.value }); rows.value = result.list; total.value = result.total; } } finally { loading.value = false; } };
const openScope = (row?: ScopeClaim) => { Object.assign(scopeForm, row || { id: 0, name: '', description: '', claims: [], status: true }); scopeVisible.value = true; };
const saveScope = async () => { scopeForm.id ? await scopeClaimApi.update(scopeForm.id, scopeForm) : await scopeClaimApi.create(scopeForm); scopeVisible.value = false; await load(); };
const removeScope = async (id: number) => { await ElMessageBox.confirm(t('applications.deleteScopeConfirm', '确定删除该 Scope？'), t('applications.deleteTitle', '删除确认')); await scopeClaimApi.remove(id); await load(); };
const showUser = async (id: number) => { const [user, links, sessions, authorizations] = await Promise.all([identityUserApi.detail(id), identityUserApi.links(id), identityUserApi.sessions(id), identityUserApi.authorizations(id)]); activeUser.value = { ...user, links, sessions, authorizations }; userVisible.value = true; };
const revokeSession = async (id: number) => { await ElMessageBox.confirm(t('applications.revokeSessionConfirm', '确定撤销该会话？'), t('applications.revokeTitle', '撤销确认')); await identitySessionApi.revoke(id); await load(); };
const retryDelivery = async (id: number) => { await identitySessionApi.retry(id); await load(); };
const revokeUserSessions = async () => { if (activeUser.value) await identityUserApi.revokeSessions(activeUser.value.id); };
const revokeUserAuthorizations = async () => { if (activeUser.value) await identityUserApi.revokeAuthorizations(activeUser.value.id); };
const rotateKey = async () => { await ElMessageBox.confirm(t('applications.rotateKeyConfirm', '确定轮换签名密钥？'), t('applications.rotateKeyTitle', '密钥轮换')); await signingKeyApi.rotate(); await load(); };
watch(mode, () => { page.value = 1; void load(); });
onMounted(load);
</script>

<style scoped>
.toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }.search { width: min(100%, 320px); }.full-width { width: 100%; }.pagination { justify-content: flex-end; margin-top: 16px; }.drawer-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }h3 { margin: 22px 0 10px; }
@media (max-width: 640px) { .pagination { justify-content: center; overflow-x: auto; }.toolbar .el-select { width: 100%; } }
</style>
