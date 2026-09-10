<template>
  <!-- 单根容器：配合 .app-layout__main > * 的 flex 占位 -->
  <div class="app-layout-router">
    <router-view v-slot="{ Component, route }">
      <keep-alive :max="12">
        <component v-if="Component" :is="Component" :key="route.fullPath" />
      </keep-alive>
    </router-view>
  </div>
</template>


<style scoped>
.app-layout-router {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
</style>
