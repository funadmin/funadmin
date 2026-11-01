<template>
  <div class="public-site">
    <header class="public-header">
      <div class="public-container public-header__inner">
        <RouterLink class="brand" to="/" aria-label="FunAdmin">
          <LogoMark :size="34" />
          <span>FunAdmin</span>
        </RouterLink>

        <nav class="public-nav" :aria-label="t('public.nav.label', '主导航')">
          <RouterLink to="/#features">{{ t('public.nav.features', '产品能力') }}</RouterLink>
          <RouterLink to="/#showcase">{{ t('public.nav.showcase', '后台体验') }}</RouterLink>
          <RouterLink to="/#architecture">{{ t('public.nav.architecture', '架构亮点') }}</RouterLink>
          <RouterLink to="/quick-start">{{ t('public.nav.quickStart', '快速开始') }}</RouterLink>
        </nav>

        <div class="public-header__actions">
          <button class="icon-button" type="button" :aria-label="t('public.actions.toggleTheme', '切换主题')" @click="toggleTheme">
            <i :class="appStore.themeMode === 'dark' ? 'i-ep-sunny' : 'i-ep-moon'" />
          </button>
          <button class="locale-button" type="button" @click="toggleLocale">
            {{ appStore.locale === 'zh-CN' ? 'EN' : '中' }}
          </button>
          <RouterLink class="admin-entry" to="/admin/login">后台管理</RouterLink>
          <RouterLink v-if="memberSession.authenticated()" class="header-login" to="/web/profile">个人中心</RouterLink>
          <template v-else>
            <RouterLink class="header-register" to="/web/register">注册</RouterLink>
            <RouterLink class="header-login" to="/web/login">登录</RouterLink>
          </template>
        </div>
      </div>
    </header>

    <main>
      <section class="hero-section">
        <div class="hero-glow hero-glow--one" />
        <div class="hero-glow hero-glow--two" />
        <div class="public-container hero-section__inner">
          <div class="hero-copy">
            <div class="eyebrow"><span class="eyebrow__dot" /> {{ t('public.hero.eyebrow', '现代化后台开发平台') }}</div>
            <h1>{{ t('public.hero.title', '让后台开发变得简单而有趣') }}</h1>
            <p class="hero-copy__desc">{{ t('public.hero.desc', 'FunAdmin 是一套面向企业应用的全栈开发平台，用可视化工具、可扩展插件和 AI 能力，快速构建稳定、漂亮的管理后台。') }}</p>
            <div class="hero-copy__actions">
              <RouterLink class="primary-button" to="/web/register">
                {{ t('public.hero.start', '立即体验') }} <i class="i-ep-arrow-right" />
              </RouterLink>
              <a class="secondary-button" href="https://gitee.com/funadmin/funadmin" target="_blank" rel="noreferrer">
                <i class="i-ep-view" /> {{ t('public.hero.source', '查看源码') }}
              </a>
            </div>
            <div class="hero-trust">
              <span><i class="i-ep-check" /> {{ t('public.hero.trust1', '开源免费') }}</span>
              <span><i class="i-ep-check" /> {{ t('public.hero.trust2', 'PHP 8.1+') }}</span>
              <span><i class="i-ep-check" /> {{ t('public.hero.trust3', '插件化扩展') }}</span>
            </div>
          </div>

          <div class="hero-preview" aria-label="FunAdmin dashboard preview">
            <div class="preview-window">
              <div class="preview-window__bar"><span /><span /><span /><b>FunAdmin Console</b></div>
              <div class="preview-window__body">
                <aside class="preview-sidebar">
                  <div class="preview-logo"><LogoMark :size="22" /></div>
                  <i class="i-ep-monitor preview-sidebar__active" /><i class="i-ep-data-analysis" /><i class="i-ep-setting" /><i class="i-ep-connection" />
                </aside>
                <div class="preview-content">
                  <div class="preview-content__head"><div><small>{{ t('public.preview.overview', 'Overview') }}</small><strong>{{ t('public.preview.title', '控制台概览') }}</strong></div><span class="preview-avatar">A</span></div>
                  <div class="preview-stats"><div v-for="stat in previewStats" :key="stat.label" class="preview-stat"><i :class="stat.icon" /><small>{{ stat.label }}</small><strong>{{ stat.value }}</strong><em>{{ stat.trend }}</em></div></div>
                  <div class="preview-chart"><div class="preview-chart__title"><strong>{{ t('public.preview.activity', '业务增长趋势') }}</strong><span>2024</span></div><div class="chart-bars"><span v-for="height in chartHeights" :key="height" :style="{ height: `${height}%` }" /></div></div>
                  <div class="preview-table"><span v-for="row in 3" :key="row"><i /><b /><em /></span></div>
                </div>
              </div>
            </div>
            <div class="preview-badge preview-badge--top"><i class="i-ep-circle-check" /> {{ t('public.preview.ready', 'Production ready') }}</div>
            <div class="preview-badge preview-badge--bottom"><i class="i-ep-magic-stick" /> AI + CRUD</div>
          </div>
        </div>
      </section>

      <section class="metrics-strip">
        <div class="public-container metrics-strip__inner">
          <div v-for="metric in metrics" :key="metric.label" class="metric-item"><strong>{{ metric.value }}</strong><span>{{ metric.label }}</span></div>
        </div>
      </section>

      <section id="features" class="section-block section-block--features">
        <div class="public-container">
          <div class="section-heading"><span class="section-kicker">{{ t('public.features.kicker', 'BUILT FOR BUILDERS') }}</span><h2>{{ t('public.features.title', '从想法到上线，只需要更少的重复工作') }}</h2><p>{{ t('public.features.desc', '把时间留给真正的业务创新，而不是重复搭建列表、表单和权限。') }}</p></div>
          <div class="feature-grid">
            <article v-for="feature in features" :key="feature.title" class="feature-card" :class="`feature-card--${feature.color}`"><div class="feature-card__icon"><i :class="feature.icon" /></div><h3>{{ feature.title }}</h3><p>{{ feature.desc }}</p><a href="#architecture">{{ t('public.features.learnMore', '了解更多') }} <i class="i-ep-arrow-right" /></a></article>
          </div>
        </div>
      </section>

      <section id="showcase" class="section-block showcase-section">
        <div class="public-container">
          <div class="section-heading section-heading--left"><span class="section-kicker">{{ t('public.showcase.kicker', 'A CONSOLE THAT FEELS LIKE YOURS') }}</span><h2>{{ t('public.showcase.title', '不只是后台，更是你的业务工作台') }}</h2><p>{{ t('public.showcase.desc', '从登录后的第一眼，到个人资料、权限和业务数据，每个模块都保持清晰、舒适且可扩展。') }}</p></div>
          <div class="showcase-grid">
            <div class="showcase-tabs">
              <button v-for="item in showcaseItems" :key="item.key" type="button" :class="['showcase-tab', { 'is-active': activeShowcase === item.key }]" @click="activeShowcase = item.key"><span class="showcase-tab__icon"><i :class="item.icon" /></span><span><b>{{ item.title }}</b><small>{{ item.desc }}</small></span><i class="i-ep-arrow-right showcase-tab__arrow" /></button>
              <RouterLink class="showcase-link" to="/web/login">{{ t('public.showcase.openConsole', '进入后台查看完整体验') }} <i class="i-ep-top-right" /></RouterLink>
            </div>
            <div class="showcase-preview">
              <div class="showcase-preview__bar"><div><span /><span /><span /></div><b>funadmin / {{ activeShowcaseItem.path }}</b><i class="i-ep-more" /></div>
              <div v-if="activeShowcase === 'profile'" class="profile-mock"><div class="profile-mock__cover"><div class="profile-mock__pattern" /><div class="profile-mock__avatar">A</div></div><div class="profile-mock__body"><div><strong>Admin User</strong><small>admin@example.com</small></div><span class="profile-status"><i class="i-ep-circle-check" /> {{ t('public.showcase.profile.status', '账户正常') }}</span></div><div class="profile-mock__details"><div><i class="i-ep-user" /><span>{{ t('public.showcase.profile.basic', '基本资料') }}<small>{{ t('public.showcase.profile.basicDesc', '昵称、邮箱与头像') }}</small></span><i class="i-ep-arrow-right" /></div><div><i class="i-ep-lock" /><span>{{ t('public.showcase.profile.security', '安全设置') }}<small>{{ t('public.showcase.profile.securityDesc', '密码与登录安全') }}</small></span><i class="i-ep-arrow-right" /></div><div><i class="i-ep-message" /><span>{{ t('public.showcase.profile.messages', '消息中心') }}<small>{{ t('public.showcase.profile.messagesDesc', '查看系统通知') }}</small></span><i class="i-ep-arrow-right" /></div></div></div>
              <div v-else class="console-mock"><div class="console-mock__top"><div><small>{{ activeShowcaseItem.eyebrow }}</small><strong>{{ activeShowcaseItem.title }}</strong></div><span class="mock-user">A</span></div><div class="console-mock__cards"><div v-for="card in activeShowcaseItem.cards" :key="card.label"><i :class="card.icon" /><small>{{ card.label }}</small><strong>{{ card.value }}</strong><em>{{ card.delta }}</em></div></div><div class="console-mock__chart"><div class="mock-chart-head"><b>{{ activeShowcaseItem.chartTitle }}</b><span>2024 ↗</span></div><div class="mock-line"><i v-for="point in activeShowcaseItem.points" :key="point" :style="{ height: `${point}%` }" /></div></div><div class="mock-rows"><span v-for="row in 3" :key="row"><i /><b /><em /></span></div></div>
            </div>
          </div>
        </div>
      </section>

      <section id="architecture" class="section-block architecture-section">
        <div class="public-container architecture-grid">
          <div class="architecture-copy"><span class="section-kicker">{{ t('public.architecture.kicker', 'ONE PLATFORM, MANY POSSIBILITIES') }}</span><h2>{{ t('public.architecture.title', '可靠的底座，灵活的上层') }}</h2><p>{{ t('public.architecture.desc', '从认证、权限到业务生成，FunAdmin 将常见后台能力沉淀为清晰的模块，让团队可以按自己的节奏持续扩展。') }}</p><ul><li v-for="item in architectureItems" :key="item"><i class="i-ep-check" /> {{ item }}</li></ul></div>
          <div class="architecture-stack"><div class="stack-layer stack-layer--top"><i class="i-ep-magic-stick" /><span>AI Development</span><small>Copilot for your workflow</small></div><div class="stack-layer stack-layer--middle"><i class="i-ep-data-analysis" /><span>Visual CRUD & Form Builder</span><small>Build business faster</small></div><div class="stack-layer stack-layer--bottom"><i class="i-ep-lock" /><span>Webman · PHP · MySQL</span><small>Stable foundation</small></div></div>
        </div>
      </section>

      <section id="quick-start" class="section-block quick-section">
        <div class="public-container quick-card"><div><span class="section-kicker">{{ t('public.quick.kicker', 'START BUILDING TODAY') }}</span><h2>{{ t('public.quick.title', '准备好构建你的下一个后台了吗？') }}</h2><p>{{ t('public.quick.desc', '几分钟完成安装，马上开始你的业务开发。') }}</p></div><RouterLink class="primary-button primary-button--light" to="/quick-start">{{ t('public.quick.button', '查看快速开始') }} <i class="i-ep-arrow-right" /></RouterLink></div>
      </section>
    </main>

    <footer class="public-footer"><div class="public-container public-footer__inner"><div class="brand"><LogoMark :size="28" /><span>FunAdmin</span></div><span>© {{ new Date().getFullYear() }} FunAdmin · {{ t('public.footer.slogan', '让开发更有趣') }}</span><a href="https://gitee.com/funadmin/funadmin" target="_blank" rel="noreferrer">Gitee <i class="i-ep-top-right" /></a></div></footer>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import LogoMark from '@/components/LogoMark.vue';
