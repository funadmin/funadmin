<template>
  <template v-if="!visibleChildren.length">
    <el-menu-item :index="resolvedPath">
      <i :class="route.meta?.icon || (visibleChildren.length ? 'i-ep-folder' : 'i-ep-document')" class="app-top-icon" />
      <span>{{ menuTitle(route) }}</span>
    </el-menu-item>
  </template>

  <el-sub-menu v-else :index="resolvedPath" :teleported="true">
    <template #title>
      <i :class="route.meta?.icon || (visibleChildren.length ? 'i-ep-folder' : 'i-ep-document')" class="app-top-icon" />
      <span>{{ menuTitle(route) }}</span>
    </template>
    <TopSubItem
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

interface Props {
  route: RouteRecordRaw;
  basePath?: string;
}
const props = withDefaults(defineProps<Props>(), { basePath: '' });

const { menuTitle } = useMenuTitle();

const visibleChildren = computed(() =>
  (props.route.children || []).filter((c) => !c.meta?.hidden)
);

const resolvedPath = computed(() => {
  if (isExternal(props.route.path)) return props.route.path;
  if (props.route.path.startsWith('/')) return props.route.path;
  const base = props.basePath.endsWith('/') ? props.basePath.slice(0, -1) : props.basePath;
  return `${base}/${props.route.path}`;
});

defineOptions({ name: 'TopSubItem' });
</script>

<style scoped>
.app-top-icon {
  display: inline-block;
  width: 16px;
  height: 16px;
  margin-right: 8px;
  vertical-align: -3px;
}
</style>
