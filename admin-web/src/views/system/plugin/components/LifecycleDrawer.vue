<template>
  <PluginHistoryDrawer v-model="visible" :name="name" :redeploy-disabled-reason="redeployDisabledReason" />
</template>

<script setup lang="ts">
import PluginHistoryDrawer from './PluginHistoryDrawer.vue';

const visible = defineModel<boolean>({ default: false });
defineProps<{ name: string; redeployDisabledReason?: string }>();
</script>
