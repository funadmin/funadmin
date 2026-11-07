<template>
  <el-dialog
    :model-value="modelValue"
    class="codegen-conflict-dialog"
    :title="t('business.codegen.edit.conflictTitle', '核对保存冲突')"
    width="min(1080px, 96vw)"
    top="6vh"
    append-to-body
    :close-on-click-modal="false"
    @update:model-value="(value: boolean) => !value && emit('cancel')"
  >
    <div data-testid="schema-conflict-review">
      <p class="codegen-conflict__notice">{{ t('business.codegen.edit.conflictNotice', '以下为完整只读 JSON。取消将保留本地改动，保存继续暂停；发布设置改动不受影响。') }}</p>
      <p v-if="loading" class="codegen-conflict__notice">{{ t('business.codegen.edit.conflictLoading', '正在读取服务端版本…') }}</p>
      <el-alert v-if="error" type="error" :closable="false" :title="error" />
      <template v-if="review">
        <p class="codegen-conflict__hash">{{ t('business.codegen.edit.conflictHash', '核对的服务端版本 hash：') }}<code>{{ review.serverHash }}</code></p>
        <div class="codegen-conflict__compare">
          <label class="codegen-conflict__card">
            <span>{{ t('business.codegen.edit.conflictLocal', '本地改动') }}</span>
            <textarea readonly rows="14" spellcheck="false" :value="format(review.local)" />
          </label>
          <label class="codegen-conflict__card">
            <span>{{ t('business.codegen.edit.conflictServer', '服务端版本') }}</span>
            <textarea readonly rows="14" spellcheck="false" :value="format(review.serverDocument)" />
          </label>
        </div>
      </template>
    </div>
    <template #footer>
      <el-button :disabled="busy" data-action="conflict-recheck" @click="emit('recheck')">{{ t('business.codegen.edit.conflictRecheck', '重新核对') }}</el-button>
      <el-button :disabled="busy" data-action="conflict-cancel" @click="emit('cancel')">{{ t('business.codegen.edit.conflictCancel', '取消，保留本地') }}</el-button>
      <el-button :disabled="!review || busy" data-action="conflict-use-server" @click="emit('resolve', 'server')">{{ t('business.codegen.edit.conflictUseServer', '放弃本地，采用服务端') }}</el-button>
      <el-button v-if="canOverwrite" type="primary" :disabled="!review || busy" data-action="conflict-use-local" @click="emit('resolve', 'local')">{{ t('business.codegen.edit.conflictUseLocal', '以核对后的本地覆盖') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { ConflictReview } from './useGenerationEditor';

defineProps<{ modelValue: boolean; review: ConflictReview | null; loading: boolean; busy: boolean; error: string; canOverwrite: boolean }>();
const emit = defineEmits<{ cancel: []; recheck: []; resolve: [choice: 'local' | 'server'] }>();

const { t } = useI18n();
const format = (value: unknown) => JSON.stringify(value, null, 2);
</script>

<style scoped>
.codegen-conflict__notice,
.codegen-conflict__hash {
  margin: 0 0 8px;
  color: var(--el-text-color-secondary);
  font-size: 12.5px;
}

.codegen-conflict__compare {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.codegen-conflict__card {
  display: grid;
  gap: 6px;
  color: var(--el-text-color-primary);
  font-size: 13px;
  font-weight: 600;
}

.codegen-conflict__card textarea {
  width: 100%;
  min-height: 280px;
  padding: 8px;
  border: 1px solid var(--el-border-color);
  border-radius: 6px;
  background: var(--el-fill-color-lighter);
  color: var(--el-text-color-regular);
  font: 12px/1.5 ui-monospace, SFMono-Regular, Menlo, monospace;
  resize: vertical;
}

@media (max-width: 760px) {
  .codegen-conflict__compare {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
