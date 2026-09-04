<template>
  <main class="install-page">
    <section class="install-shell">
      <header class="install-header">
        <LogoMark :size="42" />
        <div><h1>{{ siteName }} 安装向导</h1><p>快速完成运行环境、数据库与管理员配置</p></div>
        <span class="version">v{{ siteVersion }}</span>
      </header>

      <el-steps :active="active" align-center finish-status="success">
        <el-step title="安装协议" /><el-step title="环境检测" /><el-step title="系统配置" /><el-step title="安装完成" />
      </el-steps>

      <el-card class="install-card" shadow="never" v-loading="loading">
        <template v-if="active === 0">
          <h2>FunAdmin 软件许可协议</h2>
          <div class="agreement">
            <p>感谢您选择 FunAdmin。FunAdmin 是基于 ThinkPHP 8 开发的模块化后台管理系统。</p>
            <p>使用本软件即表示您同意遵守适用的开源许可协议，不得利用本软件从事违法活动。</p>
            <p>安装前请确认服务器满足运行要求，并妥善保管数据库凭据和管理员密码。</p>
            <p>项目官网：<a href="https://www.funadmin.com" target="_blank" rel="noopener">www.funadmin.com</a></p>
          </div>
          <el-checkbox v-model="agreed">我已阅读并同意安装协议</el-checkbox>
        </template>

        <template v-else-if="active === 1">
          <div class="section-title"><div><h2>运行环境检测</h2><p>必需项全部通过后才能继续安装</p></div><el-button @click="loadEnvironment">重新检测</el-button></div>
          <el-table :data="environmentChecks" stripe>
            <el-table-column prop="label" label="检查项" min-width="150" />
            <el-table-column prop="requiredValue" label="要求" min-width="150" />
            <el-table-column prop="currentValue" label="当前状态" min-width="180" />
            <el-table-column label="结果" width="110" align="center">
              <template #default="{ row }"><el-tag :type="row.passed ? 'success' : row.required ? 'danger' : 'warning'">{{ row.passed ? '通过' : row.required ? '不通过' : '可选' }}</el-tag></template>
            </el-table-column>
          </el-table>
        </template>

        <el-form v-else-if="active === 2" ref="formRef" :model="form" label-position="top">
          <h2>数据库配置</h2>
          <div class="form-grid">
            <el-form-item label="数据库主机" required><el-input v-model="form.hostname" placeholder="127.0.0.1" /></el-form-item>
            <el-form-item label="端口" required><el-input v-model="form.port" placeholder="3306" /></el-form-item>
            <el-form-item label="数据库名" required><el-input v-model="form.database" /></el-form-item>
            <el-form-item label="数据表前缀"><el-input v-model="form.prefix" /></el-form-item>
            <el-form-item label="数据库用户名" required><el-input v-model="form.username" /></el-form-item>
            <el-form-item label="数据库密码"><el-input v-model="form.password" type="password" show-password autocomplete="new-password" /></el-form-item>
          </div>
          <el-divider />
          <h2>管理员配置</h2>
          <div class="form-grid">
            <el-form-item label="管理员账号" required><el-input v-model="form.adminUserName" /></el-form-item>
            <el-form-item label="管理员邮箱" required><el-input v-model="form.email" maxlength="60" /></el-form-item>
            <el-form-item label="管理员密码" required><el-input v-model="form.adminPassword" type="password" show-password autocomplete="new-password" /></el-form-item>
            <el-form-item label="确认密码" required><el-input v-model="form.rePassword" type="password" show-password autocomplete="new-password" /></el-form-item>
          </div>
          <el-switch v-model="form.appDebug" active-text="开发模式" inactive-text="生产模式" />
          <p class="tip">后台入口固定为 /admin-web/，生产环境建议关闭开发模式。</p>
        </el-form>

        <template v-else>
          <el-result icon="success" title="安装成功" sub-title="FunAdmin 已完成初始化，可以进入后台登录。">
            <template #extra>
              <div class="result-info"><p>管理员账号：<strong>{{ result?.username }}</strong></p><p>后台地址：<strong>/admin-web/</strong></p></div>
              <el-button type="primary" size="large" @click="enterAdmin">进入后台</el-button>
            </template>
          </el-result>
        </template>

        <footer v-if="active < 3" class="actions">
          <el-button v-if="active > 0" @click="active--">上一步</el-button>
          <el-button v-if="active < 2" type="primary" :disabled="!canNext" @click="next">下一步</el-button>
          <el-button v-else type="primary" :loading="installing" @click="submitInstall">立即安装</el-button>
        </footer>
      </el-card>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';
