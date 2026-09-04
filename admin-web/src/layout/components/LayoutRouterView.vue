<template>
  <!-- 单根容器：配合 .app-layout__main > * 的 flex 占位 -->
  <div class="app-layout-router">
    <router-view v-slot="{ Component, route }">
      <transition :name="transitionName" mode="out-in" appear>
        <keep-alive :include="cachedNames" :max="12">
          <component v-if="Component" :is="Component" :key="route.fullPath" />
        </keep-alive>
      </transition>
    </router-view>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useTabsStore } from '@/store/modules/tabs';
import { useAppStore } from '@/store/modules/app';

const { cachedNames } = storeToRefs(useTabsStore());
const appStore = useAppStore();

const transitionName = computed(() => {
  const t = appStore.pageTransition;
  return t === 'none' ? '' : t;
});
</script>

<style scoped>
.app-layout-router {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
</style>
