<template>
  <div class="login">
    <div class="login__panel">
      <!-- 左侧品牌区 -->
      <aside class="login__brand">
        <div class="login__brand-top">
          <div class="login__brand-logo">
            <LogoMark :size="32" />
          </div>
          <span class="login__brand-name">{{ title }}</span>
        </div>

        <div class="login__brand-mid">
          <div class="login__illustration">
            <svg
              class="login__illustration-svg"
              viewBox="0 0 260 200"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
            >
              <!-- 阴影 -->
              <ellipse cx="130" cy="186" rx="110" ry="10" class="il-shadow" />

              <!-- 主卡片 -->
              <rect x="30" y="22" width="200" height="148" rx="14" class="il-card" />

              <!-- 标题条 -->
              <rect x="46" y="38" width="74" height="8" rx="3" class="il-bar-strong" />
              <rect x="46" y="52" width="46" height="5" rx="2.5" class="il-bar-weak" />

              <!-- 右上环形指标 -->
              <circle cx="190" cy="52" r="16" class="il-ring-track" />
              <path
                d="M190 36 a16 16 0 0 1 13.86 24"
                class="il-ring-active"
              />
              <text x="190" y="55" text-anchor="middle" class="il-ring-text">76</text>

              <!-- 三行数据 -->
              <g>
                <rect x="46" y="78" width="168" height="14" rx="6" class="il-row-bg" />
                <rect x="46" y="78" width="118" height="14" rx="6" class="il-row-fill-1" />
              </g>
              <g>
                <rect x="46" y="100" width="168" height="14" rx="6" class="il-row-bg" />
                <rect x="46" y="100" width="82" height="14" rx="6" class="il-row-fill-2" />
              </g>
              <g>
                <rect x="46" y="122" width="168" height="14" rx="6" class="il-row-bg" />
                <rect x="46" y="122" width="46" height="14" rx="6" class="il-row-fill-3" />
              </g>

              <!-- 浮动卡片：对勾 -->
              <g transform="translate(202 152)">
                <circle r="20" class="il-float-bg" />
                <path
                  d="M-7 0 l5 5 l9 -10"
                  class="il-check"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </g>

              <!-- 浮动卡片：迷你折线图 -->
              <g transform="translate(40 162)">
                <rect x="-26" y="-16" width="52" height="32" rx="7" class="il-float-bg" />
                <polyline
                  points="-18,8 -8,-2 2,4 16,-10"
                  class="il-line"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
                <circle cx="16" cy="-10" r="2.2" class="il-line-dot" />
              </g>
            </svg>
          </div>

          <h2 class="login__brand-title">
            {{ t('login.brandTitle1') }} · {{ t('login.brandTitle2') }}
          </h2>
          <p class="login__brand-desc">{{ t('login.brandDesc') }}</p>

          <ul class="login__brand-list">
            <li><i class="i-ep-check" /> {{ t('login.feat1') }}</li>
            <li><i class="i-ep-check" /> {{ t('login.feat2') }}</li>
            <li><i class="i-ep-check" /> {{ t('login.feat3') }}</li>
            <li><i class="i-ep-check" /> {{ t('login.feat4') }}</li>
          </ul>
        </div>

        <div class="login__brand-bottom">
          <span>© {{ year }} {{ title }}</span>
          <span>v{{ version }}</span>
        </div>
      </aside>

      <!-- 右侧表单区 -->
      <section class="login__form-wrap">
        <div class="login__form-inner">
          <div class="login__form-head">
            <h1 class="login__form-title">{{ t('login.welcomeTitle') }}</h1>
            <p class="login__form-sub">{{ t('login.sub') }}</p>
          </div>

          <el-form
            ref="formRef"
            :model="form"
            :rules="rules"
            size="large"
            label-position="top"
            class="login__form"
            @keyup.enter="onSubmit"
          >
            <el-form-item prop="username">
              <el-input v-model="form.username" :placeholder="t('login.usernamePh')" clearable>
                <template #prefix>
                  <i class="i-ep-user" />
                </template>
              </el-input>
            </el-form-item>

            <el-form-item prop="password">
              <el-input
                v-model="form.password"
                type="password"
                :placeholder="t('login.passwordPh')"
                show-password
                clearable
              >
                <template #prefix>
                  <i class="i-ep-lock" />
                </template>
              </el-input>
            </el-form-item>

            <el-form-item prop="captcha">
              <div class="login__captcha">
                <el-input v-model="form.captcha" :placeholder="t('login.captchaPh')" maxlength="4" clearable>
                  <template #prefix>
                    <i class="i-ep-key" />
                  </template>
                </el-input>
                <Captcha ref="captchaRef" />
              </div>
            </el-form-item>

            <div class="login__row">
              <el-checkbox v-model="form.remember">{{ t('login.remember') }}</el-checkbox>
              <a class="login__link">{{ t('login.forgot') }}</a>
            </div>

            <el-button
              type="primary"
              class="w-full"
              size="large"
              :loading="loading"
              @click="onSubmit"
            >
              {{ t('login.submit') }}
            </el-button>

            <div class="login__tip">
              <span>{{ t('login.defaultAccount') }}</span>
            </div>
          </el-form>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, type FormInstance, type FormRules } from 'element-plus';
