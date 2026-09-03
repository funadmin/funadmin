<template>
  <el-breadcrumb separator="/" class="app-breadcrumb">
    <el-breadcrumb-item v-for="(item, idx) in items" :key="item.path">
      <span v-if="idx === items.length - 1" class="app-breadcrumb__current">
        <i v-if="idx === 0" class="i-ep-house mr-1" />
        {{ item.title }}
      </span>
      <a
        v-else
        class="app-breadcrumb__link cursor-pointer"
        @click="onClick(item.path)"
      >
        <i v-if="idx === 0" class="i-ep-house mr-1" />
        {{ item.title }}
      </a>
    </el-breadcrumb-item>
  </el-breadcrumb>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMenuTitle } from '@/composables/useMenuTitle';

interface Crumb {
  path: string;
  title: string;
}

const route = useRoute();
const router = useRouter();
const { menuTitle } = useMenuTitle();

const items = computed<Crumb[]>(() => {
  const matched = route.matched.filter(
    (r) => r.meta?.title && r.meta?.breadcrumb !== false
  );
  if (!matched.length || matched[0].path !== '/dashboard') {
    matched.unshift({
      path: '/dashboard',
      name: 'Home',
      meta: { title: '首页' }
    } as any);
  }
  return matched.map((r) => ({
    path: r.path === '' ? '/' : r.path,
    title: menuTitle(r)
  }));
});

function onClick(path: string) {
  if (path === route.path) return;
  router.push(path);
}
</script>

<style scoped>
.app-breadcrumb {
  font-size: 13px;
  line-height: 1.45;
  --app-crumb-icon-size: 15px;
}
.app-breadcrumb :deep(.el-breadcrumb__separator) {
  margin: 0 6px;
  font-weight: 400;
  color: var(--app-text-secondary);
  opacity: 0.55;
}
.app-breadcrumb :deep(.el-breadcrumb__item) {
  display: inline-flex;
  align-items: center;
}
.app-breadcrumb :deep(.el-breadcrumb__inner) {
  display: inline-flex;
  align-items: center;
}
.app-breadcrumb__current {
  color: var(--app-text);
  font-weight: 600;
  letter-spacing: 0.01em;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.app-breadcrumb__current .mr-1 {
  font-size: var(--app-crumb-icon-size);
  opacity: 0.9;
}
.app-breadcrumb__link {
  color: var(--app-text-secondary);
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: color 0.18s ease;
}
.app-breadcrumb__link .mr-1 {
  font-size: var(--app-crumb-icon-size);
}
.app-breadcrumb__link:hover {
  color: var(--el-color-primary);
}
</style>
