<template>
  <div
    class="app-layout"
    :class="[
      `layout-mode--${appStore.layoutMode}`,
      `menu-theme--${appStore.menuTheme}`,
      {
        'is-collapsed': appStore.sidebarCollapsed,
        'is-content-full': appStore.contentFull,
        'is-mobile': appStore.isMobile,
        'mobile-drawer-open': appStore.isMobile && appStore.mobileDrawerOpen
      }
    ]"
  >
    <!-- ============ 移动端通用：全屏遮罩 + 抽屉侧栏 ============ -->
    <template v-if="appStore.isMobile">
      <transition name="mask-fade">
        <div
          v-if="appStore.mobileDrawerOpen"
          class="app-layout__mobile-mask"
          @click="appStore.closeMobileDrawer()"
        />
      </transition>
      <Sidebar class="app-layout__sidebar app-layout__sidebar--mobile" />
    </template>

    <!-- ============ 1. 垂直布局：左侧栏 + 右容器 ============ -->
    <template v-if="appStore.layoutMode === 'vertical'">
      <Sidebar v-if="!appStore.contentFull && !appStore.isMobile" class="app-layout__sidebar" />
      <section class="app-layout__container">
        <Header
          class="app-layout__header"
          :class="{ 'is-fixed': appStore.fixedHeader }"
        />
        <Tabs v-if="appStore.showTabs" class="app-layout__tabs" />
        <main class="app-layout__main">
          <LayoutRouterView />
        </main>
        <LayoutFooter v-if="appStore.showFooter" />
      </section>
    </template>

    <!-- ============ 2. 水平布局：顶部 Header(含全菜单) + 容器 ============ -->
    <template v-else-if="appStore.layoutMode === 'horizontal'">
      <section class="app-layout__container is-vertical">
        <header class="app-layout__topbar">
          <button v-if="appStore.isMobile" class="app-topbar-hamburger" @click="appStore.mobileDrawerOpen ? appStore.closeMobileDrawer() : appStore.openMobileDrawer()">
            <i :class="appStore.mobileDrawerOpen ? 'i-ep-close' : 'i-ep-expand'" />
          </button>
          <Logo v-if="appStore.showLogo" class="app-layout__logo" :collapsed="false" />
          <TopMenu v-if="!appStore.isMobile" mode="horizontal" />
          <div class="app-layout__topbar-actions">
            <HeaderActions />
          </div>
        </header>
        <Tabs v-if="appStore.showTabs" class="app-layout__tabs" />
        <main class="app-layout__main">
          <LayoutRouterView />
        </main>
        <LayoutFooter v-if="appStore.showFooter" />
      </section>
    </template>

    <!-- ============ 3. 混合布局：顶部一级菜单 + 左侧二级 + 容器 ============ -->
    <template v-else-if="appStore.layoutMode === 'mix'">
      <section class="app-layout__container is-vertical">
        <header class="app-layout__topbar">
          <button v-if="appStore.isMobile" class="app-topbar-hamburger" @click="appStore.mobileDrawerOpen ? appStore.closeMobileDrawer() : appStore.openMobileDrawer()">
            <i :class="appStore.mobileDrawerOpen ? 'i-ep-close' : 'i-ep-expand'" />
          </button>
          <Logo v-if="appStore.showLogo" class="app-layout__logo" :collapsed="false" />
          <TopMenu v-if="!appStore.isMobile" mode="mix" />
          <div class="app-layout__topbar-actions">
            <HeaderActions />
          </div>
        </header>
        <div class="app-layout__mix-body">
          <Sidebar
            v-if="!appStore.contentFull && !appStore.isMobile && currentRootChildren.length"
            :routes="currentRootChildren"
            :menu-base-path="activeRootPath"
            :show-logo="false"
            submenu-pane
            class="app-layout__sidebar app-layout__sidebar--mix"
          />
          <section class="app-layout__inner">
            <Tabs v-if="appStore.showTabs" class="app-layout__tabs" />
            <main class="app-layout__main">
              <LayoutRouterView />
            </main>
            <LayoutFooter v-if="appStore.showFooter" />
          </section>
        </div>
      </section>
    </template>

    <!-- ============ 4. 双列布局：Logo 整行 + 窄活动栏 + 二级菜单栏 + 容器 ============ -->
    <template v-else-if="appStore.layoutMode === 'columns'">
      <div
        v-if="!appStore.contentFull && !appStore.isMobile"
        class="app-layout__columns-aside"
        :class="{ 'is-rail-only': !currentRootChildren.length }"
      >
        <div v-if="appStore.showLogo" class="app-layout__columns-logo">
          <Logo :collapsed="!currentRootChildren.length" />
        </div>
        <div class="app-layout__columns-menus">
          <ColumnsRail :show-logo="false" />
          <Sidebar
            v-if="currentRootChildren.length"
            :routes="currentRootChildren"
            :menu-base-path="activeRootPath"
            :show-logo="false"
            submenu-pane
            class="app-layout__sidebar app-layout__sidebar--columns"
          />
        </div>
      </div>
      <section class="app-layout__container">
        <Header
          class="app-layout__header"
          :class="{ 'is-fixed': appStore.fixedHeader }"
        />
        <Tabs v-if="appStore.showTabs" class="app-layout__tabs" />
        <main class="app-layout__main">
          <LayoutRouterView />
        </main>
        <LayoutFooter v-if="appStore.showFooter" />
      </section>
    </template>

    <Setting />
    <MenuSearch />
  </div>