import { useAppStore } from '@/store/modules/app';
import { memberSession } from '@/web/session/memberSession';

const { t } = useI18n();
const route = useRoute();
const appStore = useAppStore();

type ShowcaseKey = 'dashboard' | 'profile' | 'crud' | 'ai';
const activeShowcase = ref<ShowcaseKey>('dashboard');

const metrics = computed(() => [
  { value: '10+', label: t('public.metrics.modules', '开箱即用模块') },
  { value: '3', label: t('public.metrics.layers', '清晰架构层次') },
  { value: '100%', label: t('public.metrics.open', '开源可扩展') },
  { value: '24/7', label: t('public.metrics.ready', '随时准备开发') }
]);

const previewStats = computed(() => [
  { icon: 'i-ep-user', label: t('public.preview.users', '用户总数'), value: '12,680', trend: '+18.2%' },
  { icon: 'i-ep-document', label: t('public.preview.orders', '业务记录'), value: '8,492', trend: '+12.5%' },
  { icon: 'i-ep-data-analysis', label: t('public.preview.revenue', '处理效率'), value: '96.8%', trend: '+8.4%' }
]);
const chartHeights = [42, 58, 47, 72, 61, 82, 68, 91, 76, 86, 73, 96];
const features = computed(() => [
  { icon: 'i-ep-magic-stick', color: 'blue', title: t('public.features.ai.title', 'AI 开发助手'), desc: t('public.features.ai.desc', '将自然语言转化为可执行的开发任务，辅助分析、生成和迭代。') },
  { icon: 'i-ep-data-analysis', color: 'blue', title: t('public.features.crud.title', '可视化 CRUD'), desc: t('public.features.crud.desc', '从数据库或可视化表单快速生成完整业务模块，减少样板代码。') },
  { icon: 'i-ep-lock', color: 'orange', title: t('public.features.auth.title', '权限与认证'), desc: t('public.features.auth.desc', '内置角色、菜单、资源和会话能力，守住企业应用的安全边界。') },
  { icon: 'i-ep-connection', color: 'green', title: t('public.features.plugin.title', '插件化架构'), desc: t('public.features.plugin.desc', '模块可以独立开发、发布和升级，让系统保持长期可维护。') }
]);
const architectureItems = computed(() => [t('public.architecture.item1', 'Vue 3 + Vite 管理后台'), t('public.architecture.item2', 'Webman 高性能后端'), t('public.architecture.item3', 'FormSchema 运行时引擎'), t('public.architecture.item4', '中英文多语言支持')]);
const showcaseItems = computed(() => [
  { key: 'dashboard' as const, icon: 'i-ep-monitor', path: 'dashboard', title: t('public.showcase.dashboard.title', '仪表盘工作台'), desc: t('public.showcase.dashboard.desc', '数据概览与业务趋势一目了然'), eyebrow: t('public.showcase.dashboard.eyebrow', 'WELCOME BACK'), chartTitle: t('public.showcase.dashboard.chart', '访问趋势'), points: [35, 52, 42, 68, 56, 79, 65, 92], cards: [{ icon: 'i-ep-user', label: t('public.showcase.dashboard.users', '用户总数'), value: '12,680', delta: '+18.2%' }, { icon: 'i-ep-document', label: t('public.showcase.dashboard.records', '业务记录'), value: '8,492', delta: '+12.5%' }, { icon: 'i-ep-data-analysis', label: t('public.showcase.dashboard.efficiency', '处理效率'), value: '96.8%', delta: '+8.4%' }] },
  { key: 'profile' as const, icon: 'i-ep-user', path: 'profile', title: t('public.showcase.profile.title', '个人中心'), desc: t('public.showcase.profile.desc', '资料、安全、头像和消息统一管理'), eyebrow: '', chartTitle: '', points: [], cards: [] },
  { key: 'crud' as const, icon: 'i-ep-data-analysis', path: 'development/business', title: t('public.showcase.crud.title', '可视化业务开发'), desc: t('public.showcase.crud.desc', '从表结构快速生成业务页面'), eyebrow: t('public.showcase.crud.eyebrow', 'BUSINESS BUILDER'), chartTitle: t('public.showcase.crud.chart', '生成进度'), points: [25, 38, 50, 65, 73, 84, 91, 97], cards: [{ icon: 'i-ep-folder-opened', label: t('public.showcase.crud.forms', '业务模块'), value: '28', delta: '+6' }, { icon: 'i-ep-document-copy', label: t('public.showcase.crud.fields', '字段定义'), value: '436', delta: '+24' }, { icon: 'i-ep-circle-check', label: t('public.showcase.crud.published', '已发布'), value: '19', delta: '+3' }] },
  { key: 'ai' as const, icon: 'i-ep-magic-stick', path: 'development/ai', title: t('public.showcase.ai.title', 'AI 开发助手'), desc: t('public.showcase.ai.desc', '让自然语言参与分析、生成与迭代'), eyebrow: t('public.showcase.ai.eyebrow', 'AI WORKSPACE'), chartTitle: t('public.showcase.ai.chart', '任务完成度'), points: [28, 44, 39, 58, 67, 62, 82, 94], cards: [{ icon: 'i-ep-chat-dot-round', label: t('public.showcase.ai.sessions', '活跃会话'), value: '16', delta: '+4' }, { icon: 'i-ep-finished', label: t('public.showcase.ai.tasks', '完成任务'), value: '124', delta: '+31' }, { icon: 'i-ep-lightning', label: t('public.showcase.ai.speed', '开发提速'), value: '3.8x', delta: '+0.6x' }] }
]);
const activeShowcaseItem = computed(() => showcaseItems.value.find((item) => item.key === activeShowcase.value) ?? showcaseItems.value[0]!);

