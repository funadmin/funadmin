<template>
  <div class="public-site">
    <header class="public-header">
      <div class="public-container public-header__inner">
        <RouterLink class="brand" to="/" aria-label="FunAdmin">
          <LogoMark :size="34" />
          <span>FunAdmin</span>
        </RouterLink>

        <nav class="public-nav" :aria-label="t('public.nav.label', '主导航')">
          <RouterLink to="/" :class="{ 'is-active': route.path === '/' && !route.hash }">{{ t('public.landing.home') }}</RouterLink>
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
            <div class="hero-labels">
              <div class="eyebrow">{{ t('public.hero.eyebrow', '现代化后台开发平台') }}</div>
              <RouterLink class="hero-version" to="/quick-start">{{ t('public.landing.version') }} {{ APP_CONFIG.version }} <i class="i-ep-arrow-right" /></RouterLink>
            </div>
            <h1><span>{{ t('public.landing.title') }}</span><span class="hero-title__accent">{{ t('public.landing.titleAccent') }}</span></h1>
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

          <div class="hero-preview" :aria-label="t('public.landing.previewLabel')">
            <div class="preview-window">
              <div class="preview-window__bar">
                <div class="window-controls" aria-hidden="true"><span /><span /><span /></div>
                <div class="preview-tabs" role="tablist" :aria-label="t('public.landing.previewLabel')">
                  <button v-for="(tab, index) in previewTabs" :id="`preview-tab-${tab.key}`" :key="tab.key" type="button" role="tab" :aria-selected="activePreview === tab.key" aria-controls="preview-panel" :tabindex="activePreview === tab.key ? 0 : -1" :class="{ 'is-active': activePreview === tab.key }" @click="activePreview = tab.key" @keydown="onPreviewKeydown($event, index)">{{ tab.label }}</button>
                </div>
              </div>
              <div class="preview-window__body">
                <aside class="preview-sidebar" aria-hidden="true">
                  <div class="preview-logo"><LogoMark :size="23" /></div>
                  <i class="i-ep-monitor preview-sidebar__active" /><i class="i-ep-data-analysis" /><i class="i-ep-setting" /><i class="i-ep-connection" />
                </aside>
                <div id="preview-panel" class="preview-content" role="tabpanel" :aria-labelledby="`preview-tab-${activePreview}`" tabindex="0">
                  <div class="preview-content__head"><div><small>FUNADMIN WORKSPACE</small><strong>{{ previewTabs.find((tab) => tab.key === activePreview)?.label }}</strong></div><span class="preview-avatar">F</span></div>
                  <div v-if="activePreview === 'dashboard'" class="preview-dashboard">
                    <div class="preview-stats"><div v-for="stat in previewStats" :key="stat.label" class="preview-stat"><i :class="stat.icon" /><small>{{ stat.label }}</small><strong>{{ stat.value }}</strong><em>{{ stat.trend }}</em></div></div>
                    <div class="preview-chart"><div class="preview-chart__title"><strong>{{ t('public.preview.activity', '业务增长趋势') }}</strong><span>{{ t('public.landing.sampleData') }}</span></div><div class="chart-bars"><span v-for="height in chartHeights" :key="height" :style="{ height: `${height}%` }" /></div></div>
                  </div>
                  <div v-else-if="activePreview === 'schema'" class="preview-schema">
                    <div class="preview-code"><span>SELECT</span> id, name, status <span>FROM</span> business;</div>
                    <table class="preview-data-table"><thead><tr><th>id</th><th>name</th><th>status</th></tr></thead><tbody><tr v-for="(item, index) in ['orders', 'products', 'members']" :key="item"><td>00{{ index + 1 }}</td><td>{{ item }}</td><td><span class="preview-status">{{ t('public.landing.published') }}</span></td></tr></tbody></table>
                    <div class="preview-schema__footer"><i class="i-ep-circle-check" /> FormSchema v2 <span>{{ t('public.landing.sampleData') }}</span></div>
                  </div>
                  <div v-else class="preview-ai">
                    <div class="preview-ai__prompt">{{ t('public.landing.aiPrompt') }}</div>
                    <div class="preview-ai__answer"><span class="preview-ai__avatar">AI</span><div><strong>{{ t('public.landing.aiAnswer') }}</strong><p>{{ t('public.landing.aiDescription') }}</p></div></div>
                    <div v-for="step in ['schema', 'form', 'permissions']" :key="step" class="preview-ai__step"><i class="i-ep-check" /><code>{{ step }}</code><span>{{ t('public.landing.generated') }}</span></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="preview-badge preview-badge--top"><i class="i-ep-circle-check" /> {{ t('public.landing.visualReady') }}</div>
            <div class="preview-badge preview-badge--bottom"><i class="i-ep-magic-stick" /> AI · Agent</div>
          </div>
        </div>
        <div class="public-container tech-strip"><span>{{ t('public.landing.techCaption') }}</span><div class="tech-strip__tags"><span v-for="tech in technologies" :key="tech">{{ tech }}</span></div></div>
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
            <article v-for="feature in features" :key="feature.title" class="feature-card" :class="`feature-card--${feature.color}`"><div class="feature-card__icon"><i :class="feature.icon" /></div><h3>{{ feature.title }}</h3><p>{{ feature.desc }}</p><RouterLink to="/#architecture">{{ t('public.features.learnMore', '了解更多') }} <i class="i-ep-arrow-right" /></RouterLink></article>
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
          <div class="architecture-copy">
            <span class="section-kicker">{{ t('public.architecture.kicker') }}</span>
            <h2><span class="architecture-title__line">{{ t('public.architecture.titleBase') }}</span><span class="architecture-title__line">{{ t('public.architecture.titleExtension') }}</span></h2>
            <p>{{ t('public.architecture.desc') }}</p>
            <ul><li v-for="item in architectureItems" :key="item"><i class="i-ep-check" /> {{ item }}</li></ul>
          </div>
          <div class="architecture-diagram">
            <div class="architecture-diagram__heading"><span>{{ t('public.architecture.overview') }}</span><span>FunAdmin</span></div>
            <div class="architecture-layers">
              <article v-for="(layer, index) in architectureLayers" :key="layer.key" class="architecture-layer">
                <div class="architecture-layer__icon"><i :class="layer.icon" /></div>
                <div class="architecture-layer__body">
                  <h3>{{ layer.title }}</h3>
                  <p class="architecture-layer__description">{{ layer.desc }}</p>
                  <div class="architecture-layer__tags"><span v-for="tag in layer.tags" :key="tag">{{ tag }}</span></div>
                </div>
                <span class="architecture-layer__number">0{{ index + 1 }}</span>
              </article>
            </div>
          </div>
        </div>
      </section>

      <section id="quick-start" class="section-block quick-section">
        <div class="public-container">
          <div class="quick-card">
            <div class="quick-card__copy">
              <span class="section-kicker">{{ t('public.quick.kicker', 'START BUILDING TODAY') }}</span>
              <h2>{{ t('public.quick.title', '准备好构建你的下一个后台了吗？') }}</h2>
              <p>{{ t('public.quick.desc', '几分钟完成安装，马上开始你的业务开发。') }}</p>
            </div>
            <div class="quick-card__actions">
              <RouterLink class="primary-button" to="/quick-start">{{ t('public.quick.button', '查看快速开始') }} <i class="i-ep-arrow-right" /></RouterLink>
              <RouterLink class="secondary-button" to="/web/register">{{ t('public.hero.start', '立即体验') }} <i class="i-ep-top-right" /></RouterLink>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="public-footer">
      <div class="public-container">
        <div class="public-footer__inner">
          <div class="public-footer__brand">
            <RouterLink class="brand" to="/" aria-label="FunAdmin"><LogoMark :size="30" /><span>FunAdmin</span></RouterLink>
            <p>{{ t('public.hero.eyebrow', '现代化后台开发平台') }} · {{ t('public.footer.slogan', '让开发更有趣') }}</p>
          </div>
          <nav class="public-footer__nav" :aria-label="t('public.nav.label', '主导航')">
            <RouterLink to="/#features">{{ t('public.nav.features', '产品能力') }}</RouterLink>
            <RouterLink to="/#showcase">{{ t('public.nav.showcase', '后台体验') }}</RouterLink>
            <RouterLink to="/quick-start">{{ t('public.nav.quickStart', '快速开始') }}</RouterLink>
          </nav>
        </div>
        <div class="public-footer__bottom">
          <span>© {{ new Date().getFullYear() }} FunAdmin · {{ t('public.footer.slogan', '让开发更有趣') }}</span>
          <a href="https://gitee.com/funadmin/funadmin" target="_blank" rel="noopener noreferrer">Gitee <i class="i-ep-top-right" /></a>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import LogoMark from '@/components/LogoMark.vue';