</template>

<script setup lang="ts">
import { computed, watch, h } from 'vue';
import { useRoute, type RouteRecordRaw } from 'vue-router';
import { useAppStore } from '@/store/modules/app';
import { useTabsStore } from '@/store/modules/tabs';
import { usePermissionStore } from '@/store/modules/permission';
import Sidebar from './components/Sidebar.vue';
import Header from './components/Header.vue';
import Tabs from './components/Tabs.vue';
import Logo from './components/Logo.vue';
import TopMenu from './components/TopMenu.vue';
import ColumnsRail from './components/ColumnsRail.vue';
import HeaderActions from './components/HeaderActions.vue';
import Setting from '@/components/Setting/index.vue';
import MenuSearch from '@/components/MenuSearch/index.vue';
import LayoutRouterView from './components/LayoutRouterView.vue';
import { APP_CONFIG } from '@/config';
import { getMenuActiveRootPath } from '@/utils/route';

const appStore = useAppStore();
const tabsStore = useTabsStore();
const permissionStore = usePermissionStore();
const route = useRoute();

watch(
  () => route.fullPath,
  () => tabsStore.addTab(route),
  { immediate: true }
);

/** 当前激活的一级菜单 path（mix / columns：与 route.path 首段一致，避免 matched 相对 path 对不齐） */
const activeRootPath = computed(() => getMenuActiveRootPath(route));

const dashboardItem: RouteRecordRaw = {
  path: '/dashboard',
  name: 'Dashboard',
  meta: { title: '仪表盘', icon: 'i-ep-monitor' }
} as RouteRecordRaw;

const allMenus = computed<RouteRecordRaw[]>(() => {
  const list: RouteRecordRaw[] = [dashboardItem];
  permissionStore.dynamicRoutes.forEach((r) => {
    if (r.meta?.hidden) return;
    list.push(r);
  });
  return list;
});

/** 当前一级菜单下可见的二级路由（用于 mix / columns 模式的 Sidebar） */
const currentRootChildren = computed<RouteRecordRaw[]>(() => {
  const root = allMenus.value.find((m) => {
    const path = m.path.startsWith('/') ? m.path : `/${m.path}`;
    return path === activeRootPath.value;
  });
  if (!root || !root.children) return [];
  return root.children.filter((c) => !c.meta?.hidden);
});

/** 简化页脚渲染 */
const LayoutFooter = {
  setup() {
    const year = new Date().getFullYear();
    return () =>
      h(
        'footer',
        { class: 'app-layout__footer' },
        `© ${year} ${APP_CONFIG.title} · v${APP_CONFIG.version} · Powered by Vue 3 + Element Plus`
      );
  }
};

</script>

<style scoped>
.app-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: var(--app-app-bg);
}

/* ===== 公用：sidebar / container / main / header / tabs / footer ===== */
.app-layout__sidebar {
  flex-shrink: 0;
  width: var(--app-sidebar-w);
  transition: width 0.25s cubic-bezier(0.34, 0.69, 0.1, 1);
  background: var(--app-sidebar-bg);
  color: var(--app-sidebar-text);
  box-shadow: var(--app-shadow-sm);
  z-index: 10;
}
.app-layout.is-collapsed .app-layout__sidebar {
  width: var(--app-sidebar-collapsed-w);
}
/* 混合/双列的次级菜单栏：保持常规宽度，与左侧导航栏并列展示文案菜单 */
.app-layout.is-collapsed .app-layout__sidebar--mix,
.app-layout.is-collapsed .app-layout__sidebar--columns {
  width: var(--app-sidebar-w);
}
.app-layout__sidebar--mix,
.app-layout__sidebar--columns {
  border-right: 1px solid var(--app-sidebar-separator);
  box-shadow: none;
}

