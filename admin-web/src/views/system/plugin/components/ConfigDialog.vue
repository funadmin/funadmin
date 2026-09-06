<template>
  <PluginConfigDrawer v-model="visible" :name="name" @saved="emit('saved')" />
</template>

<script setup lang="ts">
import PluginConfigDrawer from './PluginConfigDrawer.vue';

const visible = defineModel<boolean>({ default: false });
defineProps<{ name: string }>();
const emit = defineEmits<{ saved: [] }>();
</script>
