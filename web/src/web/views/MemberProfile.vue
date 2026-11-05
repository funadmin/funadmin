<template>
  <main class="member-profile-page">
    <header class="member-profile-header">
      <RouterLink class="member-brand" to="/"><LogoMark :size="32" /><span>FunAdmin</span></RouterLink>
      <nav><RouterLink to="/">官网首页</RouterLink><button type="button" @click="logout">退出登录</button></nav>
    </header>
    <section class="member-profile-shell">
      <div class="member-profile-intro"><span class="member-kicker">MEMBER CENTER</span><h1>个人中心</h1><p>管理你的会员资料和账号安全。</p></div>
      <div class="member-profile-grid">
        <el-card shadow="never" class="member-account-card">
          <div class="member-avatar">{{ account?.nickname?.charAt(0).toUpperCase() || 'U' }}</div>
          <h2>{{ account?.nickname || account?.username || '会员用户' }}</h2>
          <p>{{ account?.email || account?.username }}</p>
          <el-tag type="success" effect="light">账号正常</el-tag>
          <div class="member-account-meta"><span>用户名</span><b>{{ account?.username }}</b></div><div class="member-account-meta"><span>注册时间</span><b>{{ account?.created_at || '—' }}</b></div>
        </el-card>
        <el-card shadow="never" class="member-settings-card">
          <el-tabs v-model="activeTab">
            <el-tab-pane label="基本资料" name="profile">
              <el-form :model="profileForm" label-position="top" @submit.prevent="saveProfile">
                <el-form-item label="昵称"><el-input v-model="profileForm.nickname" placeholder="请输入昵称" /></el-form-item>
                <el-form-item label="个人简介"><el-input v-model="profileForm.motto" type="textarea" :rows="4" maxlength="255" show-word-limit placeholder="介绍一下自己" /></el-form-item>
                <el-form-item label="头像地址"><el-input v-model="profileForm.avatar" placeholder="请输入 https 开头的图片地址" /></el-form-item>
                <el-button type="primary" :loading="saving" @click="saveProfile">保存资料</el-button>
              </el-form>
            </el-tab-pane>
            <el-tab-pane label="安全设置" name="password">
              <el-form :model="passwordForm" label-position="top" @submit.prevent="changePassword">
                <el-form-item label="当前密码"><el-input v-model="passwordForm.current" type="password" show-password autocomplete="current-password" /></el-form-item>
                <el-form-item label="新密码"><el-input v-model="passwordForm.password" type="password" show-password autocomplete="new-password" /></el-form-item>
                <el-form-item label="确认新密码"><el-input v-model="passwordForm.password_confirm" type="password" show-password autocomplete="new-password" /></el-form-item>
                <el-alert title="修改密码后当前会员令牌会失效，需要重新登录。" type="info" :closable="false" class="member-security-tip" />
                <el-button type="primary" :loading="changingPassword" @click="changePassword">修改密码</el-button>
              </el-form>
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </div>
    </section>
  </main>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import LogoMark from '@/components/LogoMark.vue';
import { memberAuthApi, type MemberAccount } from '@/web/api/memberAuth';
import { memberSession } from '@/web/session/memberSession';

const router = useRouter();
const account = ref<MemberAccount | null>(memberSession.account());
const activeTab = ref<'profile' | 'password'>('profile');
const saving = ref(false);
const changingPassword = ref(false);
const profileForm = reactive({ nickname: '', motto: '', avatar: '' });
const passwordForm = reactive({ current: '', password: '', password_confirm: '' });

function applyAccount(next: MemberAccount) {
  account.value = next;
  memberSession.updateAccount(next);
  Object.assign(profileForm, { nickname: next.nickname || '', motto: next.motto || '', avatar: next.avatar || '' });
}

async function loadProfile() {
  try {
    const result = await memberAuthApi.me();
    applyAccount(result.account);
  } catch {
    memberSession.clear();
    await router.replace({ path: '/web/login', query: { redirect: '/web/profile' } });
  }
}