/* 浅色侧栏 */
.app-layout.menu-theme--light .app-layout__sidebar {
  background: #fff;
  color: var(--app-sidebar-text);
  border-right: 1px solid var(--app-border);
}
/* 清新侧栏（loading 同款：浅彩 radial-gradient + 白色卡片激活态） */
.app-layout.menu-theme--fresh .app-layout__sidebar {
  position: relative;
  background:
    radial-gradient(ellipse at 15% 18%, #e0f2fe 0%, transparent 55%),
    radial-gradient(ellipse at 85% 12%, #fce7f3 0%, transparent 50%),
    radial-gradient(ellipse at 80% 88%, #d1fae5 0%, transparent 55%),
    radial-gradient(ellipse at 20% 70%, #fef3c7 0%, transparent 45%),
    linear-gradient(180deg, #fdfcfb 0%, #f5f7fa 100%);
  border-right: 1px solid rgba(91, 141, 239, 0.08);
  box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.6);
  /* 与面包屑/标签未选中字色同令牌，避免侧栏单独偏灰蓝 */
  --app-sidebar-text: var(--app-text-secondary);
  --app-sidebar-text-active: var(--el-color-primary);
  --app-sidebar-active-bg: #ffffff;
  --app-sidebar-hover-bg: rgba(255, 255, 255, 0.7);
  --app-sidebar-logo-bg: transparent;
}
/* 菜单项：圆角 + 更舒适留白 */
.app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-menu-item),
.app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-sub-menu__title) {
  border-radius: 10px;
  margin: 4px 12px;
  transition:
    background-color 0.2s ease,
    color 0.2s ease,
    box-shadow 0.25s ease;
}
/* hover：半透明白卡片（仅非夜间，避免与 html.dark 侧栏冲突） */
html:not(.dark) .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-menu-item:hover),
html:not(.dark) .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-sub-menu__title:hover) {
  background-color: rgba(255, 255, 255, 0.7) !important;
  color: var(--theme-color) !important;
}
/* 激活态：纯白卡片 + 主色字（仅非夜间；夜间由下方 html.dark 与 Sidebar 接管） */
html:not(.dark) .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-menu-item.is-active) {
  background-color: #ffffff !important;
  color: var(--theme-color) !important;
  box-shadow:
    0 4px 12px -2px color-mix(in srgb, var(--el-color-primary) 22%, transparent),
    0 0 0 1px color-mix(in srgb, var(--el-color-primary) 8%, transparent);
  position: relative;
}
html:not(.dark) .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-menu-item.is-active .app-menu-icon),
html:not(.dark) .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-menu-item.is-active .truncate) {
  color: var(--theme-color) !important;
}
html:not(.dark) .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-menu-item.is-active)::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 18px;
  border-radius: 0 3px 3px 0;
  background: var(--theme-color);
}
html:not(.dark) .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-sub-menu.is-active > .el-sub-menu__title) {
  /* 父级仅表示展开，不与当前页主色抢戏，与默认侧栏一致 */
  color: var(--app-sidebar-text) !important;
  background-color: transparent !important;
  font-weight: 500;
}
/* 二级菜单：透明，让浅彩底透出 */
.app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-menu .el-menu) {
  background: transparent !important;
}

.app-layout__container {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
  min-width: 0;
}
.app-layout__container.is-vertical {
  flex-direction: column;
}

.app-layout__header {
  flex-shrink: 0;
  height: var(--app-header-h);
  background: var(--app-header-bg);
  box-shadow: var(--app-header-shadow);
  z-index: 9;
}

.app-layout__tabs {
  flex-shrink: 0;
  height: var(--app-tabs-h);
  background: var(--app-tabs-bg);
  /* 顶栏与标签栏同底时加顶部分割，层次更清晰 */
  border-top: 1px solid color-mix(in srgb, var(--app-border) 65%, transparent);
  box-shadow: var(--app-tabs-shadow);
  z-index: 8;
}

