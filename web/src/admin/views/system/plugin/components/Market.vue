<template>
  <PluginMarketDrawer v-model="visible" :code="code" @install="emit('install', $event)" />
</template>

<script setup lang="ts">
import PluginMarketDrawer from './PluginMarketDrawer.vue';

const visible = defineModel<boolean>({ default: false });
defineProps<{ code: string }>();
const emit = defineEmits<{ install: [version: string] }>();
</script>
