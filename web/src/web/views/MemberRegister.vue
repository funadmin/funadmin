<template>
  <main class="member-page">
    <section class="member-shell">
      <RouterLink class="member-brand" to="/"><LogoMark :size="34" /><span>FunAdmin</span></RouterLink>
      <div class="member-card">
        <div class="member-card__heading"><span class="member-kicker">{{ t('community.labels.joinFunAdmin') }}</span><h1>{{ t('community.labels.createAnAccount') }}</h1><p>{{ t('community.labels.registerAsAMemberToUseFunadminSPublic') }}</p></div>
        <el-form :model="form" label-position="top" @submit.prevent="submit">
          <el-form-item :label="t('community.labels.username')"><el-input v-model="form.username" size="large" autocomplete="username" :placeholder="t('community.labels.label420LettersNumbersOrUnderscores')" /></el-form-item>
          <el-form-item :label="t('community.labels.email')"><el-input v-model="form.email" size="large" type="email" autocomplete="email" placeholder="name@example.com" /></el-form-item>
          <el-form-item :label="t('community.labels.nickname')"><el-input v-model="form.nickname" size="large" autocomplete="nickname" :placeholder="t('community.labels.yourPublicDisplayName')" /></el-form-item>
          <el-form-item :label="t('community.labels.password')"><el-input v-model="form.password" size="large" type="password" show-password autocomplete="new-password" :placeholder="t('community.labels.atLeast8CharactersIncludingLettersAndNumbers')" /></el-form-item>
          <el-form-item :label="t('community.labels.confirmPassword')"><el-input v-model="form.password_confirm" size="large" type="password" show-password autocomplete="new-password" :placeholder="t('community.labels.enterYourPasswordAgain')" @keyup.enter="submit" /></el-form-item>
          <el-button class="member-submit" type="primary" size="large" :loading="loading" @click="submit">{{ t('community.labels.registerAndSignIn') }}</el-button>
        </el-form>
        <div class="member-card__footer"><span>{{ t('community.labels.alreadyHaveAnAccount') }}</span><RouterLink to="/web/login">{{ t('community.labels.backToSignIn') }}</RouterLink></div>
      </div>
      <RouterLink class="member-back" to="/">{{ t('community.labels.backToHome') }}</RouterLink>
    </section>
    <div class="member-decoration member-decoration--one" /><div class="member-decoration member-decoration--two" />
  </main>
</template>

<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import LogoMark from '@/components/LogoMark.vue';
import { memberAuthApi } from '@/web/api/memberAuth';
import { memberSession } from '@/web/session/memberSession';

const { t } = useCmsI18n();
const router = useRouter();
const loading = ref(false);
const form = reactive({ username: '', email: '', nickname: '', password: '', password_confirm: '' });

async function submit() {
  if (!form.username.trim() || !form.email.trim() || !form.password || form.password !== form.password_confirm) {
    ElMessage.warning(t('community.labels.completeAllRegistrationFieldsAndMakeSureThePasswords'));
    return;
  }
  loading.value = true;
  try {
    const result = await memberAuthApi.register(form);
    memberSession.save(result);
    ElMessage.success(t('community.labels.registrationSuccessfulWelcome'));
    await router.replace('/web/profile');
  } catch (error: any) {
    ElMessage.error(error?.msg || error?.message || t('community.labels.registrationFailedCheckYourInformation'));
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.member-page { position: relative; min-height: 100vh; overflow: hidden; display: grid; place-items: center; padding: 40px 20px; color: #202846; background: linear-gradient(135deg, #eef5ff 0%, #f6f8ff 55%, #edf8ff 100%); }.member-shell { position: relative; z-index: 1; width: min(100%, 480px); }.member-brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 24px; color: #252b4e; font-size: 20px; font-weight: 800; }.member-brand :deep(.logo-mark) { color: #1677ff; }.member-card { padding: 36px; border: 1px solid rgba(255,255,255,.8); border-radius: 22px; background: rgba(255,255,255,.88); box-shadow: 0 24px 70px rgba(31,41,55,.12); backdrop-filter: blur(20px); }.member-card__heading { margin-bottom: 24px; text-align: center; }.member-kicker { color: #1677ff; font-size: 10px; font-weight: 800; letter-spacing: .15em; }.member-card h1 { margin: 11px 0 8px; color: #202846; font-size: 28px; letter-spacing: -.04em; }.member-card p { margin: 0; color: #8992a9; font-size: 13px; line-height: 1.7; }.member-submit { width: 100%; margin-top: 8px; border: 0; border-radius: 10px; background: #1677ff; }.member-card__footer { display: flex; justify-content: center; gap: 5px; margin-top: 22px; color: #929bb0; font-size: 12px; }.member-card__footer a,.member-back { color: #1677ff; font-weight: 700; }.member-back { display: block; margin-top: 20px; text-align: center; font-size: 12px; }.member-decoration { position: absolute; border-radius: 50%; filter: blur(2px); opacity: .55; }.member-decoration--one { width: 360px; height: 360px; top: -150px; right: -100px; background: #cfe2ff; }.member-decoration--two { width: 280px; height: 280px; bottom: -100px; left: -90px; background: #ccecff; }@media (max-width: 520px) { .member-card { padding: 28px 22px; } }
</style>
