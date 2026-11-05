<template>
  <el-config-provider :locale="locale">
    <router-view />
  </el-config-provider>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ElConfigProvider } from 'element-plus';
import zhCn from 'element-plus/es/locale/lang/zh-cn';
import en from 'element-plus/es/locale/lang/en';
import { useAppStore } from '@/store/modules/app';

const appStore = useAppStore();
const route = useRoute();

const locale = computed(() => (appStore.locale === 'en-US' ? en : zhCn));

/**
 * 响应式断点
 * < 1024px → 小屏（含平板）：侧栏变浮窗抽屉
 * < 640px  → 手机：额外自动切换布局到 vertical
 */
function handleResize() {
  const w = window.innerWidth;
  const isSmall = w < 1024;
  const isPhone = w < 640;
  appStore.setIsMobile(isSmall, isPhone);
}

onMounted(() => {
  appStore.applyTheme();
  handleResize();
  window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
});

watch(() => appStore.themeMode, () => appStore.applyTheme());

/** 路由跳转后自动关闭移动端菜单抽屉 */
watch(() => route.fullPath, () => {
  if (appStore.isMobile) appStore.closeMobileDrawer();
});
</script>