import { useUserStore } from '@/store/modules/user';
import { APP_CONFIG } from '@/config';
import Captcha from '@/components/Captcha/index.vue';
import LogoMark from '@/components/LogoMark.vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const userStore = useUserStore();

const title = APP_CONFIG.title;
const version = APP_CONFIG.version;
const year = new Date().getFullYear();

const formRef = ref<FormInstance>();
const captchaRef = ref<InstanceType<typeof Captcha>>();
const loading = ref(false);

const form = reactive({
  username: 'admin',
  password: '123456',
  captcha: '',
  remember: true
});

const rules = computed<FormRules>(() => ({
  username: [{ required: true, message: t('login.rulesUsername'), trigger: 'blur' }],
  password: [{ required: true, message: t('login.rulesPassword'), trigger: 'blur' }],
  captcha: [{ required: true, message: t('login.rulesCaptcha'), trigger: 'blur' }]
}));

async function onSubmit() {
  if (!formRef.value) return;
  await formRef.value.validate();


  loading.value = true;
  try {
    await userStore.login({
      username: form.username,
      password: form.password,
      captcha: form.captcha,
      remember: form.remember
    });
    const redirect = (route.query.redirect as string) || '/dashboard';
    router.replace(redirect);
  } catch {
    captchaRef.value?.refresh();
    form.captcha = '';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.login {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background-color: var(--app-app-bg);
}

.login__panel {
  position: relative;
  width: 100%;
  max-width: 1080px;
  min-height: 580px;
  display: grid;
  grid-template-columns: 1.05fr 1fr;
  background: var(--app-card-bg);
  border: 1px solid var(--app-border);
  border-radius: var(--app-radius-lg);
  overflow: hidden;
  box-shadow: var(--app-shadow-lg);
}

/* ---------- 左侧品牌区：浅色 + 主色淡底点缀 ---------- */
.login__brand {
  position: relative;
  padding: 40px 44px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
  color: var(--app-text);
  background-color: color-mix(in srgb, var(--el-color-primary) 5%, var(--app-card-bg));
  border-right: 1px solid var(--app-border);
}

.login__brand-top {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
}
.login__brand-logo {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: color-mix(in srgb, var(--el-color-primary) 12%, transparent);
}
.login__brand-name {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: var(--app-text);
}

.login__brand-mid {
  position: relative;
  margin-top: 8px;
}

/* 插画 */
.login__illustration {
  display: flex;
  justify-content: center;
  margin: 8px 0 24px;
}
.login__illustration-svg {
  width: 100%;
  max-width: 320px;
  height: auto;
  color: var(--el-color-primary);
}
.login__illustration-svg .il-shadow { fill: currentColor; opacity: 0.08; }
.login__illustration-svg .il-card {
  fill: var(--app-card-bg);
  stroke: color-mix(in srgb, currentColor 22%, transparent);
  stroke-width: 1.2;
}
.login__illustration-svg .il-bar-strong { fill: currentColor; opacity: 0.85; }
.login__illustration-svg .il-bar-weak { fill: currentColor; opacity: 0.25; }
.login__illustration-svg .il-ring-track {
  fill: none;
  stroke: currentColor;
  stroke-opacity: 0.2;
  stroke-width: 3;
}
.login__illustration-svg .il-ring-active {
  fill: none;
  stroke: currentColor;
  stroke-width: 3;
  stroke-linecap: round;
}
.login__illustration-svg .il-ring-text {
  fill: currentColor;
  font-size: 10px;
  font-weight: 700;
  font-family: inherit;
}
.login__illustration-svg .il-row-bg { fill: currentColor; opacity: 0.08; }
.login__illustration-svg .il-row-fill-1 { fill: currentColor; opacity: 0.7; }
.login__illustration-svg .il-row-fill-2 { fill: currentColor; opacity: 0.45; }
.login__illustration-svg .il-row-fill-3 { fill: currentColor; opacity: 0.28; }
.login__illustration-svg .il-float-bg {
  fill: var(--app-card-bg);
  stroke: color-mix(in srgb, currentColor 22%, transparent);
  stroke-width: 1.2;
}
.login__illustration-svg .il-check {
  stroke: currentColor;
  stroke-width: 2.5;
  fill: none;
}
.login__illustration-svg .il-line {
  stroke: currentColor;
  stroke-width: 2;
  fill: none;
}
.login__illustration-svg .il-line-dot { fill: currentColor; }

.login__brand-title {
  margin: 0 0 10px;
  font-size: 22px;
  line-height: 1.4;
  font-weight: 700;
  color: var(--app-text);
  text-align: center;
}
.login__brand-desc {
  margin: 0 0 20px;
  font-size: 13px;
  line-height: 1.7;
  color: var(--app-text-secondary);
  text-align: center;
  white-space: pre-line;
}
.login__brand-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
  font-size: 12.5px;
}
.login__brand-list li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 999px;
  color: var(--el-color-primary);
  background: color-mix(in srgb, var(--el-color-primary) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--el-color-primary) 18%, transparent);
}
.login__brand-list i {
  font-size: 12px;
}