.app-layout__main {
  flex: 1;
  overflow: auto;
  padding: var(--app-gap);
  background: var(--app-content-bg);
  display: flex;
  flex-direction: column;
}
.app-layout__main > * {
  flex: 1;
  min-height: 0;
}

.app-layout__footer {
  flex-shrink: 0;
  text-align: center;
  font-size: 12px;
  color: var(--app-text-secondary);
  padding: 12px 0;
  border-top: 1px solid var(--app-border);
  background: var(--app-card-bg);
}

/* ===== horizontal / mix 顶栏 ===== */
.app-layout__topbar {
  height: var(--app-header-h);
  min-height: var(--app-header-h);
  flex-shrink: 0;
  display: flex;
  /* stretch：避免子项在 align-items:center 下 height:100% 不生效，顶栏菜单实际高度卡在 ~60px */
  align-items: stretch;
  background: var(--app-header-bg);
  box-shadow: var(--app-header-shadow);
  padding: 0 16px 0 0;
  z-index: 9;
}
.app-layout__logo {
  flex-shrink: 0;
  width: var(--app-sidebar-w);
  height: auto;
  align-self: stretch;
  display: flex;
  align-items: center;
  justify-content: center;
}
.app-layout__topbar-actions {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  align-self: stretch;
  margin-left: 12px;
}

/* mix 模式：顶部之下再分栏 */
.app-layout__mix-body {
  flex: 1;
  display: flex;
  overflow: hidden;
  min-height: 0;
}
.app-layout__inner {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-width: 0;
}

/* 双列：Logo 独占顶部一整行（横跨窄轨 + 次级菜单宽度） */
.app-layout__columns-aside {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  height: 100%;
  z-index: 10;
  background: var(--app-sidebar-bg);
  box-shadow: var(--app-shadow-sm);
  color: var(--app-sidebar-text);
}
.app-layout__columns-logo {
  flex-shrink: 0;
  width: 100%;
}
.app-layout__columns-logo :deep(.app-logo) {
  width: 100%;
  box-sizing: border-box;
  border-bottom: 1px solid var(--app-sidebar-separator);
}
.app-layout__columns-aside.is-rail-only .app-layout__columns-logo {
  width: 72px;
}
.app-layout__columns-aside.is-rail-only .app-layout__columns-logo :deep(.app-logo) {
  padding: 0;
}
.app-layout__columns-menus {
  flex: 1;
  display: flex;
  min-height: 0;
  overflow: hidden;
}
.app-layout.menu-theme--light .app-layout__columns-aside {
  background: #fff;
  color: var(--app-sidebar-text);
  border-right: 1px solid var(--app-border);
  box-shadow: none;
}
.app-layout.menu-theme--fresh .app-layout__columns-aside {
  position: relative;
  background:
    radial-gradient(ellipse at 15% 18%, #e0f2fe 0%, transparent 55%),
    radial-gradient(ellipse at 85% 12%, #fce7f3 0%, transparent 50%),
    radial-gradient(ellipse at 80% 88%, #d1fae5 0%, transparent 55%),
    radial-gradient(ellipse at 20% 70%, #fef3c7 0%, transparent 45%),
    linear-gradient(180deg, #fdfcfb 0%, #f5f7fa 100%);
  border-right: 1px solid rgba(91, 141, 239, 0.08);
  box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.6);
}
html.dark .app-layout.menu-theme--light .app-layout__columns-aside,
html.dark .app-layout.menu-theme--fresh .app-layout__columns-aside {
  background: var(--app-sidebar-bg);
  color: var(--app-sidebar-text);
  border-right: 1px solid var(--app-border);
  box-shadow: none;
}

/* ============== 夜间模式覆盖：让 menuTheme(light/fresh) 的浅色硬编码回退到 dark 变量 ============== */
html.dark .app-layout.menu-theme--light .app-layout__sidebar,
html.dark .app-layout.menu-theme--fresh .app-layout__sidebar {
  background: var(--app-sidebar-bg);
  color: var(--app-sidebar-text);
  border-right: 1px solid var(--app-border);
  box-shadow: none;
}

html.dark .app-layout.menu-theme--fresh .app-layout__sidebar {
  --app-sidebar-text: var(--app-text-secondary);
  --app-sidebar-text-active: #ffffff;
  --app-sidebar-active-bg: rgba(255, 255, 255, 0.08);
  --app-sidebar-hover-bg: rgba(255, 255, 255, 0.05);
  --app-sidebar-logo-bg: rgba(255, 255, 255, 0.03);
}

html.dark .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-sub-menu.is-active > .el-sub-menu__title) {
  color: var(--app-sidebar-text) !important;
  font-weight: 500;
}

html.dark .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-menu-item:hover),
html.dark .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-sub-menu__title:hover) {
  background-color: rgba(255, 255, 255, 0.05) !important;
  color: var(--app-sidebar-text-active) !important;
}

html.dark .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-menu-item.is-active) {
  background-color: rgba(255, 255, 255, 0.1) !important;
  color: #f0f6fc !important;
  --el-menu-active-color: #f0f6fc !important;
  box-shadow: none;
}
html.dark .app-layout.menu-theme--fresh .app-layout__sidebar :deep(.el-menu-item.is-active .app-menu-icon) {
  color: #f0f6fc !important;
  opacity: 1 !important;
}

