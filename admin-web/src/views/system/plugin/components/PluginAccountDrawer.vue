<template>
  <el-drawer v-model="visible" size="min(520px, 100vw)" class="plugin-account-drawer" @closed="resetSensitiveState">
    <template #header>
      <div class="drawer-header">
        <span class="header-icon" aria-hidden="true"><el-icon><Shop /></el-icon></span>
        <div>
          <h2>云市场账号</h2>
          <p>连接 FunAdmin 云市场，统一管理插件服务</p>
        </div>
      </div>
    </template>

    <div class="drawer-content">
      <el-alert
        v-if="error"
        class="account-alert"
        :title="error"
        type="error"
        show-icon
        :closable="false"
      />

      <template v-if="initialLoading">
        <el-skeleton data-testid="account-skeleton" :rows="7" animated />
      </template>

      <div v-else-if="loadFailed" class="load-error">
        <el-icon class="load-error-icon"><WarningFilled /></el-icon>
        <h3>账号信息加载失败</h3>
        <p>暂时无法获取云市场连接状态，请检查网络后重试。</p>
        <el-button type="primary" plain :loading="initialLoading" @click="load">重新加载</el-button>
      </div>

      <template v-else-if="account">
        <el-card shadow="never" class="account-card">
          <div class="account-summary">
            <el-avatar :size="56" :src="account.avatar || undefined" class="account-avatar">
              {{ accountInitial }}
            </el-avatar>
            <div class="account-identity">
              <div class="account-name-row">
                <strong>{{ account.nickname || account.username }}</strong>
                <el-tag type="success" effect="light" round>已连接</el-tag>
              </div>
              <span>@{{ account.username }}</span>
            </div>
          </div>
        </el-card>

        <section class="capability-section" aria-labelledby="market-capabilities">
          <div class="section-heading">
            <div>
              <h3 id="market-capabilities">云市场能力</h3>
              <p>当前账号已授权以下插件服务</p>
            </div>
          </div>
          <ul class="capability-list">
            <li>
              <span class="capability-icon"><el-icon><Refresh /></el-icon></span>
              <div><strong>版本检查</strong><p>同步插件版本与更新信息</p></div>
              <el-tag type="success" effect="plain">可用</el-tag>
            </li>
            <li>
              <span class="capability-icon"><el-icon><Download /></el-icon></span>
              <div><strong>授权下载</strong><p>获取账号有权访问的插件包</p></div>
              <el-tag type="success" effect="plain">可用</el-tag>
            </li>
            <li>
              <span class="capability-icon"><el-icon><Key /></el-icon></span>
              <div><strong>会话令牌</strong><p>凭据由服务端会话安全维护</p></div>
              <el-tag type="info" effect="plain">已保护</el-tag>
            </li>
          </ul>
        </section>

        <div class="account-actions">
          <el-button
            type="primary"
            plain
            :loading="refreshing"
            v-perm="'system:plugin:account-refresh'"
            @click="refreshToken"
          >刷新令牌</el-button>
          <el-button type="danger" plain :loading="loggingOut" @click="logout">退出市场账号</el-button>
        </div>
      </template>

      <template v-else>
        <el-card shadow="never" class="intro-card">
          <div class="intro-content">
            <span class="intro-icon" aria-hidden="true"><el-icon><Connection /></el-icon></span>
            <div>
              <h3>连接云市场</h3>
              <p>登录后同步授权、版本信息与插件下载权限，让插件维护更顺畅。</p>
            </div>
          </div>
        </el-card>

        <el-form label-position="top" class="login-form" @submit.prevent="login">
          <el-form-item label="云市场账号" :error="validation.account">
            <el-input
              v-model="form.account"
              data-testid="account-account"
              placeholder="请输入用户名或邮箱"
              autocomplete="username"
              clearable
              @input="validation.account = ''"
            >
              <template #prefix><el-icon aria-hidden="true"><User /></el-icon></template>
            </el-input>
          </el-form-item>
          <el-form-item label="密码" :error="validation.password">
            <el-input
              v-model="form.password"
              data-testid="account-password"
              type="password"
              placeholder="请输入至少 6 位密码"
              show-password
              autocomplete="current-password"
              @input="validation.password = ''"
            >
              <template #prefix><el-icon aria-hidden="true"><Lock /></el-icon></template>
            </el-input>
          </el-form-item>
          <el-button
            data-testid="account-submit"
            class="login-button"
            type="primary"
            native-type="submit"
            :loading="loggingIn"
            :disabled="!canLogin"
          >登录并连接</el-button>
        </el-form>

        <div class="security-note">
          <el-icon aria-hidden="true"><Lock /></el-icon>
          <span>凭据仅用于换取令牌，不在浏览器长期保存；登录状态由服务端会话维护。</span>
        </div>
      </template>
    </div>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Connection, Download, Key, Lock, Refresh, Shop, User, WarningFilled } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { pluginApi, type PluginAccount } from '@/api/plugin';

