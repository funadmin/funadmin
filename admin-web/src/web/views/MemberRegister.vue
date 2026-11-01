<template>
  <main class="member-page">
    <section class="member-shell">
      <RouterLink class="member-brand" to="/"><LogoMark :size="34" /><span>FunAdmin</span></RouterLink>
      <div class="member-card">
        <div class="member-card__heading"><span class="member-kicker">JOIN FUNADMIN</span><h1>创建前台账号</h1><p>注册会员账号，开始使用 FunAdmin 的前台服务。</p></div>
        <el-form :model="form" label-position="top" @submit.prevent="submit">
          <el-form-item label="用户名"><el-input v-model="form.username" size="large" autocomplete="username" placeholder="4-20 位字母、数字或下划线" /></el-form-item>
          <el-form-item label="邮箱"><el-input v-model="form.email" size="large" type="email" autocomplete="email" placeholder="name@example.com" /></el-form-item>
          <el-form-item label="昵称"><el-input v-model="form.nickname" size="large" autocomplete="nickname" placeholder="用于展示的昵称" /></el-form-item>
          <el-form-item label="密码"><el-input v-model="form.password" size="large" type="password" show-password autocomplete="new-password" placeholder="至少 8 位，包含字母和数字" /></el-form-item>
          <el-form-item label="确认密码"><el-input v-model="form.password_confirm" size="large" type="password" show-password autocomplete="new-password" placeholder="再次输入密码" @keyup.enter="submit" /></el-form-item>
          <el-button class="member-submit" type="primary" size="large" :loading="loading" @click="submit">注册并登录</el-button>
        </el-form>
        <div class="member-card__footer"><span>已经有账号？</span><RouterLink to="/web/login">返回登录</RouterLink></div>
      </div>
      <RouterLink class="member-back" to="/">返回官网首页</RouterLink>
    </section>
    <div class="member-decoration member-decoration--one" /><div class="member-decoration member-decoration--two" />
  </main>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import LogoMark from '@/components/LogoMark.vue';
import { memberAuthApi } from '@/web/api/memberAuth';
import { memberSession } from '@/web/session/memberSession';

const router = useRouter();
const loading = ref(false);
const form = reactive({ username: '', email: '', nickname: '', password: '', password_confirm: '' });

async function submit() {
  if (!form.username.trim() || !form.email.trim() || !form.password || form.password !== form.password_confirm) {
    ElMessage.warning('请完整填写注册信息，并确认两次密码一致');
    return;
  }
  loading.value = true;
  try {
    const result = await memberAuthApi.register(form);
    memberSession.save(result);
    ElMessage.success('注册成功，欢迎加入');
    await router.replace('/web/profile');
  } catch (error: any) {
    ElMessage.error(error?.msg || error?.message || '注册失败，请检查填写内容');
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.member-page { position: relative; min-height: 100vh; overflow: hidden; display: grid; place-items: center; padding: 40px 20px; color: #202846; background: linear-gradient(135deg, #f4f1ff 0%, #f6f8ff 55%, #edf8ff 100%); }.member-shell { position: relative; z-index: 1; width: min(100%, 480px); }.member-brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 24px; color: #252b4e; font-size: 20px; font-weight: 800; }.member-brand :deep(.logo-mark) { color: #7659df; }.member-card { padding: 36px; border: 1px solid rgba(255,255,255,.8); border-radius: 22px; background: rgba(255,255,255,.88); box-shadow: 0 24px 70px rgba(69,66,126,.15); backdrop-filter: blur(20px); }.member-card__heading { margin-bottom: 24px; text-align: center; }.member-kicker { color: #7659df; font-size: 10px; font-weight: 800; letter-spacing: .15em; }.member-card h1 { margin: 11px 0 8px; color: #202846; font-size: 28px; letter-spacing: -.04em; }.member-card p { margin: 0; color: #8992a9; font-size: 13px; line-height: 1.7; }.member-submit { width: 100%; margin-top: 8px; border: 0; border-radius: 10px; background: linear-gradient(100deg, #6954dc, #8b6de8); }.member-card__footer { display: flex; justify-content: center; gap: 5px; margin-top: 22px; color: #929bb0; font-size: 12px; }.member-card__footer a,.member-back { color: #7659df; font-weight: 700; }.member-back { display: block; margin-top: 20px; text-align: center; font-size: 12px; }.member-decoration { position: absolute; border-radius: 50%; filter: blur(2px); opacity: .55; }.member-decoration--one { width: 360px; height: 360px; top: -150px; right: -100px; background: #d9d0ff; }.member-decoration--two { width: 280px; height: 280px; bottom: -100px; left: -90px; background: #ccecff; }@media (max-width: 520px) { .member-card { padding: 28px 22px; } }
</style>