html.dark .app-layout.menu-theme--light .app-layout__sidebar :deep(.el-menu-item.is-active) {
  background-color: var(--app-sidebar-active-bg) !important;
  color: #f0f6fc !important;
  --el-menu-active-color: #f0f6fc !important;
  box-shadow: none;
}
html.dark .app-layout.menu-theme--light .app-layout__sidebar :deep(.el-menu-item.is-active .app-menu-icon) {
  color: #f0f6fc !important;
  opacity: 1 !important;
}

/* ============================================================
   小屏适配：< 1024px（平板 + 手机）
   策略：
   - 侧栏变成 fixed 抽屉（浮窗），从左侧滑入，不占位
   - 内容区始终全宽
   - 深色半透明遮罩覆盖内容区，点击关闭
   ============================================================ */

/* 遮罩 & 动画：不依赖媒体查询，由 v-if 控制 */
.app-layout__mobile-mask {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 1000;
}
.mask-fade-enter-active,
.mask-fade-leave-active {
  transition: opacity 0.25s;
}
.mask-fade-enter-from,
.mask-fade-leave-to {
  opacity: 0;
}

/* 移动端抽屉侧栏：始终 fixed，默认在屏幕左侧外隐藏 */
.app-layout__sidebar--mobile {
  position: fixed !important;
  left: 0;
  top: 0;
  height: 100vh !important;
  width: 260px !important;
  z-index: 1001;
  transform: translateX(-100%);
  transition:
    transform 0.28s cubic-bezier(0.34, 0.69, 0.1, 1),
    box-shadow 0.28s;
  background: var(--app-sidebar-bg);
  box-shadow: none;
}
/* 抽屉展开 */
.app-layout.mobile-drawer-open .app-layout__sidebar--mobile {
  transform: translateX(0);
  box-shadow: 8px 0 32px rgba(0, 0, 0, 0.22);
}

@media (max-width: 1023px) {
  /* 所有布局下，非 mobile 侧栏不在流中显示（由抽屉接管） */
  .app-layout.is-mobile .app-layout__sidebar:not(.app-layout__sidebar--mobile) {
    display: none !important;
  }

  /* 顶栏 Logo 区窄化 */
  .app-layout__logo {
    width: auto;
    min-width: 0;
    padding: 0 12px;
  }

  /* 主内容区全宽 */
  .app-layout.is-mobile .app-layout__container {
    width: 100%;
  }

  /* 内容区内边距缩小 */
  .app-layout.is-mobile .app-layout__main {
    padding: 12px;
  }

  /* Tabs 横向滚动 */
  .app-layout.is-mobile .app-layout__tabs :deep(.app-tabs__nav) {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  /* 水平/混合顶栏收紧 */
  .app-layout.is-mobile .app-layout__topbar {
    padding: 0 12px;
  }

  /* 手机极窄（< 640px）：侧栏宽度占 85vw，方便操作 */
  @media (max-width: 639px) {
    .app-layout__sidebar--mobile {
      width: min(85vw, 300px) !important;
    }
  }

}

/* 水平/混合模式下的移动端汉堡按钮（不限媒体查询，由 v-if 控制显示） */
.app-topbar-hamburger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  align-self: center;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border: 0;
  border-radius: var(--app-radius-sm);
  background: transparent;
  color: var(--app-text-secondary);
  font-size: 16px;
  cursor: pointer;
  transition: all 0.2s;
  margin-right: 4px;
}
.app-topbar-hamburger:hover {
  background: var(--el-fill-color-light);
  color: var(--el-color-primary);
}
</style>