import LogoMark from '@/components/LogoMark.vue';
import { installApi, type InstallResult } from '@/api/install';
import { canContinueInstallation, validateInstallForm, type EnvironmentCheck, type InstallForm } from './install';

const active = ref(0);
const agreed = ref(false);
const loading = ref(false);
const installing = ref(false);
const siteName = ref('FunAdmin');
const siteVersion = ref('');
const environmentChecks = ref<EnvironmentCheck[]>([]);
const result = ref<InstallResult>();
const form = reactive<InstallForm>({ hostname: '127.0.0.1', port: '3306', database: 'funadmin', prefix: 'fun_', username: 'root', password: '', adminUserName: 'admin', adminPassword: '', rePassword: '', email: 'admin@admin.com', appDebug: false });
const canNext = computed(() => active.value === 0 ? agreed.value : canContinueInstallation(environmentChecks.value));

const loadEnvironment = async () => {
  loading.value = true;
  try {
    const data = await installApi.environment();
    if (data.installed) return location.replace('/admin-web/#/login');
    siteName.value = data.siteName;
    siteVersion.value = data.siteVersion;
    environmentChecks.value = data.checks;
  } catch (error: any) {
    ElMessage.error(error?.message || '环境检测失败');
  } finally { loading.value = false; }
};

const next = async () => {
  if (active.value === 0) {
    await loadEnvironment();
    if (!canContinueInstallation(environmentChecks.value)) return;
  }
  active.value++;
};

const submitInstall = async () => {
  const error = validateInstallForm(form);
  if (error) return ElMessage.error(error);
  installing.value = true;
  try {
    result.value = await installApi.install(form);
    active.value = 3;
  } catch (error: any) {
    ElMessage.error(error?.message || '安装失败');
  } finally { installing.value = false; }
};

const enterAdmin = async () => {
  try { await installApi.finish(); } catch {}
  location.href = '/admin-web/#/login';
};

onMounted(loadEnvironment);
</script>

<style scoped>
.install-page { display: grid; justify-items: center; width: 100%; min-height: 100vh; padding: 48px 20px; overflow-x: hidden; box-sizing: border-box; background: #f4f6fa; }
.install-shell { width: 100%; max-width: 960px; min-width: 0; }
.install-header { display: flex; align-items: center; gap: 14px; margin-bottom: 32px; }
.install-header h1 { margin: 0; font-size: 25px; color: #1d2129; }
.install-header p { margin: 5px 0 0; color: #86909c; }
.version { margin-left: auto; color: #86909c; }
.install-card { margin-top: 28px; border: 0; border-radius: 14px; }
.install-card h2 { margin: 0 0 18px; font-size: 18px; }
.agreement { min-height: 220px; padding: 22px; margin-bottom: 18px; border: 1px solid #e5e6eb; border-radius: 8px; background: #fafafa; line-height: 1.9; color: #4e5969; }
.section-title { display: flex; justify-content: space-between; align-items: start; }
.section-title p, .tip { color: #86909c; font-size: 13px; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 24px; }
.actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 28px; padding-top: 20px; border-top: 1px solid #f0f0f0; }
.result-info { margin-bottom: 22px; color: #4e5969; }
@media (max-width: 640px) { .install-page { padding: 24px 12px; } .form-grid { grid-template-columns: 1fr; } :deep(.el-step__title) { font-size: 12px; } }
</style>