import { useAppStore } from '@/store/modules/app';
import { APP_CONFIG } from '@/config';
import { memberSession } from '@/web/session/memberSession';

const { t } = useI18n();
const route = useRoute();
const appStore = useAppStore();

type ShowcaseKey = 'dashboard' | 'profile' | 'crud' | 'ai';
const activeShowcase = ref<ShowcaseKey>('dashboard');
const activePreview = ref<'dashboard' | 'schema' | 'ai'>('dashboard');
const technologies = ['Vue 3', 'TypeScript', 'Webman', 'PHP', 'MySQL', 'Redis', 'FormSchema', 'REST API', 'AI Agent'];
const previewTabs = computed(() => [
  { key: 'dashboard' as const, label: t('public.landing.console') },
  { key: 'schema' as const, label: t('public.landing.schema') },
  { key: 'ai' as const, label: t('public.features.ai.title') }
]);

function onPreviewKeydown(event: KeyboardEvent, index: number) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
  event.preventDefault();
  const tabs = previewTabs.value;
  const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
  activePreview.value = tabs[next]!.key;
  document.getElementById(`preview-tab-${activePreview.value}`)?.focus();
}

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
const architectureLayers = computed(() => [
  { key: 'ai', icon: 'i-ep-magic-stick', title: t('public.architecture.aiTitle'), desc: t('public.architecture.aiDesc'), tags: ['AI Agent', 'Copilot'] },
  { key: 'business', icon: 'i-ep-data-analysis', title: t('public.architecture.businessTitle'), desc: t('public.architecture.businessDesc'), tags: ['Visual CRUD', 'FormSchema'] },
  { key: 'foundation', icon: 'i-ep-lock', title: t('public.architecture.foundationTitle'), desc: t('public.architecture.foundationDesc'), tags: ['Webman', 'PHP', 'MySQL'] }
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

</script>

<style scoped>
.public-site {
  --public-hero-bg: #f8fbff;
  --public-surface: #ffffff;
  --public-soft: #f7f9fc;
  --public-text: #252b36;
  --public-muted: #6b7280;
  --public-border: #e5eaf1;
  --public-cta-bg: #eef6ff;
  --public-grid: rgba(100, 131, 166, .07);
  min-height: 100vh; color: var(--public-text); background: var(--public-surface); overflow: hidden;
}
.public-container { width: min(1320px, calc(100% - 64px)); margin: 0 auto; }
.public-header { position: relative; z-index: 5; color: var(--public-text); background: var(--public-surface); border-bottom: 1px solid var(--public-border); }
.public-header__inner { height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 28px; }
.brand { display: inline-flex; align-items: center; gap: 10px; font-size: 19px; font-weight: 750; letter-spacing: -.03em; }
.brand :deep(.logo-mark) { color: #1677ff; }
.public-nav { display: flex; align-self: stretch; align-items: stretch; gap: 26px; margin-left: auto; font-size: 14px; }
.public-nav a { display: flex; align-items: center; position: relative; white-space: nowrap; transition: color .2s; }
.public-nav a:hover,.public-nav a.is-active { color: #1677ff; }
.public-nav a.is-active::after { content: ''; position: absolute; inset: auto -12px -1px; height: 2px; background: #1677ff; }
.public-header__actions { display: flex; align-items: center; gap: 14px; white-space: nowrap; }
.icon-button,.locale-button { display: inline-flex; align-items: center; justify-content: center; border: 0; color: var(--public-muted); background: transparent; cursor: pointer; }
.icon-button { width: 34px; height: 24px; border: 1px solid var(--public-border); border-radius: 20px; background: var(--public-soft); font-size: 15px; }
.locale-button { font-size: 12px; font-weight: 600; }
.header-login { padding: 8px 16px; border: 1px solid #1677ff; border-radius: 6px; color: #1677ff; font-size: 13px; transition: background .2s; }
.header-register,.admin-entry { color: var(--public-muted); font-size: 12px; }
.header-login:hover { background: rgba(22,119,255,.06); }
.hero-section { position: relative; isolation: isolate; color: var(--public-text); background: var(--public-hero-bg); border-bottom: 1px solid var(--public-border); }
.hero-section::after { content: ''; position: absolute; z-index: -1; inset: 0; background-image: linear-gradient(var(--public-grid) 1px, transparent 1px), linear-gradient(90deg, var(--public-grid) 1px, transparent 1px); background-size: 48px 48px; mask-image: linear-gradient(#000, transparent 90%); pointer-events: none; }
.hero-glow { position: absolute; z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(28px); }
.hero-glow--one { width: 700px; height: 650px; top: -200px; left: -240px; background: radial-gradient(circle, rgba(121,190,255,.28), transparent 68%); }
.hero-glow--two { width: 680px; height: 500px; bottom: -260px; left: 42%; background: radial-gradient(circle, rgba(57,220,190,.2), transparent 68%); }
.hero-section__inner { position: relative; z-index: 1; min-height: 620px; display: grid; grid-template-columns: 1fr 1.05fr; align-items: center; gap: 72px; padding: 70px 0 54px; }
.hero-copy { min-width: 0; }
.hero-labels { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.eyebrow,.hero-version { display: inline-flex; align-items: center; gap: 8px; padding: 7px 13px; border: 1px solid var(--public-border); border-radius: 24px; font-size: 12px; font-weight: 500; }
.eyebrow { border-color: #c9e2ff; color: #1677ff; background: rgba(230,244,255,.7); }
.hero-version { color: var(--public-muted); background: var(--public-surface); }
.hero-copy h1 { margin: 28px 0 24px; font-size: clamp(40px, 4.5vw, 62px); font-weight: 700; line-height: 1.22; letter-spacing: -.035em; overflow-wrap: anywhere; }
.hero-copy h1 > span { display: block; }
.hero-title__accent { color: #1677ff; background: linear-gradient(105deg, #1677ff, #209fc4); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.hero-copy__desc { max-width: 580px; margin: 0; color: var(--public-muted); font-size: 16px; line-height: 1.95; }
.hero-copy__actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 34px; }
.primary-button,.secondary-button { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 46px; padding: 0 22px; border-radius: 6px; font-size: 14px; font-weight: 500; transition: transform .2s, background .2s; }
.primary-button { color: #fff; background: #1677ff; box-shadow: 0 4px 10px rgba(22,119,255,.12); }
.primary-button:hover { background: #4096ff; transform: translateY(-1px); }
.secondary-button { color: var(--public-muted); border: 1px solid var(--public-border); background: var(--public-surface); }
.secondary-button:hover { color: #1677ff; border-color: #91caff; }
.hero-trust { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 24px; color: var(--public-muted); font-size: 12px; }
.hero-trust span { display: inline-flex; align-items: center; gap: 5px; }
.hero-trust i { color: #1677ff; }
.hero-preview { position: relative; min-width: 0; padding: 22px 0; }
.preview-window { overflow: hidden; border: 1px solid var(--public-border); border-radius: 14px; background: var(--public-surface); box-shadow: 0 28px 65px rgba(31,49,77,.13); }
.preview-window__bar { display: flex; align-items: center; gap: 20px; height: 48px; padding: 0 16px; color: var(--public-muted); background: var(--public-soft); border-bottom: 1px solid var(--public-border); }
.window-controls { display: flex; flex-shrink: 0; gap: 6px; }
.window-controls span { width: 10px; height: 10px; border-radius: 50%; background: #ff5f57; }
.window-controls span:nth-child(2) { background: #febc2e; }
.window-controls span:nth-child(3) { background: #28c840; }
.preview-tabs { display: flex; height: 100%; min-width: 0; }
.preview-tabs button { padding: 0 12px; border: 0; border-bottom: 2px solid transparent; color: var(--public-muted); background: transparent; white-space: nowrap; font-size: 12px; cursor: pointer; }
@media (min-width: 901px) and (max-width: 1150px) { .public-header__inner { gap: 16px; }.public-nav { gap: 16px; font-size: 12px; }.public-header__actions { gap: 10px; }.hero-section__inner { gap: 40px; }.preview-tabs button { padding: 0 8px; font-size: 10px; } }
.preview-tabs button.is-active { color: #1677ff; border-bottom-color: #1677ff; }
.preview-tabs button:focus-visible,.public-site a:focus-visible,.public-site button:focus-visible { outline: 2px solid #1677ff; outline-offset: 3px; }
.preview-window__body { display: flex; min-height: 350px; }
.preview-sidebar { display: flex; flex: 0 0 48px; align-items: center; flex-direction: column; gap: 25px; padding-top: 20px; color: #9aa3b0; background: var(--public-soft); border-right: 1px solid var(--public-border); }
.preview-logo { margin-bottom: 8px; }
.preview-sidebar i { font-size: 17px; }
.preview-sidebar__active { color: #1677ff; }
.preview-content { flex: 1; min-width: 0; padding: 24px; color: var(--public-text); }
.preview-content__head { display: flex; justify-content: space-between; align-items: center; }
.preview-content__head small { display: block; color: var(--public-muted); font-size: 8px; letter-spacing: .08em; }
.preview-content__head strong { display: block; margin-top: 5px; font-size: 17px; }
.preview-avatar { display: grid; place-items: center; width: 28px; height: 28px; color: #1677ff; border-radius: 7px; background: rgba(22,119,255,.08); font-size: 12px; }
.preview-stats { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 10px; margin: 22px 0 14px; }
.preview-stat { padding: 12px; border: 1px solid var(--public-border); border-radius: 6px; background: var(--public-surface); }
.preview-stat i { display: block; margin-bottom: 8px; color: #1677ff; font-size: 14px; }
.preview-stat small { display: block; color: var(--public-muted); font-size: 9px; }
.preview-stat strong { display: block; margin-top: 4px; font-size: 17px; }
.preview-stat em { display: block; margin-top: 4px; color: #209c75; font-size: 9px; font-style: normal; }
.preview-chart { padding: 12px 14px; border: 1px solid var(--public-border); border-radius: 6px; }
.preview-chart__title { display: flex; justify-content: space-between; gap: 12px; font-size: 10px; }
.preview-chart__title span { color: var(--public-muted); font-size: 9px; }
.chart-bars { display: flex; align-items: end; gap: 8px; height: 88px; padding-top: 14px; }
.chart-bars span { flex: 1; border-radius: 3px 3px 0 0; background: linear-gradient(180deg, #69b1ff, #d6eaff); }
.preview-schema { margin-top: 24px; }
.preview-code { padding: 14px 0; color: var(--public-text); font: 11px/1.8 ui-monospace, SFMono-Regular, monospace; }
.preview-code > span { color: #1677ff; font-weight: 600; }
.preview-data-table { width: 100%; border-collapse: collapse; text-align: left; font: 11px/1.6 ui-monospace, SFMono-Regular, monospace; }
.preview-data-table th,.preview-data-table td { padding: 12px 10px; border-top: 1px solid var(--public-border); font-weight: 400; }
.preview-data-table th { color: var(--public-muted); background: var(--public-soft); }
.preview-status { color: #209c75; font-size: 10px; }
.preview-schema__footer { display: flex; align-items: center; gap: 6px; padding-top: 18px; color: #209c75; font-size: 10px; }
.preview-schema__footer > span { margin-left: auto; color: var(--public-muted); }
.preview-ai { padding-top: 20px; font-size: 11px; }
.preview-ai__prompt { padding: 12px; margin-left: 45px; border-radius: 8px 8px 0 8px; color: #1677ff; background: rgba(22,119,255,.07); }
.preview-ai__answer { display: flex; align-items: start; gap: 10px; padding: 20px 0 12px; }
.preview-ai__avatar { display: grid; place-items: center; flex: 0 0 28px; height: 28px; border-radius: 6px; color: #fff; background: #1677ff; font-size: 10px; }
.preview-ai__answer p { margin: 8px 0; line-height: 1.8; color: var(--public-muted); }
.preview-ai__step { display: flex; align-items: center; gap: 8px; margin: 8px 0 0 38px; padding: 8px; border: 1px solid var(--public-border); border-radius: 4px; }
.preview-ai__step i { color: #209c75; }
.preview-ai__step > span { margin-left: auto; color: var(--public-muted); }
.preview-badge { position: absolute; display: flex; align-items: center; gap: 8px; padding: 12px 16px; border: 1px solid var(--public-border); border-radius: 10px; color: var(--public-text); background: var(--public-surface); box-shadow: 0 16px 35px rgba(31,49,77,.12); font-size: 12px; font-weight: 600; }
.preview-badge i { color: #209c75; }
.preview-badge--top { top: 95px; left: -34px; }
.preview-badge--bottom { bottom: 6px; right: -16px; }
.preview-badge--bottom i { color: #1677ff; }
.tech-strip { position: relative; display: flex; align-items: center; flex-wrap: wrap; gap: 22px; padding-bottom: 38px; color: var(--public-muted); font-size: 12px; }
.tech-strip__tags { display: flex; flex-wrap: wrap; gap: 8px; }
.tech-strip__tags span { padding: 5px 10px; border: 1px solid var(--public-border); border-radius: 5px; background: var(--public-surface); font: 11px/1.4 ui-monospace, SFMono-Regular, monospace; }
.section-block { padding: 112px 0; }.section-heading { max-width: 630px; margin: 0 auto 54px; text-align: center; }.section-kicker { color: #1677ff; }.section-heading h2,.architecture-copy h2,.quick-card h2 { margin: 15px 0 13px; color: #18213a; font-size: clamp(28px, 3vw, 42px); line-height: 1.18; letter-spacing: -.045em; }.section-heading p,.architecture-copy > p { margin: 0; color: #7f899d; font-size: 15px; line-height: 1.8; }.feature-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; }.feature-card { min-height: 235px; padding: 27px 23px; border: 1px solid #edf0f5; border-radius: 13px; background: #fff; box-shadow: 0 6px 23px rgba(27,38,72,.035); transition: transform .2s, box-shadow .2s; }.feature-card:hover { transform: translateY(-5px); box-shadow: 0 16px 34px rgba(27,38,72,.1); }.feature-card__icon { display: grid; place-items: center; width: 40px; height: 40px; margin-bottom: 22px; border-radius: 10px; font-size: 19px; }.feature-card--blue .feature-card__icon { color: #3989e8; background: #eaf4ff; }.feature-card--orange .feature-card__icon { color: #e99535; background: #fff3e4; }.feature-card--green .feature-card__icon { color: #2eaf82; background: #e4f8f0; }.feature-card h3 { margin: 0 0 10px; color: #202a43; font-size: 17px; }.feature-card p { min-height: 66px; margin: 0 0 18px; color: #8992a5; font-size: 12px; line-height: 1.75; }.feature-card a { color: #1677ff; font-size: 11px; font-weight: 700; }.feature-card a i { margin-left: 4px; }
.metrics-strip { position: relative; padding: 48px 0 0; background: var(--public-soft); }
.metrics-strip__inner { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); min-height: 124px; border: 1px solid var(--public-border); border-radius: 12px; background: var(--public-surface); box-shadow: 0 4px 12px rgba(31,49,77,.04); }
.metric-item { display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 10px; border-right: 1px solid var(--public-border); }
.metric-item:last-child { border-right: 0; }
.metric-item strong { color: #4096ff; font-size: 36px; font-weight: 600; letter-spacing: -.03em; }
.metric-item span { color: var(--public-muted); font-size: 12px; }
.section-block--features { background: var(--public-soft); }
.section-kicker { display: inline-flex; font-size: 11px; font-weight: 600; letter-spacing: .12em; }
.showcase-section { background: #fff; }.section-heading--left { max-width: 680px; margin-left: 0; margin-right: 0; text-align: left; }.section-heading--left h2 { max-width: 620px; }.showcase-grid { display: grid; grid-template-columns: .72fr 1.28fr; gap: 55px; align-items: center; }.showcase-tabs { display: grid; gap: 7px; }.showcase-tab { display: grid; grid-template-columns: 42px 1fr 18px; align-items: center; gap: 14px; width: 100%; padding: 15px 14px; border: 1px solid transparent; border-radius: 11px; color: #657089; background: transparent; text-align: left; cursor: pointer; transition: .2s; }.showcase-tab:hover { background: #f7f8fd; }.showcase-tab.is-active { border-color: #cfe2ff; color: #252d48; background: #eef5ff; box-shadow: 0 8px 20px rgba(22,119,255,.07); }.showcase-tab__icon { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; color: #1677ff; background: #eaf4ff; font-size: 17px; }.showcase-tab:nth-child(2) .showcase-tab__icon { color: #3989e8; background: #eaf4ff; }.showcase-tab:nth-child(3) .showcase-tab__icon { color: #e99a39; background: #fff3e4; }.showcase-tab:nth-child(4) .showcase-tab__icon { color: #2eaf82; background: #e4f8f0; }.showcase-tab b,.showcase-tab small { display: block; }.showcase-tab b { margin-bottom: 4px; font-size: 13px; }.showcase-tab small { color: #9ba3b5; font-size: 11px; }.showcase-tab__arrow { color: #a4acbd; font-size: 13px; }.showcase-tab.is-active .showcase-tab__arrow { color: #1677ff; }.showcase-link { display: inline-flex; align-items: center; gap: 7px; margin: 18px 0 0 14px; color: #1677ff; font-size: 12px; font-weight: 700; }.showcase-preview { min-height: 390px; overflow: hidden; border: 1px solid #e4e7f1; border-radius: 16px; background: #f6f8fd; box-shadow: 0 22px 55px rgba(40,49,92,.11); }.showcase-preview__bar { display: flex; align-items: center; gap: 11px; height: 39px; padding: 0 16px; color: #9aa4b9; border-bottom: 1px solid #e6e9f2; background: #fff; font-size: 9px; }.showcase-preview__bar > div { display: flex; gap: 5px; }.showcase-preview__bar span { width: 7px; height: 7px; border-radius: 50%; background: #ff9999; }.showcase-preview__bar span:nth-child(2) { background: #ffd47d; }.showcase-preview__bar span:nth-child(3) { background: #7bd8ac; }.showcase-preview__bar b { margin: auto; font-weight: 600; }.showcase-preview__bar > i { font-size: 14px; }.console-mock,.profile-mock { padding: 27px 30px; color: #1e2944; }.console-mock__top,.profile-mock__body { display: flex; justify-content: space-between; align-items: center; }.console-mock__top small { display: block; color: #a0a9bb; font-size: 8px; letter-spacing: .08em; }.console-mock__top strong { display: block; margin-top: 5px; font-size: 19px; }.mock-user { display: grid; place-items: center; width: 29px; height: 29px; color: #fff; border-radius: 50%; background: #1677ff; font-size: 11px; }.console-mock__cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 23px 0 14px; }.console-mock__cards > div { padding: 13px; border: 1px solid #e8ebf3; border-radius: 9px; background: #fff; }.console-mock__cards i { display: block; margin-bottom: 8px; color: #1677ff; font-size: 15px; }.console-mock__cards small,.console-mock__cards strong,.console-mock__cards em { display: block; }.console-mock__cards small { color: #9aa4b9; font-size: 8px; }.console-mock__cards strong { margin-top: 4px; font-size: 17px; }.console-mock__cards em { margin-top: 4px; color: #32b687; font-size: 8px; font-style: normal; }.console-mock__chart { padding: 15px 17px 10px; border: 1px solid #e8ebf3; border-radius: 9px; background: #fff; }.mock-chart-head { display: flex; justify-content: space-between; font-size: 10px; }.mock-chart-head span { color: #9ca6b9; font-size: 8px; }.mock-line { display: flex; align-items: end; gap: 12px; height: 120px; padding-top: 16px; }.mock-line i { flex: 1; border-radius: 4px 4px 0 0; background: linear-gradient(180deg,#409eff,#b8ddff); }.mock-rows { display: grid; gap: 8px; padding-top: 15px; }.mock-rows span { display: grid; grid-template-columns: 18px 1fr 40px; gap: 9px; align-items: center; }.mock-rows i,.mock-rows b,.mock-rows em { display: block; height: 6px; border-radius: 4px; background: #e1e5ef; }.mock-rows i { width: 18px; height: 18px; background: #cfe2ff; }.mock-rows em { background: #d1f2e5; }.profile-mock__cover { position: relative; height: 104px; overflow: hidden; border-radius: 11px; background: linear-gradient(120deg,#0b2d5c,#1677ff); }.profile-mock__pattern { position: absolute; inset: 0; opacity: .24; background-image: radial-gradient(circle at 20% 40%, #fff 0 1px, transparent 2px), radial-gradient(circle at 70% 60%, #fff 0 1px, transparent 2px); background-size: 26px 26px, 33px 33px; }.profile-mock__avatar { position: absolute; bottom: -23px; left: 24px; display: grid; place-items: center; width: 70px; height: 70px; border: 5px solid #fff; border-radius: 50%; color: #fff; background: #409eff; font-size: 25px; font-weight: 700; box-shadow: 0 5px 13px rgba(22,119,255,.15); }.profile-mock__body { padding: 31px 4px 19px; border-bottom: 1px solid #e8ebf3; }.profile-mock__body strong,.profile-mock__body small { display: block; }.profile-mock__body strong { font-size: 17px; }.profile-mock__body small { margin-top: 4px; color: #9aa4b9; font-size: 10px; }.profile-status { display: inline-flex; align-items: center; gap: 5px; padding: 6px 9px; border-radius: 6px; color: #249b70; background: #e8f8f1; font-size: 9px; }.profile-mock__details { display: grid; gap: 9px; padding-top: 18px; }.profile-mock__details > div { display: grid; grid-template-columns: 27px 1fr 13px; gap: 10px; align-items: center; padding: 11px 12px; border: 1px solid #edf0f6; border-radius: 8px; background: #fff; }.profile-mock__details > div > i:first-child { color: #1677ff; }.profile-mock__details span { font-size: 11px; font-weight: 700; }.profile-mock__details small { display: block; margin-top: 3px; color: #9aa4b9; font-size: 9px; font-weight: 400; }.profile-mock__details > div > i:last-child { color: #a4acbd; }
.architecture-section { padding: 80px 0 56px; background: var(--public-soft); }
.architecture-grid { display: grid; grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr); align-items: center; gap: 80px; }
.architecture-copy { min-width: 0; }
.architecture-copy h2 { max-width: none; margin: 18px 0; color: var(--public-text); font-size: clamp(28px, 3vw, 40px); line-height: 1.35; letter-spacing: -.03em; }
.architecture-title__line { display: block; text-wrap: balance; }
.architecture-title__line:last-child { color: #1677ff; }
.architecture-copy > p { max-width: 490px; color: var(--public-muted); font-size: 14px; line-height: 1.95; }
.architecture-copy ul { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 12px; margin: 26px 0 0; padding: 0; list-style: none; color: var(--public-muted); font-size: 12px; }
.architecture-copy li { display: flex; align-items: start; gap: 8px; line-height: 1.7; }
.architecture-copy li i { flex-shrink: 0; margin-top: 4px; color: #1677ff; }
.architecture-diagram { min-width: 0; padding: 24px; border: 1px solid var(--public-border); border-radius: 16px; background: var(--public-surface); box-shadow: 0 8px 32px rgba(31,49,77,.035); }
.architecture-diagram__heading { display: flex; justify-content: space-between; gap: 12px; padding-bottom: 18px; color: var(--public-muted); font-size: 11px; }
.architecture-diagram__heading > span:last-child { font-family: ui-monospace, SFMono-Regular, monospace; color: #1677ff; }
.architecture-layers { position: relative; display: grid; gap: 14px; }
.architecture-layers::before { content: ''; position: absolute; left: 37px; top: 28px; bottom: 28px; width: 1px; background: var(--public-border); }
.architecture-layer { position: relative; display: grid; grid-template-columns: 38px minmax(0, 1fr) 20px; align-items: start; gap: 16px; padding: 18px; border: 1px solid var(--public-border); border-radius: 10px; background: var(--public-surface); }
.architecture-layer__icon { display: grid; place-items: center; width: 38px; height: 38px; border: 1px solid var(--public-border); border-radius: 9px; color: #1677ff; background: var(--public-cta-bg); font-size: 19px; }
.architecture-layer:nth-child(2) .architecture-layer__icon { color: #08979c; }
.architecture-layer:nth-child(3) .architecture-layer__icon { color: var(--public-muted); }
.architecture-layer__body { min-width: 0; }
.architecture-layer h3 { margin: 0; color: var(--public-text); font-size: 15px; font-weight: 600; line-height: 1.6; }
.architecture-layer__description { margin: 5px 0 10px; color: var(--public-muted); font-size: 12px; line-height: 1.75; }
.architecture-layer__tags { display: flex; flex-wrap: wrap; gap: 6px; }
.architecture-layer__tags span { padding: 2px 7px; border: 1px solid var(--public-border); border-radius: 4px; color: var(--public-muted); background: var(--public-soft); font: 10px/1.6 ui-monospace, SFMono-Regular, monospace; }
.architecture-layer__number { color: var(--public-muted); opacity: .65; font: 11px/1.8 ui-monospace, SFMono-Regular, monospace; }
.quick-section { padding: 24px 0 48px; background: var(--public-soft); }
.quick-card { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 32px; padding: 36px 40px; border: 1px solid var(--public-border); border-radius: 12px; color: var(--public-text); background: var(--public-cta-bg); box-shadow: none; }
.quick-card__copy { min-width: 0; }
.quick-card .section-kicker { color: #1677ff; font-size: 10px; letter-spacing: .14em; }
.quick-card h2 { margin: 12px 0 10px; color: var(--public-text); font-size: clamp(24px, 2.2vw, 32px); font-weight: 600; line-height: 1.4; letter-spacing: -.025em; overflow-wrap: anywhere; }
.quick-card p { margin: 0; color: var(--public-muted); font-size: 14px; line-height: 1.8; }
.quick-card__actions { display: flex; flex-direction: column; align-items: stretch; gap: 10px; }
.quick-card__actions a { white-space: nowrap; }
.public-footer { border-top: 1px solid var(--public-border); color: var(--public-muted); background: var(--public-surface); font-size: 12px; }
.public-footer__inner { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 32px 0 26px; }
.public-footer .brand { color: var(--public-text); font-size: 18px; }
.public-footer .brand :deep(.logo-mark) { color: #1677ff; }
.public-footer__brand p { margin: 12px 0 0; font-size: 12px; line-height: 1.8; }
.public-footer__nav { display: flex; flex-wrap: wrap; gap: 24px; }
.public-footer__nav a { color: var(--public-muted); line-height: 2; transition: color .2s; }
.public-footer__nav a:hover { color: #1677ff; }
.public-footer__bottom { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 0 22px; border-top: 1px solid var(--public-border); font-size: 11px; line-height: 1.8; }
.public-footer__bottom a { display: inline-flex; align-items: center; gap: 6px; color: #1677ff; }
@media (max-width: 900px) { .public-nav { display: none; }.hero-section__inner { grid-template-columns: 1fr; padding-top: 56px; padding-bottom: 40px; }.hero-section { min-height: auto; }.hero-copy { margin: 0 auto; text-align: center; }.hero-labels,.hero-copy__actions,.hero-trust { justify-content: center; }.hero-preview { box-sizing: border-box; }.hero-section__inner { gap: 28px; }.tech-strip { justify-content: center; text-align: center; }.tech-strip__tags { justify-content: center; }.hero-copy__desc { margin: auto; }.hero-preview { max-width: 650px; width: 100%; margin: auto; }.feature-grid { grid-template-columns: repeat(2,1fr); }.showcase-grid { grid-template-columns: 1fr; gap: 34px; }.showcase-tabs { max-width: 650px; width: 100%; margin: auto; }.architecture-grid { grid-template-columns: 1fr; gap: 30px; }.architecture-copy { text-align: center; }.architecture-copy h2,.architecture-copy > p { margin-left: auto; margin-right: auto; }.architecture-copy ul { justify-content: center; }.architecture-diagram { max-width: 650px; width: 100%; margin: auto; }.architecture-copy ul { max-width: 490px; margin-left: auto; margin-right: auto; text-align: left; } }
@media (max-width: 600px) { .public-container { width: min(100% - 32px, 1180px); }.public-header__inner { height: 65px; }.brand { font-size: 17px; }.locale-button { display: none; }.header-login { padding: 8px 11px; }.hero-copy h1 { font-size: 42px; }.hero-copy__desc { font-size: 14px; }.preview-window { transform: none; }.preview-badge { font-size: 9px; }.preview-badge--top { left: -4px; right: auto; } .preview-badge--bottom { right: -4px; left: auto; }.section-block { padding: 75px 0; }.feature-grid { grid-template-columns: 1fr; }.feature-card { min-height: 0; }.metrics-strip__inner { grid-template-columns: repeat(2, 1fr); }.metric-item { min-height: 86px; }.metric-item:nth-child(3) { border-left: 0; }.showcase-preview { min-height: 340px; }.console-mock,.profile-mock { padding: 20px 16px; }.console-mock__cards { gap: 6px; }.console-mock__cards > div { padding: 9px 7px; }.console-mock__cards strong { font-size: 13px; }.mock-line { gap: 7px; }.profile-mock__body { align-items: flex-start; gap: 12px; flex-direction: column; }.quick-section { padding: 20px 0 32px; }.architecture-section { padding-bottom: 40px; }.quick-card { grid-template-columns: minmax(0, 1fr); gap: 24px; padding: 26px 22px; }.quick-card__actions { flex-direction: row; flex-wrap: wrap; }.quick-card__actions a { flex: 1 1 150px; }.public-footer__inner { align-items: flex-start; flex-direction: column; gap: 20px; padding: 26px 0 22px; }.public-footer__nav { gap: 20px; }.public-footer__bottom { align-items: flex-start; flex-wrap: wrap; }.architecture-diagram { padding: 16px; }.architecture-layer { padding: 14px; gap: 10px; grid-template-columns: 32px minmax(0, 1fr); }.architecture-layer__icon { width: 32px; height: 32px; font-size: 17px; }.architecture-layer__number { display: none; }.architecture-copy ul { grid-template-columns: minmax(0, 1fr); max-width: 320px; } }
@media (max-width: 600px) { .hero-copy__actions,.hero-trust { justify-content: center; }.public-header__inner { gap: 10px; }.public-header__actions { gap: 10px; }.admin-entry,.header-register { display: none; }.hero-copy h1 { font-size: 38px; }.hero-section__inner { padding-top: 38px; }.hero-labels { gap: 8px; }.eyebrow,.hero-version { padding: 6px 10px; font-size: 10px; }.preview-window__bar { gap: 10px; padding: 0 10px; }.preview-tabs button { padding: 0 8px; font-size: 10px; }.preview-content { padding: 16px 12px; }.preview-sidebar { flex-basis: 34px; }.preview-stat { padding: 8px; }.preview-stat strong { font-size: 14px; }.preview-badge--top { top: 76px; left: -4px; }.preview-badge { padding: 9px 10px; }.preview-badge--bottom { right: -4px; }.metrics-strip { padding-top: 28px; }.metric-item { padding: 18px 0; }.metric-item:nth-child(2) { border-right: 0; }.metric-item:nth-child(-n+2) { border-bottom: 1px solid var(--public-border); }.metrics-strip__inner { width: calc(100% - 32px); } }
:global(html.dark) .public-site {
  --public-hero-bg: #111b29;
  --public-surface: #161f2b;
  --public-soft: #111925;
  --public-text: #e5eaf1;
  --public-muted: #9ba8b9;
  --public-border: #2a3545;
  --public-cta-bg: #182c42;
  --public-grid: rgba(151,181,214,.06);
}
:global(html.dark) .eyebrow { background: rgba(22,119,255,.1); border-color: #294d77; color: #69b1ff; }
:global(html.dark) .section-heading h2,:global(html.dark) .architecture-copy h2 { color: var(--public-text); }
:global(html.dark) .section-heading p,:global(html.dark) .architecture-copy > p { color: var(--public-muted); }
:global(html.dark) .feature-card { border-color: var(--public-border); background: var(--public-surface); box-shadow: none; }
:global(html.dark) .feature-card h3 { color: var(--public-text); }
:global(html.dark) .feature-card p { color: var(--public-muted); }
:global(html.dark) .architecture-section,:global(html.dark) .showcase-section { background: var(--public-surface); }
:global(html.dark) .architecture-copy ul { color: var(--public-muted); }
:global(html.dark) .public-footer { border-color: var(--public-border); color: var(--public-muted); }
:global(html.dark) .public-footer .brand { color: var(--public-text); }
</style>
