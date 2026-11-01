<template>
  <PluginHistoryDrawer v-model="visible" :code="code" :redeploy-disabled-reason="redeployDisabledReason" />
</template>

<script setup lang="ts">
import PluginHistoryDrawer from './PluginHistoryDrawer.vue';

const visible = defineModel<boolean>({ default: false });
defineProps<{ code: string; redeployDisabledReason?: string }>();
</script>
