<template>
  <!-- 勿传 background-color="transparent"：会参与子菜单浮层 hover 色计算，导致下拉项灰底 -->
  <el-menu
    mode="horizontal"
    class="app-top-menu"
    popper-class="app-top-menu-popper"
    :default-active="activeRoot"
    :ellipsis="false"
    active-text-color="var(--el-color-primary)"
    @select="onSelect"
  >
    <template v-for="item in menus" :key="item.path">
      <el-menu-item v-if="!hasChildren(item) || mode === 'mix'" :index="resolvePath(item)">
        <i v-if="item.meta?.icon" :class="item.meta.icon as string" class="app-top-icon" />
        <span>{{ menuTitle(item) }}</span>
      </el-menu-item>

      <el-sub-menu v-else :index="resolvePath(item)" :teleported="true">
        <template #title>
          <i v-if="item.meta?.icon" :class="item.meta.icon as string" class="app-top-icon" />
          <span>{{ menuTitle(item) }}</span>
        </template>
        <TopSubItem
          v-for="child in visibleChildren(item)"
          :key="child.path"
          :route="child"
          :base-path="resolvePath(item)"
        />
      </el-sub-menu>
    </template>
  </el-menu>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter, type RouteRecordRaw } from 'vue-router';
import { usePermissionStore } from '@/store/modules/permission';
import { getFirstLeafRouteFullPath, getMenuActiveRootPath } from '@/utils/route';
import TopSubItem from './TopSubItem.vue';
import { useMenuTitle } from '@/composables/useMenuTitle';

const { menuTitle } = useMenuTitle();

interface Props {
  /** horizontal: 全菜单展开；mix: 仅一级菜单（点击切换 sidebar 的二级） */
  mode?: 'horizontal' | 'mix';
}
const props = withDefaults(defineProps<Props>(), { mode: 'horizontal' });

const router = useRouter();
const route = useRoute();
const permissionStore = usePermissionStore();

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

function hasChildren(item: RouteRecordRaw): boolean {
  return !!(item.children && item.children.some((c) => !c.meta?.hidden));
}

function visibleChildren(item: RouteRecordRaw): RouteRecordRaw[] {
  return (item.children || []).filter((c) => !c.meta?.hidden);
}

function resolvePath(item: RouteRecordRaw): string {
  return item.path.startsWith('/') ? item.path : `/${item.path}`;
}

/** 当前激活的一级菜单 path（与侧栏菜单项 path 一致） */
const activeRoot = computed(() => getMenuActiveRootPath(route));

function onSelect(index: string) {
  if (props.mode === 'mix') {
    // 如果该一级下有可见的二级，自动跳到第一个
    const root = menus.value.find((m) => resolvePath(m) === index);
    if (root && hasChildren(root)) {
      const first = visibleChildren(root)[0];
      const target = getFirstLeafRouteFullPath(first, index);
      router.push(target);
    } else {
      router.push(index);
    }
  } else {
    router.push(index);
  }
}
</script>

<style scoped>
.app-top-menu {
  border-bottom: 0 !important;
  /* 顶栏为 flex + stretch 时仍用明确高度，避免 height:100% 在部分浏览器下不撑满 */
  height: var(--app-header-h);
  min-height: var(--app-header-h);
  flex: 1;
  min-width: 0;
  align-self: stretch;
  /* 与 EP 横向菜单根高度一致 */
  --el-menu-horizontal-height: var(--app-header-h);
  /* 顶栏本身透明 + 文字色；子菜单浮层 teleported 到 body，不会继承这些变量 */
  --el-menu-bg-color: transparent;
  --el-menu-text-color: var(--app-text);
  background-color: transparent !important;
}
.app-top-menu :deep(.el-menu-item),
.app-top-menu :deep(.el-sub-menu__title) {
  height: var(--app-header-h);
  line-height: var(--app-header-h);
  border-bottom: 2px solid transparent !important;
  font-size: 13.5px;
  font-weight: 500;
}
/* 仅顶层项 hover 浅灰底；下拉浮层在 body 上，使用 :root 的 --el-menu-hover-*（主色浅底） */
.app-top-menu.el-menu--horizontal > :deep(.el-menu-item:hover) {
  background: var(--app-header-item-hover-bg) !important;
  color: var(--el-color-primary) !important;
}
.app-top-menu.el-menu--horizontal > :deep(.el-sub-menu > .el-sub-menu__title:hover) {
  background: var(--app-header-item-hover-bg) !important;
  color: var(--el-color-primary) !important;
}
.app-top-menu :deep(.el-menu-item.is-active),
.app-top-menu :deep(.el-sub-menu.is-active > .el-sub-menu__title) {
  border-bottom-color: var(--el-color-primary) !important;
  color: var(--el-color-primary) !important;
  background: transparent !important;
}
.app-top-icon {
  display: inline-block;
  width: 16px;
  height: 16px;
  margin-right: 6px;
  vertical-align: -3px;
}
</style>
