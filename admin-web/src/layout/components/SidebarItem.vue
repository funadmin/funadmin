<template>
  <template v-if="!visibleChildren.length">
    <el-menu-item :index="resolvedPath" v-bind="$attrs">
      <i v-if="route.meta?.icon" :class="route.meta.icon as string" class="app-menu-icon" />
      <i v-else class="i-ep-document app-menu-icon" />
      <template #title>
        <span class="truncate">{{ menuTitle(route) }}</span>
        <el-tag
          v-if="route.meta?.badge"
          size="small"
          type="danger"
          class="ml-auto"
          effect="dark"
        >
          {{ route.meta.badge }}
        </el-tag>
      </template>
    </el-menu-item>
  </template>

  <el-sub-menu v-else :index="resolvedPath" v-bind="$attrs">
    <template #title>
      <i v-if="route.meta?.icon" :class="route.meta.icon as string" class="app-menu-icon" />
      <i v-else class="i-ep-folder app-menu-icon" />
      <span class="truncate">{{ menuTitle(route) }}</span>
    </template>
    <SidebarItem
      v-for="child in visibleChildren"
      :key="child.path"
      :route="child"
      :base-path="resolvedPath"
    />
  </el-sub-menu>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { RouteRecordRaw } from 'vue-router';
import { isExternal } from '@/utils/is';
import { useMenuTitle } from '@/composables/useMenuTitle';

const { menuTitle } = useMenuTitle();

interface Props {
  route: RouteRecordRaw;
  basePath?: string;
}
const props = withDefaults(defineProps<Props>(), { basePath: '' });

const visibleChildren = computed(() => {
  const list = (props.route.children || []).filter((c) => !c.meta?.hidden);
  return list;
});

const resolvedPath = computed(() => {
  if (isExternal(props.route.path)) return props.route.path;
  if (props.route.path.startsWith('/')) return props.route.path;
  const base = props.basePath.endsWith('/') ? props.basePath.slice(0, -1) : props.basePath;
  return `${base}/${props.route.path}`;
});

defineOptions({ name: 'SidebarItem' });
</script>

<style scoped>
.app-menu-icon {
  display: inline-block;
  width: 18px;
  height: 18px;
  margin-right: 10px;
  vertical-align: -4px;
  flex-shrink: 0;
  /* 图标色跟随父级文字色，保证与菜单文字一致 */
  color: inherit;
}
.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
