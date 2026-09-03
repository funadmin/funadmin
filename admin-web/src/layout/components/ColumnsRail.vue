<template>
  <aside class="app-rail" :class="{ 'is-icon-only': iconOnly }">
    <div v-if="showLogo && appStore.showLogo" class="app-rail__logo">
      <LogoMark :size="24" />
      <!-- 窄轨宽度有限：两行小字展示品牌，避免只有图标看起来像「空白」 -->
      <span v-show="!iconOnly" class="app-rail__brand">{{ appTitle }}</span>
    </div>
    <el-scrollbar class="app-rail__scroll">
      <div
        v-for="item in menus"
        :key="item.path"
        class="app-rail__item"
        :class="{ 'is-active': activeRoot === resolvePath(item) }"
        @click="onSelect(item)"
      >
        <i :class="(item.meta?.icon as string) || 'i-ep-document'" class="app-rail__icon" />
        <span v-show="!iconOnly" class="app-rail__label">{{ menuTitle(item) }}</span>
      </div>
    </el-scrollbar>

    <!-- 底部折叠切换按钮 -->
    <button
      class="app-rail__toggle"
      :title="railToggleTitle"
      @click="iconOnly = !iconOnly"
    >
      <i class="app-rail__toggle-icon" :class="iconOnly ? 'i-ep-d-arrow-right' : 'i-ep-d-arrow-left'" />
    </button>
  </aside>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter, type RouteRecordRaw } from 'vue-router';
import { useAppStore } from '@/store/modules/app';
import { usePermissionStore } from '@/store/modules/permission';
import { getFirstLeafRouteFullPath, getMenuActiveRootPath } from '@/utils/route';
import { useMenuTitle } from '@/composables/useMenuTitle';
import LogoMark from '@/components/LogoMark.vue';
import { APP_CONFIG } from '@/config';

withDefaults(
  defineProps<{
    /** 为 false 时由双列外层统一展示 Logo 整行，窄轨不再占位小 Logo */
    showLogo?: boolean;
  }>(),
  { showLogo: true },
);

const { t, locale } = useI18n();
const { menuTitle } = useMenuTitle();
const appTitle = APP_CONFIG.title;

const railToggleTitle = computed(() => {
  void locale.value;
  return iconOnly.value ? t('layout.railExpandLabels') : t('layout.railCollapseLabels');
});

const appStore = useAppStore();
const permissionStore = usePermissionStore();
const router = useRouter();
const route = useRoute();

/** 是否仅显示图标（隐藏文字标签） */
const iconOnly = ref(false);

const dashboardItem: RouteRecordRaw = {
  path: '/dashboard',
  name: 'Dashboard',
  meta: { title: '仪表盘', icon: 'i-ep-monitor' }
} as RouteRecordRaw;

const menus = computed<RouteRecordRaw[]>(() => {
  const list: RouteRecordRaw[] = [dashboardItem];
  permissionStore.dynamicRoutes.forEach((r) => {
    if (r.meta?.hidden) return;
    list.push(r);
  });
  return list;
});

function resolvePath(item: RouteRecordRaw): string {
  return item.path.startsWith('/') ? item.path : `/${item.path}`;
}

function visibleChildren(item: RouteRecordRaw): RouteRecordRaw[] {
  return (item.children || []).filter((c) => !c.meta?.hidden);
}

const activeRoot = computed(() => getMenuActiveRootPath(route));

function onSelect(item: RouteRecordRaw) {
  const rootPath = resolvePath(item);
  const children = visibleChildren(item);
  if (children.length) {
    const first = children[0];
    const target = getFirstLeafRouteFullPath(first, rootPath);
    router.push(target);
  } else {
    router.push(rootPath);
  }
}
</script>

<style scoped>
/* 展开态：显示图标 + 文字 */
.app-rail {
  width: 72px;
  flex-shrink: 0;
  background: var(--app-sidebar-bg);
  border-right: 1px solid var(--app-sidebar-separator);
  display: flex;
  flex-direction: column;
  height: 100%;
  z-index: 11;
  transition: width 0.22s cubic-bezier(0.34, 0.69, 0.1, 1);
}
/* 仅图标态：缩至 52px */
.app-rail.is-icon-only {
  width: 52px;
}

.app-rail__logo {
  min-height: var(--app-header-h);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  padding: 6px 4px;
  font-size: 22px;
  color: var(--el-color-primary);
  border-bottom: 1px solid var(--app-sidebar-separator);
  flex-shrink: 0;
  box-sizing: border-box;
}
.app-rail__brand {
  font-size: 9px;
  font-weight: 700;
  line-height: 1.15;
  text-align: center;
  color: var(--app-sidebar-brand-text);
  max-width: 64px;
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  word-break: break-all;
}
.app-rail__scroll {
  flex: 1;
  padding: 8px 0;
}
.app-rail__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 10px 4px;
  margin: 4px auto;
  width: calc(100% - 16px);
  max-width: 100%;
  box-sizing: border-box;
  border-radius: var(--app-radius);
  color: var(--app-sidebar-text);
  cursor: pointer;
  transition: all 0.18s;
  font-size: 11px;
  text-align: center;
  line-height: 1.2;
}
.app-rail.is-icon-only .app-rail__item {
  margin: 4px auto;
  width: calc(100% - 12px);
  padding: 10px 4px;
}
.app-rail__item:hover {
  background: var(--app-sidebar-hover-bg);
  color: var(--el-color-primary);
}
.app-rail__item.is-active {
  background: var(--app-sidebar-active-bg);
  color: var(--app-sidebar-text-active, var(--el-color-primary));
  font-weight: 600;
}
.app-rail__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  flex-shrink: 0;
  margin: 0 auto;
}
.app-rail__label {
  display: block;
  width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* 底部切换按钮 */
.app-rail__toggle {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 40px;
  margin: 4px 8px 12px;
  border-radius: var(--app-radius);
  border: 1px solid var(--app-sidebar-separator);
  background: transparent;
  color: var(--app-sidebar-text);
  cursor: pointer;
  transition: all 0.18s;
}
.app-rail__toggle:hover {
  background: var(--app-sidebar-hover-bg);
  color: var(--app-sidebar-text-active);
  border-color: color-mix(in srgb, var(--el-color-primary) 55%, transparent);
}
.app-rail__toggle-icon {
  display: inline-block;
  width: 16px;
  height: 16px;
}
</style>