function toggleTheme() {
  appStore.setTheme(appStore.themeMode === 'dark' ? 'light' : 'dark');
}
function toggleLocale() {
  appStore.setLocale(appStore.locale === 'zh-CN' ? 'en-US' : 'zh-CN');
}

const anchor = route.path === '/features' ? 'features' : route.path === '/quick-start' ? 'quick-start' : '';
if (anchor) window.setTimeout(() => document.querySelector(`#${anchor}`)?.scrollIntoView({ behavior: 'smooth' }), 0);
</script>

<style scoped>
.public-site { min-height: 100vh; color: #172033; background: #fff; overflow: hidden; }
.public-container { width: min(1180px, calc(100% - 48px)); margin: 0 auto; }
.public-header { position: absolute; z-index: 5; inset: 0 0 auto; color: rgba(255,255,255,.86); }
.public-header__inner { height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 28px; }
.brand { display: inline-flex; align-items: center; gap: 10px; font-size: 19px; font-weight: 750; letter-spacing: -.03em; }
.brand :deep(.logo-mark) { color: #fff; }
.public-nav { display: flex; align-items: center; gap: 30px; margin-left: auto; font-size: 13px; }
.public-nav a { transition: color .2s; }.public-nav a:hover { color: #fff; }
.public-header__actions { display: flex; align-items: center; gap: 12px; }.icon-button,.locale-button { border: 0; color: inherit; background: transparent; cursor: pointer; }.icon-button { font-size: 17px; }.locale-button { font-size: 12px; font-weight: 700; }.header-login { padding: 9px 16px; border: 1px solid rgba(255,255,255,.28); border-radius: 8px; font-size: 13px; transition: background .2s, border-color .2s; }.header-register,.admin-entry { color: rgba(255,255,255,.82); font-size: 12px; }.header-login:hover { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.5); }
.hero-section { position: relative; min-height: 680px; color: #fff; background: linear-gradient(125deg, #0b2d5c 0%, #1259a8 53%, #1677ff 100%); }.hero-section::after { content: ''; position: absolute; inset: auto 0 0; height: 180px; opacity: .14; background: linear-gradient(165deg, transparent 35%, #fff 36%, transparent 37%), linear-gradient(20deg, transparent 53%, #fff 54%, transparent 55%); pointer-events: none; }.hero-glow { position: absolute; border-radius: 50%; filter: blur(4px); opacity: .3; pointer-events: none; }.hero-glow--one { width: 480px; height: 480px; top: 100px; right: 8%; background: radial-gradient(circle, #5cb8ff, transparent 68%); }.hero-glow--two { width: 340px; height: 340px; left: -100px; bottom: -160px; background: radial-gradient(circle, #35d0c0, transparent 70%); }
.hero-section__inner { position: relative; z-index: 1; min-height: 680px; display: grid; grid-template-columns: .96fr 1.04fr; align-items: center; gap: 50px; padding-top: 52px; }.hero-copy { max-width: 560px; }.eyebrow,.section-kicker { display: inline-flex; align-items: center; gap: 8px; color: #bae0ff; font-size: 11px; font-weight: 750; letter-spacing: .14em; }.eyebrow__dot { width: 7px; height: 7px; border-radius: 50%; background: #8ef0d0; box-shadow: 0 0 0 5px rgba(142,240,208,.15); }.hero-copy h1 { margin: 20px 0 20px; max-width: 560px; font-size: clamp(42px, 5vw, 68px); line-height: 1.08; letter-spacing: -.055em; }.hero-copy__desc { max-width: 500px; margin: 0; color: rgba(235,239,255,.72); font-size: 16px; line-height: 1.8; }.hero-copy__actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }.primary-button,.secondary-button { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 46px; padding: 0 20px; border-radius: 9px; font-size: 13px; font-weight: 700; transition: transform .2s, box-shadow .2s, background .2s; }.primary-button { color: #0b2d5c; background: #fff; box-shadow: 0 12px 28px rgba(4,4,32,.18); }.primary-button:hover,.secondary-button:hover { transform: translateY(-2px); }.secondary-button { color: #fff; border: 1px solid rgba(255,255,255,.28); background: rgba(255,255,255,.1); }.hero-trust { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 31px; color: rgba(235,239,255,.58); font-size: 11px; }.hero-trust span { display: inline-flex; align-items: center; gap: 5px; }.hero-trust i { color: #8ef0d0; }
.hero-preview { position: relative; padding: 26px 20px 35px; }.preview-window { position: relative; overflow: hidden; border: 1px solid rgba(255,255,255,.22); border-radius: 15px; background: #f7f9ff; box-shadow: 0 30px 80px rgba(5,4,38,.38), 0 0 0 10px rgba(255,255,255,.04); transform: perspective(1400px) rotateY(-7deg) rotateX(2deg); }.preview-window__bar { display: flex; align-items: center; gap: 6px; height: 37px; padding: 0 13px; color: #9ca5b9; background: #fff; border-bottom: 1px solid #e8eaf3; font-size: 8px; }.preview-window__bar span { width: 7px; height: 7px; border-radius: 50%; background: #ff8b8b; }.preview-window__bar span:nth-child(2) { background: #ffd477; }.preview-window__bar span:nth-child(3) { background: #77d8aa; }.preview-window__bar b { margin-left: auto; margin-right: auto; font-weight: 600; }.preview-window__body { display: flex; min-height: 360px; }.preview-sidebar { display: flex; flex: 0 0 58px; align-items: center; flex-direction: column; gap: 26px; padding-top: 17px; color: #a2a9bf; background: #fff; border-right: 1px solid #e8eaf3; }.preview-logo { margin-bottom: 7px; }.preview-logo :deep(.logo-mark) { color: #1677ff; }.preview-sidebar i { font-size: 17px; }.preview-sidebar__active { color: #1677ff; }.preview-content { flex: 1; padding: 20px; color: #1b2540; }.preview-content__head { display: flex; justify-content: space-between; align-items: center; }.preview-content__head small { display: block; color: #a3abc0; font-size: 8px; }.preview-content__head strong { display: block; margin-top: 4px; font-size: 16px; }.preview-avatar { display: grid; place-items: center; width: 25px; height: 25px; color: #fff; border-radius: 50%; background: #1677ff; font-size: 10px; }.preview-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; margin: 22px 0 14px; }.preview-stat { padding: 10px; border: 1px solid #ebedf5; border-radius: 8px; background: #fff; }.preview-stat i { display: block; margin-bottom: 7px; color: #1677ff; font-size: 13px; }.preview-stat small { display: block; color: #9ca5b9; font-size: 7px; }.preview-stat strong { display: block; margin-top: 3px; font-size: 14px; }.preview-stat em { display: block; margin-top: 2px; color: #35b68b; font-size: 7px; font-style: normal; }.preview-chart { padding: 12px 13px 9px; border: 1px solid #ebedf5; border-radius: 8px; background: #fff; }.preview-chart__title { display: flex; justify-content: space-between; font-size: 9px; }.preview-chart__title span { color: #a3abc0; }.chart-bars { display: flex; align-items: end; gap: 8px; height: 112px; padding-top: 14px; }.chart-bars span { flex: 1; border-radius: 3px 3px 0 0; background: linear-gradient(180deg, #409eff, #b8ddff); }.preview-table { display: grid; gap: 7px; padding-top: 13px; }.preview-table span { display: grid; grid-template-columns: 17px 1fr 45px; gap: 8px; align-items: center; }.preview-table i,.preview-table b,.preview-table em { display: block; height: 6px; border-radius: 4px; background: #e4e7f1; }.preview-table i { width: 17px; height: 17px; border-radius: 5px; background: #cfe2ff; }.preview-table em { background: #d2f3e7; }.preview-badge { position: absolute; display: flex; align-items: center; gap: 7px; padding: 10px 13px; border: 1px solid rgba(255,255,255,.32); border-radius: 9px; color: #fff; background: rgba(11,45,92,.72); backdrop-filter: blur(12px); box-shadow: 0 12px 30px rgba(10,6,50,.24); font-size: 10px; font-weight: 700; }.preview-badge i { color: #8ef0d0; }.preview-badge--top { top: 7px; right: -5px; }.preview-badge--bottom { bottom: 8px; left: -5px; }.preview-badge--bottom i { color: #ffca75; }
.section-block { padding: 112px 0; }.section-heading { max-width: 630px; margin: 0 auto 54px; text-align: center; }.section-kicker { color: #1677ff; }.section-heading h2,.architecture-copy h2,.quick-card h2 { margin: 15px 0 13px; color: #18213a; font-size: clamp(28px, 3vw, 42px); line-height: 1.18; letter-spacing: -.045em; }.section-heading p,.architecture-copy > p { margin: 0; color: #7f899d; font-size: 15px; line-height: 1.8; }.feature-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; }.feature-card { min-height: 235px; padding: 27px 23px; border: 1px solid #edf0f5; border-radius: 13px; background: #fff; box-shadow: 0 6px 23px rgba(27,38,72,.035); transition: transform .2s, box-shadow .2s; }.feature-card:hover { transform: translateY(-5px); box-shadow: 0 16px 34px rgba(27,38,72,.1); }.feature-card__icon { display: grid; place-items: center; width: 40px; height: 40px; margin-bottom: 22px; border-radius: 10px; font-size: 19px; }.feature-card--blue .feature-card__icon { color: #3989e8; background: #eaf4ff; }.feature-card--orange .feature-card__icon { color: #e99535; background: #fff3e4; }.feature-card--green .feature-card__icon { color: #2eaf82; background: #e4f8f0; }.feature-card h3 { margin: 0 0 10px; color: #202a43; font-size: 17px; }.feature-card p { min-height: 66px; margin: 0 0 18px; color: #8992a5; font-size: 12px; line-height: 1.75; }.feature-card a { color: #1677ff; font-size: 11px; font-weight: 700; }.feature-card a i { margin-left: 4px; }
.metrics-strip { position: relative; z-index: 2; margin-top: -1px; background: #fff; border-bottom: 1px solid #edf0f5; }.metrics-strip__inner { display: grid; grid-template-columns: repeat(4, 1fr); min-height: 112px; }.metric-item { display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 5px; border-right: 1px solid #edf0f5; }.metric-item:first-child { border-left: 1px solid #edf0f5; }.metric-item strong { color: #252e48; font-size: 25px; letter-spacing: -.04em; }.metric-item span { color: #929bad; font-size: 11px; }
.showcase-section { background: #fff; }.section-heading--left { max-width: 680px; margin-left: 0; margin-right: 0; text-align: left; }.section-heading--left h2 { max-width: 620px; }.showcase-grid { display: grid; grid-template-columns: .72fr 1.28fr; gap: 55px; align-items: center; }.showcase-tabs { display: grid; gap: 7px; }.showcase-tab { display: grid; grid-template-columns: 42px 1fr 18px; align-items: center; gap: 14px; width: 100%; padding: 15px 14px; border: 1px solid transparent; border-radius: 11px; color: #657089; background: transparent; text-align: left; cursor: pointer; transition: .2s; }.showcase-tab:hover { background: #f7f8fd; }.showcase-tab.is-active { border-color: #cfe2ff; color: #252d48; background: #eef5ff; box-shadow: 0 8px 20px rgba(22,119,255,.07); }.showcase-tab__icon { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; color: #1677ff; background: #eaf4ff; font-size: 17px; }.showcase-tab:nth-child(2) .showcase-tab__icon { color: #3989e8; background: #eaf4ff; }.showcase-tab:nth-child(3) .showcase-tab__icon { color: #e99a39; background: #fff3e4; }.showcase-tab:nth-child(4) .showcase-tab__icon { color: #2eaf82; background: #e4f8f0; }.showcase-tab b,.showcase-tab small { display: block; }.showcase-tab b { margin-bottom: 4px; font-size: 13px; }.showcase-tab small { color: #9ba3b5; font-size: 11px; }.showcase-tab__arrow { color: #a4acbd; font-size: 13px; }.showcase-tab.is-active .showcase-tab__arrow { color: #1677ff; }.showcase-link { display: inline-flex; align-items: center; gap: 7px; margin: 18px 0 0 14px; color: #1677ff; font-size: 12px; font-weight: 700; }.showcase-preview { min-height: 390px; overflow: hidden; border: 1px solid #e4e7f1; border-radius: 16px; background: #f6f8fd; box-shadow: 0 22px 55px rgba(40,49,92,.11); }.showcase-preview__bar { display: flex; align-items: center; gap: 11px; height: 39px; padding: 0 16px; color: #9aa4b9; border-bottom: 1px solid #e6e9f2; background: #fff; font-size: 9px; }.showcase-preview__bar > div { display: flex; gap: 5px; }.showcase-preview__bar span { width: 7px; height: 7px; border-radius: 50%; background: #ff9999; }.showcase-preview__bar span:nth-child(2) { background: #ffd47d; }.showcase-preview__bar span:nth-child(3) { background: #7bd8ac; }.showcase-preview__bar b { margin: auto; font-weight: 600; }.showcase-preview__bar > i { font-size: 14px; }.console-mock,.profile-mock { padding: 27px 30px; color: #1e2944; }.console-mock__top,.profile-mock__body { display: flex; justify-content: space-between; align-items: center; }.console-mock__top small { display: block; color: #a0a9bb; font-size: 8px; letter-spacing: .08em; }.console-mock__top strong { display: block; margin-top: 5px; font-size: 19px; }.mock-user { display: grid; place-items: center; width: 29px; height: 29px; color: #fff; border-radius: 50%; background: #1677ff; font-size: 11px; }.console-mock__cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 23px 0 14px; }.console-mock__cards > div { padding: 13px; border: 1px solid #e8ebf3; border-radius: 9px; background: #fff; }.console-mock__cards i { display: block; margin-bottom: 8px; color: #1677ff; font-size: 15px; }.console-mock__cards small,.console-mock__cards strong,.console-mock__cards em { display: block; }.console-mock__cards small { color: #9aa4b9; font-size: 8px; }.console-mock__cards strong { margin-top: 4px; font-size: 17px; }.console-mock__cards em { margin-top: 4px; color: #32b687; font-size: 8px; font-style: normal; }.console-mock__chart { padding: 15px 17px 10px; border: 1px solid #e8ebf3; border-radius: 9px; background: #fff; }.mock-chart-head { display: flex; justify-content: space-between; font-size: 10px; }.mock-chart-head span { color: #9ca6b9; font-size: 8px; }.mock-line { display: flex; align-items: end; gap: 12px; height: 120px; padding-top: 16px; }.mock-line i { flex: 1; border-radius: 4px 4px 0 0; background: linear-gradient(180deg,#409eff,#b8ddff); }.mock-rows { display: grid; gap: 8px; padding-top: 15px; }.mock-rows span { display: grid; grid-template-columns: 18px 1fr 40px; gap: 9px; align-items: center; }.mock-rows i,.mock-rows b,.mock-rows em { display: block; height: 6px; border-radius: 4px; background: #e1e5ef; }.mock-rows i { width: 18px; height: 18px; background: #cfe2ff; }.mock-rows em { background: #d1f2e5; }.profile-mock__cover { position: relative; height: 104px; overflow: hidden; border-radius: 11px; background: linear-gradient(120deg,#0b2d5c,#1677ff); }.profile-mock__pattern { position: absolute; inset: 0; opacity: .24; background-image: radial-gradient(circle at 20% 40%, #fff 0 1px, transparent 2px), radial-gradient(circle at 70% 60%, #fff 0 1px, transparent 2px); background-size: 26px 26px, 33px 33px; }.profile-mock__avatar { position: absolute; bottom: -23px; left: 24px; display: grid; place-items: center; width: 70px; height: 70px; border: 5px solid #fff; border-radius: 50%; color: #fff; background: #409eff; font-size: 25px; font-weight: 700; box-shadow: 0 5px 13px rgba(22,119,255,.15); }.profile-mock__body { padding: 31px 4px 19px; border-bottom: 1px solid #e8ebf3; }.profile-mock__body strong,.profile-mock__body small { display: block; }.profile-mock__body strong { font-size: 17px; }.profile-mock__body small { margin-top: 4px; color: #9aa4b9; font-size: 10px; }.profile-status { display: inline-flex; align-items: center; gap: 5px; padding: 6px 9px; border-radius: 6px; color: #249b70; background: #e8f8f1; font-size: 9px; }.profile-mock__details { display: grid; gap: 9px; padding-top: 18px; }.profile-mock__details > div { display: grid; grid-template-columns: 27px 1fr 13px; gap: 10px; align-items: center; padding: 11px 12px; border: 1px solid #edf0f6; border-radius: 8px; background: #fff; }.profile-mock__details > div > i:first-child { color: #1677ff; }.profile-mock__details span { font-size: 11px; font-weight: 700; }.profile-mock__details small { display: block; margin-top: 3px; color: #9aa4b9; font-size: 9px; font-weight: 400; }.profile-mock__details > div > i:last-child { color: #a4acbd; }
.architecture-section { background: #f8f9fc; }.architecture-grid { display: grid; grid-template-columns: .9fr 1.1fr; align-items: center; gap: 110px; }.architecture-copy h2 { max-width: 430px; }.architecture-copy > p { max-width: 490px; }.architecture-copy ul { display: grid; gap: 13px; margin: 28px 0 0; padding: 0; list-style: none; color: #56627b; font-size: 13px; }.architecture-copy li { display: flex; align-items: center; gap: 9px; }.architecture-copy li i { color: #1677ff; }.architecture-stack { display: grid; gap: 17px; padding: 35px 20px; }.stack-layer { display: flex; align-items: center; gap: 16px; padding: 21px 24px; border: 1px solid rgba(255,255,255,.75); border-radius: 13px; color: #fff; box-shadow: 0 15px 32px rgba(35,42,90,.1); }.stack-layer i { font-size: 23px; }.stack-layer span { font-size: 16px; font-weight: 700; }.stack-layer small { margin-left: auto; color: rgba(255,255,255,.65); font-size: 10px; }.stack-layer--top { background: linear-gradient(100deg,#1677ff,#409eff); transform: translateX(50px); }.stack-layer--middle { background: linear-gradient(100deg,#3486dc,#5fb9ef); }.stack-layer--bottom { background: linear-gradient(100deg,#24325f,#4c5e92); transform: translateX(-50px); }
.quick-section { padding-top: 0; }.quick-card { display: flex; align-items: center; justify-content: space-between; gap: 30px; padding: 52px 62px; border-radius: 17px; color: #fff; background: linear-gradient(105deg,#0b2d5c,#1677ff); box-shadow: 0 20px 40px rgba(11,45,92,.2); }.quick-card h2 { color: #fff; margin-bottom: 7px; }.quick-card p { margin: 0; color: rgba(255,255,255,.66); font-size: 13px; }.primary-button--light { color: #0b2d5c; white-space: nowrap; }
.public-footer { border-top: 1px solid #edf0f5; color: #9199aa; font-size: 11px; }.public-footer__inner { display: flex; align-items: center; justify-content: space-between; gap: 20px; min-height: 84px; }.public-footer .brand { color: #26304a; font-size: 16px; }.public-footer .brand :deep(.logo-mark) { color: #1677ff; }.public-footer a { color: #1677ff; }
@media (max-width: 900px) { .public-nav { display: none; }.hero-section__inner { grid-template-columns: 1fr; padding-top: 120px; padding-bottom: 70px; }.hero-section { min-height: auto; }.hero-copy { margin: 0 auto; text-align: center; }.eyebrow,.hero-copy__actions,.hero-trust { justify-content: center; }.hero-copy__desc { margin: auto; }.hero-preview { max-width: 650px; width: 100%; margin: auto; }.feature-grid { grid-template-columns: repeat(2,1fr); }.showcase-grid { grid-template-columns: 1fr; gap: 34px; }.showcase-tabs { max-width: 650px; width: 100%; margin: auto; }.architecture-grid { grid-template-columns: 1fr; gap: 30px; }.architecture-copy { text-align: center; }.architecture-copy h2,.architecture-copy > p { margin-left: auto; margin-right: auto; }.architecture-copy ul { justify-content: center; }.architecture-stack { max-width: 650px; width: 100%; margin: auto; } }
@media (max-width: 600px) { .public-container { width: min(100% - 32px, 1180px); }.public-header__inner { height: 65px; }.brand { font-size: 17px; }.locale-button { display: none; }.header-login { padding: 8px 11px; }.hero-copy h1 { font-size: 42px; }.hero-copy__desc { font-size: 14px; }.preview-window { transform: none; }.preview-badge { font-size: 9px; }.preview-badge--top { right: -3px; }.preview-badge--bottom { left: -3px; }.section-block { padding: 75px 0; }.feature-grid { grid-template-columns: 1fr; }.feature-card { min-height: 0; }.metrics-strip__inner { grid-template-columns: repeat(2, 1fr); }.metric-item { min-height: 86px; }.metric-item:nth-child(3) { border-left: 1px solid #edf0f5; }.showcase-preview { min-height: 340px; }.console-mock,.profile-mock { padding: 20px 16px; }.console-mock__cards { gap: 6px; }.console-mock__cards > div { padding: 9px 7px; }.console-mock__cards strong { font-size: 13px; }.mock-line { gap: 7px; }.profile-mock__body { align-items: flex-start; gap: 12px; flex-direction: column; }.quick-card { align-items: flex-start; flex-direction: column; padding: 34px 25px; }.public-footer__inner { align-items: flex-start; flex-direction: column; padding: 22px 0; }.stack-layer--top,.stack-layer--bottom { transform: none; }.stack-layer small { display: none; } }
:global(html.dark) .public-site { color: #e7eafe; background: #11162d; }
:global(html.dark) .section-heading h2, :global(html.dark) .architecture-copy h2 { color: #f4f5ff; }
:global(html.dark) .section-heading p, :global(html.dark) .architecture-copy > p { color: #9ca7c4; }
:global(html.dark) .section-block--features { background: #11162d; }
:global(html.dark) .feature-card { border-color: #293253; background: #171d39; box-shadow: none; }
:global(html.dark) .feature-card h3 { color: #eef0ff; }
:global(html.dark) .feature-card p { color: #9ca7c4; }
:global(html.dark) .architecture-section { background: #0d1227; }
:global(html.dark) .architecture-copy ul { color: #c0c8df; }
:global(html.dark) .public-footer { border-color: #293253; color: #8791ad; }
:global(html.dark) .public-footer .brand { color: #edf0ff; }
</style>
