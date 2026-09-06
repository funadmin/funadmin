<template>
  <PluginConfigDrawer v-model="visible" :code="code" @saved="emit('saved')" />
</template>

<script setup lang="ts">
import PluginConfigDrawer from './PluginConfigDrawer.vue';

const visible = defineModel<boolean>({ default: false });
defineProps<{ code: string }>();
const emit = defineEmits<{ saved: [] }>();
</script>