.login__brand-bottom {
  position: relative;
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--app-text-secondary);
  opacity: 0.85;
}

/* ---------- 右侧表单区 ---------- */
.login__form-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  background: var(--app-card-bg);
}
.login__form-inner {
  width: 100%;
  max-width: 360px;
}
.login__form-head {
  margin-bottom: 28px;
}
.login__form-title {
  margin: 0 0 6px;
  font-size: 26px;
  font-weight: 700;
  color: var(--app-text);
}
.login__form-sub {
  margin: 0;
  font-size: 13px;
  color: var(--app-text-secondary);
}

.login__captcha {
  display: flex;
  gap: 10px;
  width: 100%;
}

.login__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.login__link {
  font-size: 13px;
  color: var(--el-color-primary);
  cursor: pointer;
}
.login__link:hover {
  text-decoration: underline;
}
.login__tip {
  margin-top: 14px;
  text-align: center;
  font-size: 12px;
  color: var(--app-text-secondary);
}

@media (max-width: 960px) {
  .login__panel {
    grid-template-columns: 1fr;
    max-width: 460px;
    min-height: 0;
  }
  .login__brand {
    padding: 28px 28px 20px;
  }
  .login__illustration {
    margin: 4px 0 16px;
  }
  .login__illustration-svg {
    max-width: 220px;
  }
  .login__brand-list {
    display: none;
  }
  .login__brand-bottom {
    display: none;
  }
  .login__form-wrap {
    padding: 32px;
  }
}
</style>
