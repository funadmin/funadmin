<template>
  <div class="flex flex-col gap-3">
    <el-alert
      :title="parsed.ok ? 'JSON 解析成功，可应用到设计器' : parsed.error"
      :type="parsed.ok ? 'success' : 'error'"
      :closable="false"
      show-icon
    />
    <el-input v-model="raw" type="textarea" :rows="22" spellcheck="false" />
    <div class="flex justify-end">
      <el-button type="primary" :disabled="!parsed.ok" @click="apply">应用到设计器</el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { FormSchemaDocument } from '@/api/form';
import { parseSchemaJson } from '../schemaEditor';

const props = defineProps<{ schema: FormSchemaDocument }>();
const emit = defineEmits<{ apply: [schema: FormSchemaDocument] }>();
const raw = ref(JSON.stringify(props.schema, null, 2));
const parsed = computed(() => parseSchemaJson(raw.value));

watch(() => props.schema, (schema) => {
  raw.value = JSON.stringify(schema, null, 2);
}, { deep: true });

const apply = () => {
  if (parsed.value.schema) emit('apply', parsed.value.schema);
};
</script>
