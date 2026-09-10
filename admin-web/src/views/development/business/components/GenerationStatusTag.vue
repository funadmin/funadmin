<template>
  <el-tag :type="tagType" :title="description">{{ label }}</el-tag>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { TagProps } from 'element-plus';
import {
  GENERATION_STATUS_META,
  RECOVERY_STATUS_META,
  normalizeGenerationStatus,
  type BusinessStatusMeta
} from '../constants';

const props = withDefaults(defineProps<{ status?: string | null; kind?: 'generation' | 'recovery' }>(), {
  status: '',
  kind: 'generation'
});
const { t } = useI18n();
const normalized = computed(() => props.kind === 'generation' ? normalizeGenerationStatus(props.status) : (props.status || 'none'));
const meta = computed<BusinessStatusMeta>(() => {
  const source = props.kind === 'generation' ? GENERATION_STATUS_META : RECOVERY_STATUS_META;
  return source[normalized.value as keyof typeof source] || {
    labelKey: '', tone: 'info', descriptionKey: ''
  };
});
const label = computed(() => meta.value.labelKey ? t(meta.value.labelKey) : normalized.value);
const description = computed(() => meta.value.descriptionKey ? t(meta.value.descriptionKey) : normalized.value);
const tagType = computed<TagProps['type']>(() => meta.value.tone === 'primary' ? undefined : meta.value.tone);
</script>
