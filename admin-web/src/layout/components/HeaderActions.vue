<template>
  <div class="app-header__right">
    <button class="app-btn-icon" :title="t('layout.menuSearch')" @click="onSearch">
      <i class="i-ep-search" />
    </button>
    <button class="app-btn-icon" :title="t('layout.refresh')" @click="onRefresh">
      <i class="i-ep-refresh-right" />
    </button>
    <button class="app-btn-icon" :title="isFullscreen ? t('layout.exitFullscreen') : t('layout.fullscreen')" @click="toggleFullscreen">
      <i :class="isFullscreen ? 'i-ep-aim' : 'i-ep-full-screen'" />
    </button>

    <Notification />

    <button class="app-btn-icon" :title="t('layout.toggleTheme')" @click="toggleTheme">
      <i :class="appStore.themeMode === 'dark' ? 'i-ep-sunny' : 'i-ep-partly-cloudy'" />
    </button>

    <el-dropdown trigger="click" @command="onLocale">
      <button class="app-btn-icon" :title="t('layout.language')">
        <i class="i-ep-map-location" />
      </button>
      <template #dropdown>
        <el-dropdown-menu>
          <el-dropdown-item command="zh-CN" :disabled="appStore.locale === 'zh-CN'">
            <span class="mr-2">🇨🇳</span>{{ t('layout.langZh') }}
          </el-dropdown-item>
          <el-dropdown-item command="en-US" :disabled="appStore.locale === 'en-US'">
            <span class="mr-2">🇬🇧</span>{{ t('layout.langEn') }}
          </el-dropdown-item>
        </el-dropdown-menu>
      </template>
    </el-dropdown>

    <button class="app-btn-icon" :title="t('layout.layoutSetting')" @click="openSetting">
      <i class="i-ep-setting" />
    </button>

    <el-divider direction="vertical" class="!h-5 !mx-2" />

    <el-dropdown trigger="click" @command="onUserCmd">
      <div class="app-header__user">
        <el-avatar :size="30" :src="userStore.avatar" class="app-header__avatar">
          {{ userStore.nickname.charAt(0).toUpperCase() }}
        </el-avatar>
        <div class="app-header__user-info">
          <span class="app-header__user-name">{{ userStore.nickname || t('layout.notLoggedIn') }}</span>
          <span class="app-header__user-role">{{ userStore.roles[0] || t('layout.guest') }}</span>
        </div>
        <i class="i-ep-arrow-down app-header__user-arrow" />
      </div>
      <template #dropdown>
        <el-dropdown-menu>
          <div class="px-4 py-2 text-xs text-[var(--el-text-color-secondary)]">
            {{ userStore.userInfo?.email || t('layout.welcomeBack') }}
          </div>
          <el-dropdown-item command="profile">
            <i class="i-ep-user mr-2" /> {{ t('layout.profile') }}
          </el-dropdown-item>
          <el-dropdown-item command="setting">
            <i class="i-ep-setting mr-2" /> {{ t('layout.layoutSettings') }}
          </el-dropdown-item>
          <el-dropdown-item divided command="logout">
            <i class="i-ep-switch-button mr-2" /> {{ t('layout.logout') }}
          </el-dropdown-item>
        </el-dropdown-menu>
      </template>
    </el-dropdown>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessageBox, ElMessage } from 'element-plus';
import mitt from '@/utils/mitt';
import { useAppStore, type LocaleType } from '@/store/modules/app';
import { useUserStore } from '@/store/modules/user';
import { usePermissionStore } from '@/store/modules/permission';
import { useTabsStore } from '@/store/modules/tabs';
import Notification from '@/components/Notification/index.vue';

const { t } = useI18n();
const appStore = useAppStore();
const userStore = useUserStore();
const permissionStore = usePermissionStore();
const tabsStore = useTabsStore();
const router = useRouter();

const isFullscreen = ref(false);

function onRefresh() {
  const fullPath = router.currentRoute.value.fullPath;
  router.replace(`/redirect${fullPath}`);
}

function onSearch() {
  mitt.emit('open:menu-search');
}

function openSetting() {
  mitt.emit('open:setting');
}

function toggleFullscreen() {
  if (document.fullscreenElement) {
    document.exitFullscreen();
  } else {
    document.documentElement.requestFullscreen();
  }
}

function syncFullscreen() {
  isFullscreen.value = Boolean(document.fullscreenElement);
}

onMounted(() => document.addEventListener('fullscreenchange', syncFullscreen));
onUnmounted(() => document.removeEventListener('fullscreenchange', syncFullscreen));

function toggleTheme() {
  appStore.setTheme(appStore.themeMode === 'dark' ? 'light' : 'dark');
}

function onLocale(lang: string) {
  appStore.setLocale(lang as LocaleType);
  ElMessage.success(lang === 'zh-CN' ? t('layout.localeSwitchedZh') : t('layout.localeSwitchedEn'));
}

async function onUserCmd(cmd: string) {
  if (cmd === 'profile') {
    router.push('/profile');
    return;
  }
  if (cmd === 'setting') {
    openSetting();
    return;
  }
  if (cmd === 'logout') {
    await ElMessageBox.confirm(t('layout.logoutConfirm'), t('layout.tip'), { type: 'warning' });
    await userStore.logout();
    permissionStore.reset();
    tabsStore.reset();
    router.replace('/login');
  }
}
</script>

<style scoped>
.app-header__right {
  display: flex;
  align-items: center;
  gap: 4px;
  height: 100%;
}
.app-btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: var(--app-radius-sm);
  border: 0;
  background: transparent;
  color: var(--app-text-secondary);
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s;
}
.app-btn-icon:hover {
  background: var(--el-fill-color-light);
  color: var(--el-color-primary);
}
.app-header__user {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px 4px 4px;
  border-radius: var(--app-radius-sm);
  cursor: pointer;
  transition: background 0.2s;
}
.app-header__user:hover {
  background: var(--el-fill-color-light);
}
.app-header__avatar {
  background: var(--el-color-primary);
  color: #fff;
  font-weight: 600;
  flex-shrink: 0;
}
.app-header__user-info {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}
.app-header__user-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-text);
}
.app-header__user-role {
  font-size: 11px;
  color: var(--app-text-secondary);
}
.app-header__user-arrow {
  font-size: 12px;
  color: var(--app-text-secondary);
}

@media (max-width: 768px) {
  .app-header__user-info {
    display: none;
  }
}
</style>
