<template>
  <aside class="app-sidebar" :class="{ 'app-sidebar--submenu-pane': submenuPane }">
    <Logo v-if="showLogoFinal" :collapsed="appStore.sidebarCollapsed" />

    <el-scrollbar class="app-sidebar__scroll">
      <!-- 勿传 background-color：折叠时子菜单浮层会用其计算 hover 底，transparent 会变成灰底 -->
      <el-menu
        :default-active="activeMenu"
        :collapse="menuCollapsed"
        :collapse-transition="false"
        :unique-opened="true"
        :text-color="textColor"
        :active-text-color="activeTextColor"
        router
      >
        <SidebarItem
          v-for="route in menus"
          :key="route.path"
          :route="route"
          :base-path="menuBasePathForRoot"
        />
      </el-menu>
    </el-scrollbar>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, type RouteRecordRaw } from 'vue-router';
import { useAppStore } from '@/store/modules/app';
import { usePermissionStore } from '@/store/modules/permission';
import Logo from './Logo.vue';
import SidebarItem from './SidebarItem.vue';

interface Props {
  /** 自定义菜单数据；不传则使用全部动态路由（vertical 默认行为） */
  routes?: RouteRecordRaw[];
  /**
   * 自定义子菜单列表时的一级路径前缀（如 /system）。
   * mix/columns 只传入当前一级下的 children，子项 path 多为相对段，必须带此前缀才能拼成 /system/user。
   */
  menuBasePath?: string;
  /** 是否显示 Logo（mix/columns 模式由父布局控制） */
  showLogo?: boolean;
  /**
   * 混合/双列布局的次级菜单栏：始终展开显示文案，不受顶栏折叠按钮影响。
   * 避免第二列变成窄图标条 + tooltip（与参考双列布局不一致）。
   */
  submenuPane?: boolean;
}
const props = withDefaults(defineProps<Props>(), {
  routes: undefined,
  menuBasePath: undefined,
  showLogo: undefined,
  submenuPane: false
});

const appStore = useAppStore();
const permissionStore = usePermissionStore();
const route = useRoute();

const dashboardItem: RouteRecordRaw = {
  path: '/dashboard',
  name: 'Dashboard',
  meta: { title: '仪表盘', icon: 'i-ep-monitor' }
} as RouteRecordRaw;

/** 默认菜单：仪表盘 + 全部动态路由 */
const defaultMenus = computed<RouteRecordRaw[]>(() => {
  const list: RouteRecordRaw[] = [dashboardItem];
  permissionStore.dynamicRoutes.forEach((r) => {
    if (r.meta?.hidden) return;
    list.push(r);
  });
  return list;
});

const menus = computed<RouteRecordRaw[]>(() => props.routes ?? defaultMenus.value);

/** 仅在使用自定义 routes（mix/columns 次级栏）时拼接一级前缀；全量树顶层 path 本身多为绝对路径 */
const menuBasePathForRoot = computed(() => {
  if (props.routes?.length && props.menuBasePath) {
    const p = props.menuBasePath;
    return p.endsWith('/') ? p.slice(0, -1) : p;
  }
  return '';
});

const showLogoFinal = computed(() =>
  props.showLogo === undefined ? appStore.showLogo : props.showLogo
);

/** 次级侧栏不参与 el-menu 折叠 */
const menuCollapsed = computed(
  () => !props.submenuPane && appStore.sidebarCollapsed
);

const activeMenu = computed(() => (route.meta?.activeMenu as string) || route.path);

/** el-menu 颜色统一走 CSS 变量，由 menuTheme / preset 决定 */
const textColor = computed(() => 'var(--app-sidebar-text)');
const activeTextColor = computed(() => 'var(--app-sidebar-text-active)');
</script>

<style scoped>
.app-sidebar {
  display: flex;
  flex-direction: column;
  height: 100%;
}

/* 混合/双列：次级菜单列以根级 item 为主，单独略缩字号与行高，避免「字偏大」 */
.app-sidebar--submenu-pane :deep(.el-menu-item),
.app-sidebar--submenu-pane :deep(.el-sub-menu__title) {
  font-size: 13px;
  height: 40px;
  line-height: 40px;
  margin: 2px 8px;
}
.app-sidebar--submenu-pane :deep(.el-menu-item.is-active)::before {
  height: 16px;
}
.app-sidebar--submenu-pane :deep(.el-menu:not(.el-menu--collapse) .el-menu .el-menu-item) {
  font-size: 12.5px;
  height: 36px;
  line-height: 36px;
  margin: 2px 8px;
}
.app-sidebar--submenu-pane :deep(.el-menu:not(.el-menu--collapse) .el-menu .el-menu .el-menu-item) {
  font-size: 12px;
  height: 34px;
  line-height: 34px;
}
.app-sidebar--submenu-pane :deep(.app-menu-icon) {
  width: 16px;
  height: 16px;
  margin-right: 8px;
  vertical-align: -3px;
  font-size: 16px;
}