async function saveProfile() {
  if (!profileForm.nickname.trim()) {
    ElMessage.warning('请输入昵称');
    return;
  }
  saving.value = true;
  try {
    const result = await memberAuthApi.updateProfile(profileForm);
    applyAccount(result.account);
    ElMessage.success('资料已保存');
  } catch (error: any) {
    ElMessage.error(error?.msg || error?.message || '资料保存失败');
  } finally {
    saving.value = false;
  }
}

async function changePassword() {
  if (!passwordForm.current || !passwordForm.password || passwordForm.password !== passwordForm.password_confirm) {
    ElMessage.warning('请完整填写密码，并确认两次新密码一致');
    return;
  }
  changingPassword.value = true;
  try {
    await memberAuthApi.changePassword(passwordForm);
    memberSession.clear();
    ElMessage.success('密码已修改，请重新登录');
    await router.replace('/web/login');
  } catch (error: any) {
    ElMessage.error(error?.msg || error?.message || '密码修改失败');
  } finally {
    changingPassword.value = false;
  }
}

async function logout() {
  try { await memberAuthApi.logout(); } catch { /* 令牌失效时本地仍需退出。 */ }
  memberSession.clear();
  await router.replace('/web/login');
}

onMounted(loadProfile);
</script>

<style scoped>
.member-profile-page { min-height: 100vh; color: #202846; background: #f7f8fc; }.member-profile-header { display: flex; align-items: center; justify-content: space-between; min-height: 74px; padding: 0 max(24px, calc((100% - 1180px) / 2)); background: #fff; border-bottom: 1px solid #edf0f5; }.member-brand { display: inline-flex; align-items: center; gap: 9px; color: #252b4e; font-size: 18px; font-weight: 800; }.member-brand :deep(.logo-mark) { color: #1677ff; }.member-profile-header nav { display: flex; align-items: center; gap: 18px; font-size: 13px; }.member-profile-header nav a { color: #68738c; }.member-profile-header button { padding: 0; border: 0; color: #1677ff; background: transparent; cursor: pointer; font-size: 13px; font-weight: 700; }.member-profile-shell { width: min(1180px, calc(100% - 40px)); margin: 0 auto; padding: 70px 0; }.member-profile-intro { margin-bottom: 34px; }.member-kicker { color: #1677ff; font-size: 10px; font-weight: 800; letter-spacing: .15em; }.member-profile-intro h1 { margin: 12px 0 8px; color: #202846; font-size: 36px; letter-spacing: -.05em; }.member-profile-intro p { margin: 0; color: #8992a9; font-size: 14px; }.member-profile-grid { display: grid; grid-template-columns: 290px 1fr; gap: 20px; }.member-account-card,.member-settings-card { border: 1px solid #edf0f5; border-radius: 16px; }.member-account-card { text-align: center; }.member-avatar { display: grid; place-items: center; width: 88px; height: 88px; margin: 6px auto 18px; border-radius: 50%; color: #fff; background: linear-gradient(135deg, #1677ff, #409eff); font-size: 30px; font-weight: 800; }.member-account-card h2 { margin: 0 0 5px; color: #202846; font-size: 20px; }.member-account-card > p { margin: 0 0 15px; color: #8992a9; font-size: 13px; }.member-account-meta { display: flex; justify-content: space-between; gap: 12px; margin-top: 16px; padding-top: 15px; border-top: 1px solid #edf0f5; text-align: left; font-size: 12px; }.member-account-meta span { color: #929bb0; }.member-account-meta b { color: #4a5570; font-weight: 600; }.member-settings-card { min-height: 420px; }.member-security-tip { margin-bottom: 20px; }
@media (max-width: 760px) { .member-profile-header { padding: 0 18px; }.member-profile-shell { width: min(100% - 28px, 560px); padding: 42px 0; }.member-profile-grid { grid-template-columns: 1fr; }.member-account-card { padding-bottom: 18px; } }
</style>