const visible = defineModel<boolean>({ default: false });
const emit = defineEmits<{ changed: [] }>();
const account = ref<PluginAccount | null>(null);
const initialLoading = ref(false);
const loadFailed = ref(false);
const loggingIn = ref(false);
const refreshing = ref(false);
const loggingOut = ref(false);
const error = ref('');
const form = reactive({ account: '', password: '' });
const validation = reactive({ account: '', password: '' });

const canLogin = computed(() => form.account.trim().length > 0 && form.password.length >= 6 && !loggingIn.value);
const accountInitial = computed(() => (account.value?.nickname || account.value?.username || '云').trim().charAt(0).toUpperCase());

function errorMessage(reason: unknown) {
  if (reason instanceof Error) return reason.message;
  if (reason && typeof reason === 'object' && 'msg' in reason && typeof reason.msg === 'string') return reason.msg;
  return '操作失败，请稍后重试';
}

function resetSensitiveState() {
  form.password = '';
  error.value = '';
  validation.account = '';
  validation.password = '';
}

function validateLogin() {
  validation.account = form.account.trim() ? '' : '请输入云市场账号';
  validation.password = form.password.length >= 6 ? '' : '密码至少 6 位';
  return !validation.account && !validation.password;
}

async function load() {
  initialLoading.value = true;
  loadFailed.value = false;
  error.value = '';
  try {
    account.value = await pluginApi.currentAccount();
  } catch (reason) {
    account.value = null;
    loadFailed.value = true;
    error.value = errorMessage(reason);
  } finally {
    initialLoading.value = false;
  }
}

async function login() {
  if (!validateLogin()) return;
  loggingIn.value = true;
  error.value = '';
  try {
    const normalizedAccount = form.account.trim();
    account.value = await pluginApi.accountLogin(normalizedAccount, form.password);
    form.account = normalizedAccount;
    form.password = '';
    ElMessage.success('云市场账号登录成功');
    emit('changed');
  } catch (reason) {
    error.value = errorMessage(reason);
  } finally {
    loggingIn.value = false;
  }
}

async function refreshToken() {
  refreshing.value = true;
  error.value = '';
  try {
    account.value = await pluginApi.accountRefresh();
    ElMessage.success('令牌刷新成功');
    emit('changed');
  } catch (reason) {
    error.value = errorMessage(reason);
  } finally {
    refreshing.value = false;
  }
}

async function logout() {
  try {
    await ElMessageBox.confirm('退出后将无法同步授权、版本信息和下载插件，确认退出吗？', '退出云市场账号', {
      type: 'warning',
      confirmButtonText: '确认退出',
      cancelButtonText: '取消'
    });
  } catch (reason) {
    if (reason === 'cancel' || reason === 'close') return;
    error.value = errorMessage(reason);
    return;
  }

  loggingOut.value = true;
  error.value = '';
  try {
    await pluginApi.accountLogout();
    account.value = null;
    form.password = '';
    ElMessage.success('已退出云市场账号');
    emit('changed');
  } catch (reason) {
    error.value = errorMessage(reason);
  } finally {
    loggingOut.value = false;
  }
}