.app-sidebar__scroll {
  flex: 1;
}

:deep(.el-menu) {
  border-right: 0;
  background: transparent;
  /* 与 active-text-color 一致，保证 EP 内部引用 --el-menu-active-color 的节点也能吃到主色 */
  --el-menu-active-color: var(--app-sidebar-text-active);
}
:deep(.el-menu--collapse) {
  width: var(--app-sidebar-collapsed-w);
}

/* —— Notion / Linear 风格：扁平 + 左竖条 indicator —— */

/* 通用菜单项（一级 / sub-menu 标题）：宽松、圆角、relative 给 ::before 占位 */
:deep(.el-menu-item),
:deep(.el-sub-menu__title) {
  position: relative;
  height: 46px;
  line-height: 46px;
  margin: 3px 8px;
  border-radius: var(--app-radius);
  font-size: 14px;
  font-weight: 500;
  transition:
    background-color 0.18s ease,
    color 0.18s ease;
}
/* 非激活态文字色：排除 .is-active 避免覆盖激活色 */
:deep(.el-menu-item:not(.is-active)),
:deep(.el-sub-menu__title) {
  color: var(--app-sidebar-text) !important;
}
:deep(.el-menu--collapse) .el-menu-item,
:deep(.el-menu--collapse) .el-sub-menu__title {
  margin: 3px 6px;
}

/*
 * 折叠态：去掉为文案预留的图标右边距，并让 tooltip 触发层主轴居中
 *（否则 .app-menu-icon 的 margin-right 仍占位，图标相对窄栏视觉不居中）
 */
:deep(.el-menu--collapse) .app-menu-icon {
  margin-right: 0 !important;
}
:deep(.el-menu--collapse) .el-menu-tooltip__trigger {
  justify-content: center !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}
/* 折叠态 sub-menu：无 tooltip trigger 包裹，直接居中标题行 */
:deep(.el-menu--collapse) .el-sub-menu > .el-sub-menu__title {
  justify-content: center !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}

/* hover */
:deep(.el-menu-item:hover),
:deep(.el-sub-menu__title:hover) {
  background-color: var(--app-sidebar-hover-bg) !important;
}

/*
 * 激活态：参考 saiadmin .menu-item.active 的双层语义
 *  - 浅蓝底（primary-1）：色号比 light-9 略重，让选中态一眼可见但不抢戏
 *  - 主色字 + 600 字重：层级清晰
 *  - 左侧 2px 细色条：克制收尾，不再用 3px 圆角竖条（saiadmin 默认无竖条，此处保留作为 admin-vue 的轻量识别符）
 */
:deep(.el-menu-item.is-active) {
  color: var(--app-sidebar-text-active) !important;
  background-color: var(--app-sidebar-active-bg) !important;
  font-weight: 600;
}
:deep(.el-menu-item.is-active .app-menu-icon),
:deep(.el-menu-item.is-active .truncate) {
  color: var(--app-sidebar-text-active) !important;
}
:deep(.el-menu-item.is-active)::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 2px;
  height: 18px;
  background: var(--app-sidebar-text-active);
  border-radius: 0 2px 2px 0;
}
/* 收起时左竖条没空间，隐藏 */
:deep(.el-menu--collapse) .el-menu-item.is-active::before {
  display: none;
}

/* 父级 sub-menu 展开时：标题保持次级字色，字重与同级菜单一致，仅子项用主色高亮 */
:deep(.el-sub-menu.is-active > .el-sub-menu__title) {
  color: var(--app-sidebar-text) !important;
  background-color: transparent !important;
  font-weight: 500;
}

/* 二级菜单容器：透明、贴边 */
:deep(.el-menu .el-menu) {
  background: transparent !important;
  padding: 0;
  margin: 0;
}
/* 二级菜单项：缩进 + 略小字号 */
:deep(.el-menu:not(.el-menu--collapse) .el-menu .el-menu-item) {
  margin: 2px 8px;
  height: 42px;
  line-height: 42px;
  font-size: 13.5px;
  font-weight: 500;
  padding-left: 42px !important;
}
/* 三级及以下：再增加一级缩进（与 Element 嵌套 .el-menu 层级对齐） */
:deep(.el-menu:not(.el-menu--collapse) .el-menu .el-menu .el-menu-item) {
  padding-left: 56px !important;
}
:deep(.el-menu:not(.el-menu--collapse) .el-menu .el-menu .el-sub-menu__title) {
  padding-left: 52px !important;
}
/* 二级激活竖条与一级共用同一规则，已覆盖；保持视觉一致 */

/* classic 预设的颜色差异已由 CSS 变量 --app-sidebar-text-active / --app-sidebar-active-bg 处理 */
</style>
