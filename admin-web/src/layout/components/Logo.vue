<template>
  <div class="app-logo" :class="{ 'is-collapsed': collapsed }" @click="onClick">
    <div class="app-logo__icon">
      <LogoMark :size="28" />
    </div>
    <!-- 不用 transition+v-show，避免偶发停在 opacity:0 导致「标题空白」 -->
    <span v-show="!collapsed" class="app-logo__text">{{ title }}</span>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router';
import { APP_CONFIG } from '@/config';
import LogoMark from '@/components/LogoMark.vue';

interface Props {
  collapsed?: boolean;
}
defineProps<Props>();

const title = APP_CONFIG.title;
const router = useRouter();

function onClick() {
  router.push('/dashboard');
}
</script>

<style scoped>
.app-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  height: var(--app-header-h);
  padding: 0 12px;
  cursor: pointer;
  user-select: none;
  background: var(--app-sidebar-logo-bg);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  transition: padding 0.25s;
}
.app-logo__icon {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  /* 阴影由 LogoMark 内部承载，避免双重阴影与紫色串色 */
}
.app-logo__text {
  font-size: 17px;
  font-weight: 700;
  letter-spacing: 0.5px;
  white-space: nowrap;
  flex-shrink: 0;
  /* 默认：深色侧栏（menu-theme--dark）用白字 */
  color: #ffffff;
}
.app-logo.is-collapsed .app-logo__text {
  display: none;
}
:global(.menu-theme--light) .app-logo,
:global(.menu-theme--fresh) .app-logo {
  border-bottom: 1px solid var(--app-border);
  background: transparent;
}

/* 水平顶栏：与浅灰顶栏一体，避免侧栏深色 Logo 样式（白字渐变） */
:global(.app-layout__topbar) .app-logo {
  border-bottom: none;
  background: transparent;
}
:global(.app-layout__topbar) .app-logo__text {
  background: none;
  -webkit-text-fill-color: var(--app-text);
  color: var(--app-text);
}
</style>

<style>
/* 无 scoped：带 .app-sidebar 限定，只作用侧栏 Logo，避免 scoped+:global 组合未命中 */
.app-layout.menu-theme--light .app-sidebar .app-logo__text,
.app-layout.menu-theme--fresh .app-sidebar .app-logo__text {
  color: var(--app-text);
  -webkit-text-fill-color: var(--app-text);
}

html.dark .app-layout.menu-theme--light .app-sidebar .app-logo__text,
html.dark .app-layout.menu-theme--fresh .app-sidebar .app-logo__text {
  color: #ffffff;
  -webkit-text-fill-color: #ffffff;
}

/* 双列布局：顶部通栏 Logo（不在 .app-sidebar 内） */
.app-layout.menu-theme--light .app-layout__columns-logo .app-logo__text,
.app-layout.menu-theme--fresh .app-layout__columns-logo .app-logo__text {
  color: var(--app-text);
  -webkit-text-fill-color: var(--app-text);
}
html.dark .app-layout.menu-theme--light .app-layout__columns-logo .app-logo__text,
html.dark .app-layout.menu-theme--fresh .app-layout__columns-logo .app-logo__text {
  color: #ffffff;
  -webkit-text-fill-color: #ffffff;
}
</style>
