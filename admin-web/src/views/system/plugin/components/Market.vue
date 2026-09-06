<template>
  <PluginMarketDrawer v-model="visible" :name="name" @install="emit('install', $event)" />
</template>

<script setup lang="ts">
import PluginMarketDrawer from './PluginMarketDrawer.vue';

const visible = defineModel<boolean>({ default: false });
defineProps<{ name: string }>();
const emit = defineEmits<{ install: [version: string] }>();
</script>
