<template>
  <PluginAccountDrawer v-model="visible" @changed="emit('changed')" />
</template>

<script setup lang="ts">
import PluginAccountDrawer from './PluginAccountDrawer.vue';

const visible = defineModel<boolean>({ default: false });
const emit = defineEmits<{ changed: [] }>();
</script>
