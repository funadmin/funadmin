<template>
  <el-drawer :model-value="modelValue" :title="`${t('business.summary')} #${generation?.id ?? ''}`" size="72%" @update:model-value="emit('update:modelValue', $event)">
    <template v-if="generation">
      <section data-section="summary">
        <h3>{{ t('business.summary') }}</h3>
        <p>#{{ generation.id }} · {{ generation.generationMode || '-' }}</p>
        <CopyValue v-if="generation.routePath" :value="generation.routePath" />
      </section>

      <section data-section="status">
        <h3>{{ t('business.statusTitle') }}</h3>
        <GenerationStatusTag :status="generation.status" />
        <GenerationStatusTag :status="generation.recoveryStatus" kind="recovery" />
      </section>

      <section data-section="hashes">
        <h3>{{ t('business.hashes') }}</h3>
        <CopyValue v-for="value in hashes" :key="value" :value="value" />
      </section>

      <section data-section="files">
        <h3>{{ t('business.files') }}</h3>
        <GenerationPlanView v-if="generation.plan" :plan="generation.plan" />
        <p v-else>{{ t('business.noFiles') }}</p>
      </section>

      <section data-section="recovery">
        <h3>{{ t('business.recovery') }}</h3>
        <SafeObject :value="generation.recovery" />
        <el-button v-if="canRecover" data-action="recover" type="warning" :loading="recovering" @click="recover">
          {{ t('business.recover') }}
        </el-button>
      </section>

      <section data-section="result">
        <h3>{{ t('business.result') }}</h3>
        <SafeObject :value="generation.result" />
      </section>

      <section data-section="safe-error" role="status">
        <h3>{{ t('business.safeError') }}</h3>
        <SafeObject :value="generation.error" />
      </section>
    </template>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BusinessGeneration } from '@/api/development/business';
import { businessDevelopmentApi } from '@/api/development/business';
import GenerationPlanView from './GenerationPlanView.vue';
import GenerationStatusTag from './GenerationStatusTag.vue';

const props = defineProps<{ modelValue: boolean; generation: BusinessGeneration | null }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; recovered: [result: unknown] }>();
const { t } = useI18n();
const recovering = ref(false);
const hashes = computed(() => [props.generation?.planDigest, props.generation?.definitionHash, props.generation?.schemaHash].filter((value): value is string => Boolean(value)));
const canRecover = computed(() => props.generation?.availableActions?.includes('recover') === true);

const CopyValue = defineComponent({
  props: { value: { type: String, required: true } },
  setup(copyProps) {
    const copy = () => navigator.clipboard?.writeText(copyProps.value);
    return () => h('code', {
      tabindex: 0,
      role: 'button',
      title: t('business.copy'),
      'data-copy': copyProps.value,
      onClick: copy,
      onKeydown: (event: KeyboardEvent) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          void copy();
        }
      }
    }, copyProps.value);
  }
});

const SafeObject = defineComponent({
  props: { value: { type: Object as PropType<object | null>, default: null } },
  setup(safeProps) {
    return () => safeProps.value
      ? h('dl', Object.entries(safeProps.value).filter(([, value]) => typeof value !== 'object').map(([key, value]) => h('div', [h('dt', key), h('dd', String(value ?? ''))])))
      : h('span', '-');
  }
});

async function recover() {
  if (!props.generation || !canRecover.value) return;
  recovering.value = true;
  try {
    const result = await businessDevelopmentApi.recoverGeneration(props.generation.id, props.generation.recoveryStatus || 'recovery_required');
    emit('recovered', result);
  } finally {
    recovering.value = false;
  }
}
</script>