watch(visible, (open) => {
  if (open) load();
  else resetSensitiveState();
}, { immediate: true });
</script>

<style scoped>
.drawer-header {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.drawer-header h2,
.drawer-header p,
.intro-card h3,
.intro-card p,
.capability-section h3,
.capability-section p,
.load-error h3,
.load-error p {
  margin: 0;
}

.drawer-header h2 {
  color: var(--el-text-color-primary);
  font-size: 18px;
  line-height: 1.45;
}

.drawer-header p {
  margin-top: 2px;
  color: var(--el-text-color-secondary);
  font-size: 13px;
  line-height: 1.4;
}

.header-icon,
.intro-icon,
.capability-icon {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  color: var(--el-color-primary);
  background: var(--el-color-primary-light-9);
  border: 1px solid var(--el-color-primary-light-7);
}

.header-icon {
  width: 38px;
  height: 38px;
  border-radius: var(--el-border-radius-base);
  font-size: 20px;
}

.drawer-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.account-alert {
  margin-bottom: 0;
}

.intro-card,
.account-card {
  border-color: var(--el-border-color-light);
  background: var(--el-fill-color-blank);
}

.intro-content,
.account-summary {
  display: flex;
  align-items: center;
  gap: 16px;
}

.intro-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  font-size: 24px;
}

.intro-card h3 {
  color: var(--el-text-color-primary);
  font-size: 16px;
}

.intro-card p,
.capability-section p,
.load-error p {
  margin-top: 6px;
  color: var(--el-text-color-secondary);
  font-size: 13px;
  line-height: 1.65;
}

.login-form {
  padding-top: 2px;
}

.login-button {
  width: 100%;
  margin-top: 2px;
}

.security-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 12px 14px;
  color: var(--el-text-color-secondary);
  background: var(--el-fill-color-light);
  border: 1px solid var(--el-border-color-lighter);
  border-radius: var(--el-border-radius-base);
  font-size: 12px;
  line-height: 1.6;
}

.security-note .el-icon {
  flex: 0 0 auto;
  margin-top: 3px;
  color: var(--el-color-success);
}

.account-avatar {
  flex: 0 0 auto;
  color: var(--el-color-white);
  background: var(--el-color-primary);
  font-weight: 600;
}

.account-identity {
  min-width: 0;
}

.account-name-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.account-name-row strong {
  overflow: hidden;
  color: var(--el-text-color-primary);
  font-size: 17px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-identity > span {
  display: block;
  margin-top: 6px;
  color: var(--el-text-color-secondary);
  font-size: 13px;
}

.section-heading {
  margin-bottom: 10px;
}

.capability-section h3 {
  color: var(--el-text-color-primary);
  font-size: 15px;
}

.capability-list {
  padding: 0;
  margin: 0;
  overflow: hidden;
  list-style: none;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: var(--el-border-radius-base);
}

.capability-list li {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  padding: 14px;
  background: var(--el-fill-color-blank);
}

.capability-list li + li {
  border-top: 1px solid var(--el-border-color-lighter);
}

.capability-icon {
  width: 34px;
  height: 34px;
  border-radius: 50%;
}

.capability-list strong {
  color: var(--el-text-color-primary);
  font-size: 14px;
}

.capability-list p {
  margin-top: 2px;
}

.account-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.account-actions .el-button {
  width: 100%;
  margin-left: 0;
}

.load-error {
  padding: 44px 20px;
  text-align: center;
  border: 1px dashed var(--el-border-color);
  border-radius: var(--el-border-radius-base);
}

.load-error-icon {
  margin-bottom: 12px;
  color: var(--el-color-danger);
  font-size: 34px;
}

.load-error h3 {
  color: var(--el-text-color-primary);
  font-size: 16px;
}

.load-error .el-button {
  margin-top: 18px;
}

@media (max-width: 520px) {
  .drawer-header p {
    display: none;
  }

  .drawer-content {
    gap: 16px;
  }

  .account-actions {
    grid-template-columns: 1fr;
  }

  .capability-list li {
    gap: 10px;
    padding: 12px;
  }
}
</style>
