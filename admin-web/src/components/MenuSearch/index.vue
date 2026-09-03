<template>
  <el-dialog
    v-model="visible"
    width="520"
    :show-close="false"
    align-center
    class="menu-search-dialog"
    @open="onOpen"
  >
    <div class="menu-search">
      <div class="menu-search__input">
        <i class="i-ep-search text-base text-[var(--el-text-color-secondary)] mr-2" />
        <input
          ref="inputRef"
          v-model="keyword"
          class="menu-search__field"
          placeholder="搜索菜单（标题 / 路径）"
          @keydown.up.prevent="move(-1)"
          @keydown.down.prevent="move(1)"
          @keydown.enter.prevent="enter"
          @keydown.esc="visible = false"
        />
        <span class="menu-search__hint">ESC</span>
      </div>

      <el-scrollbar max-height="320px" class="menu-search__list">
        <div v-if="!filtered.length" class="menu-search__empty">
          <el-empty :image-size="64" description="未找到匹配菜单" />
        </div>
        <div
          v-for="(item, idx) in filtered"
          :key="item.path"
          class="menu-search__item"
          :class="{ 'is-active': idx === active }"
          @click="go(item.path)"
          @mouseenter="active = idx"
        >
          <i :class="item.icon || 'i-ep-document'" class="mr-2 flex-shrink-0" />
          <span class="menu-search__title">{{ displayTitle(item) }}</span>
          <span class="menu-search__path">{{ item.path }}</span>
        </div>
      </el-scrollbar>

      <div class="menu-search__footer">
        <span><kbd>↑</kbd><kbd>↓</kbd> 选择</span>
        <span><kbd>↵</kbd> 跳转</span>
        <span><kbd>ESC</kbd> 关闭</span>
      </div>
    </div>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRouter, type RouteRecordRaw } from 'vue-router';
import { usePermissionStore } from '@/store/modules/permission';
import mitt from '@/utils/mitt';
import { useMenuTitle } from '@/composables/useMenuTitle';

interface FlatMenu {
  path: string;
  title: string;
  name?: string;
  icon?: string;
}

const { menuTitle } = useMenuTitle();

function displayTitle(item: FlatMenu) {
  return menuTitle({ name: item.name, meta: { title: item.title } });
}

const router = useRouter();
const permissionStore = usePermissionStore();

const visible = ref(false);
const keyword = ref('');
const active = ref(0);
const inputRef = ref<HTMLInputElement>();

function flatten(routes: RouteRecordRaw[], parentPath = ''): FlatMenu[] {
  const list: FlatMenu[] = [];
  routes.forEach((r) => {
    if (r.meta?.hidden) return;
    const fullPath = r.path.startsWith('/')
      ? r.path
      : `${parentPath.replace(/\/$/, '')}/${r.path}`;
    const hasVisibleChildren = (r.children || []).some((c) => !c.meta?.hidden);
    if (!hasVisibleChildren && r.meta?.title) {
      list.push({
        path: fullPath,
        title: r.meta.title as string,
        name: r.name as string | undefined,
        icon: r.meta.icon as string
      });
    }
    if (r.children?.length) list.push(...flatten(r.children, fullPath));
  });
  return list;
}

const allMenus = computed<FlatMenu[]>(() => {
  const dashboard: FlatMenu = {
    path: '/dashboard',
    title: '仪表盘',
    name: 'Dashboard',
    icon: 'i-ep-monitor'
  };
  const profile: FlatMenu = {
    path: '/profile',
    title: '个人中心',
    name: 'Profile',
    icon: 'i-ep-user'
  };
  return [dashboard, profile, ...flatten(permissionStore.dynamicRoutes as RouteRecordRaw[])];
});

const filtered = computed(() => {
  const k = keyword.value.trim().toLowerCase();
  if (!k) return allMenus.value;
  return allMenus.value.filter((m) => {
    const label = displayTitle(m).toLowerCase();
    return (
      label.includes(k) ||
      m.path.toLowerCase().includes(k) ||
      m.title.toLowerCase().includes(k)
    );
  });
});

function open() {
  visible.value = true;
  keyword.value = '';
  active.value = 0;
}

function onOpen() {
  nextTick(() => inputRef.value?.focus());
}

function move(step: number) {
  const len = filtered.value.length;
  if (!len) return;
  active.value = (active.value + step + len) % len;
}

function enter() {
  const item = filtered.value[active.value];
  if (item) go(item.path);
}

function go(path: string) {
  visible.value = false;
  router.push(path);
}

function onShortcut(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault();
    open();
  }
}

onMounted(() => {
  mitt.on('open:menu-search', open);
  document.addEventListener('keydown', onShortcut);
});
onUnmounted(() => {
  mitt.off('open:menu-search', open);
  document.removeEventListener('keydown', onShortcut);
});
</script>

<style scoped>
.menu-search {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.menu-search__input {
  display: flex;
  align-items: center;
  height: 44px;
  padding: 0 14px;
  border: 1px solid var(--app-border);
  border-radius: var(--app-radius);
  background: var(--el-fill-color-light);
  transition: border-color 0.18s;
}
.menu-search__input:focus-within {
  border-color: var(--el-color-primary);
  background: var(--app-card-bg);
}
.menu-search__field {
  flex: 1;
  border: 0;
  outline: 0;
  background: transparent;
  font-size: 14px;
  color: var(--app-text);
}
.menu-search__hint {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--app-card-bg);
  color: var(--app-text-secondary);
  border: 1px solid var(--app-border);
}
.menu-search__list {
  margin: 4px -4px;
}
.menu-search__empty {
  padding: 16px 0;
}
.menu-search__item {
  display: flex;
  align-items: center;
  padding: 10px 12px;
  border-radius: var(--app-radius-sm);
  cursor: pointer;
  font-size: 13px;
  color: var(--app-text);
}
.menu-search__item.is-active {
  background: var(--el-color-primary-light-9);
  color: var(--el-color-primary);
  font-weight: 600;
  box-shadow: inset 2px 0 0 var(--el-color-primary);
}
.menu-search__item.is-active .menu-search__path {
  color: var(--el-color-primary);
  opacity: 0.7;
}
.menu-search__title {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.menu-search__path {
  font-size: 11px;
  color: var(--app-text-secondary);
  margin-left: 8px;
}
.menu-search__footer {
  display: flex;
  gap: 14px;
  justify-content: flex-end;
  font-size: 11px;
  color: var(--app-text-secondary);
  padding-top: 4px;
  border-top: 1px solid var(--app-border);
}
.menu-search__footer kbd {
  display: inline-block;
  padding: 1px 5px;
  border-radius: 4px;
  background: var(--app-card-bg);
  border: 1px solid var(--app-border);
  font-family: inherit;
  margin-right: 2px;
}
</style>

<style>
.menu-search-dialog .el-dialog__header {
  padding: 0 !important;
  margin: 0 !important;
}
.menu-search-dialog .el-dialog__body {
  padding: 16px !important;
}
</style>
