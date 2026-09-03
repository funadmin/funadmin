<template>
  <header class="app-header">
    <div class="app-header__left">
      <!-- 移动端：汉堡按钮开关抽屉 -->
      <button
        v-if="appStore.isMobile"
        class="app-btn-icon"
        @click="appStore.mobileDrawerOpen ? appStore.closeMobileDrawer() : appStore.openMobileDrawer()"
      >
        <i :class="appStore.mobileDrawerOpen ? 'i-ep-close' : 'i-ep-expand'" />
      </button>
      <!-- PC：折叠侧栏 -->
      <button v-else class="app-btn-icon" @click="appStore.toggleSidebar()">
        <i :class="appStore.sidebarCollapsed ? 'i-ep-expand' : 'i-ep-fold'" />
      </button>
      <Breadcrumb v-if="appStore.showBreadcrumb && !appStore.isMobile" />
    </div>

    <HeaderActions />
  </header>
</template>

<script setup lang="ts">
import { useAppStore } from '@/store/modules/app';
import Breadcrumb from './Breadcrumb.vue';
import HeaderActions from './HeaderActions.vue';

const appStore = useAppStore();
</script>

<style scoped>
.app-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 18px;
  height: 100%;
}
.app-header__left {
  display: flex;
  align-items: center;
  gap: 0;
  min-width: 0;
  flex: 1;
}
/* 侧栏开关与路径区分层：竖线 + 内边距，避免贴在一起显挤 */
.app-header__left :deep(.app-breadcrumb) {
  margin-left: 10px;
  padding-left: 14px;
  border-left: 1px solid var(--app-divider);
  min-width: 0;
  flex: 1;
  display: flex;
  align-items: center;
}
.app-btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: var(--app-radius);
  border: 0;
  background: transparent;
  color: var(--app-text-secondary);
  cursor: pointer;
  font-size: 17px;
  transition:
    background 0.18s ease,
    color 0.18s ease;
}
.app-btn-icon:hover {
  background: color-mix(in srgb, var(--el-color-primary) 8%, var(--app-card-bg));
  color: var(--el-color-primary);
}
</style>
