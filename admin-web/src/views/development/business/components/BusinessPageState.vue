<template>
  <section class="business-page-state" :aria-busy="loading ? 'true' : 'false'">
    <div class="sr-only" aria-live="polite">{{ announcement }}</div>
    <el-skeleton v-if="loading" :rows="5" animated />
    <el-result v-else-if="error" icon="error" :title="errorTitle" role="alert">
      <template #extra>
        <p class="mb-3">{{ errorMessage }}</p>
        <el-button v-if="onRetry" type="primary" @click="onRetry">{{ t('business.retry') }}</el-button>
      </template>
    </el-result>
    <el-empty v-else-if="empty" :description="emptyText || t('business.empty')" />
    <slot v-else />
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
  loading?: boolean;
  error?: string | Error | null;
  empty?: boolean;
  emptyText?: string;
  onRetry?: () => void;
}>(), {
  loading: false,
  error: null,
  empty: false,
  emptyText: '',
  onRetry: undefined
});

const { t } = useI18n();
const errorTitle = computed(() => t('business.loadError'));
const errorMessage = computed(() => props.error instanceof Error ? props.error.message : (props.error || errorTitle.value));
const announcement = computed(() => {
  if (props.loading) return t('business.loading');
  if (props.error) return `${errorTitle.value}: ${errorMessage.value}`;
  if (props.empty) return props.emptyText || t('business.empty');
  return '';
});
</script>
