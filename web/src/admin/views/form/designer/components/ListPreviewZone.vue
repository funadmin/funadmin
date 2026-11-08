<template>
  <div class="preview-zone" :class="{ 'is-active': active, 'is-inline': inline }" :data-zone="location">
    <span v-if="label" class="preview-zone__label">{{ label }}</span>
    <template v-for="group in groups" :key="group.key">
      <span v-if="group.key === 'more' && group.items.length" class="preview-zone__more">{{ t('common.more', '更多') }}</span>
      <span
        v-for="item in group.items"
        :key="item.button.id"
        class="preview-chip"
        :class="[`is-${item.button.color && item.button.color !== 'default' ? item.button.color : 'plain'}`, { 'is-selected': item.button.id === selectedId, 'is-disabled': item.disabled, 'is-hidden': item.hidden, 'is-more': group.key === 'more' }]"
        :data-preview-button="`${location}:${item.button.id}`"
        :title="item.reason || item.button.tips || item.button.label"
        :draggable="interactive ? 'true' : 'false'"
        role="button"
        :tabindex="interactive ? 0 : -1"
        :aria-pressed="item.button.id === selectedId"
        @click.stop="interactive && emit('select', item.button.id)"
        @keydown.enter.prevent="interactive && emit('select', item.button.id)"
        @dragstart="interactive && emit('dragstart', item.button.id)"
        @dragover.prevent
        @drop.prevent="interactive && emit('drop', item.button.id)"
      ><i v-if="item.button.icon" :class="`i-ep-${item.button.icon.replace(/^i-ep-/, '')}`" />{{ item.button.label }}</span>
    </template>
    <button v-if="interactive && addable" type="button" class="preview-chip is-add" :aria-label="t('formDesigner.buttonsWorkspace.addTo', { label: label ?? '' }, '向{label}添加按钮')" @click.stop="emit('add')">＋</button>
    <slot />
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormListButton, FormListButtonLocation } from '../../schema/types';

export interface ListPreviewItem { button: FormListButton; disabled: boolean; hidden: boolean; reason: string }
/** inline：按钮单行排列不换行（行操作列），由外层表格区域横向滚动。 */
const props = defineProps<{ location: FormListButtonLocation; items: ListPreviewItem[]; label?: string; selectedId?: string; active?: boolean; interactive?: boolean; addable?: boolean; inline?: boolean }>();
const emit = defineEmits<{ select: [id: string]; dragstart: [id: string]; drop: [id: string]; add: [] }>();
const { t } = useI18n();
const groups = computed(() => [
  { key: 'inline', items: props.items.filter(item => item.button.placement !== 'more') },
  { key: 'more', items: props.items.filter(item => item.button.placement === 'more') }
]);
</script>
<style scoped>
.preview-zone { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; min-height: 32px; padding: 6px; border: 1.5px dashed transparent; border-radius: 9px; }
.preview-zone.is-inline { flex-wrap: nowrap; width: max-content; }
.preview-zone:hover, .preview-zone.is-active { border-color: color-mix(in srgb, var(--el-color-primary) 50%, var(--el-bg-color)); background: color-mix(in srgb, var(--el-color-primary) 10%, var(--el-bg-color)); }
.preview-zone__label { position: absolute; top: -9px; left: 8px; padding: 0 4px; border-radius: 4px; font-size: 10px; line-height: 16px; color: var(--el-color-primary); background: var(--el-bg-color); }
.preview-zone__more { font-size: 11px; color: var(--el-text-color-secondary); }
.preview-zone__more::after { content: ' ▾'; }
.preview-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border: 1px solid var(--el-border-color); border-radius: 6px; font-size: 12px; line-height: 18px; white-space: nowrap; color: var(--el-text-color-regular); background: var(--el-bg-color); cursor: pointer; user-select: none; }
.preview-chip[draggable='true'] { cursor: grab; }
.preview-chip.is-primary { color: var(--el-color-primary); border-color: color-mix(in srgb, var(--el-color-primary) 50%, var(--el-bg-color)); background: color-mix(in srgb, var(--el-color-primary) 10%, var(--el-bg-color)); }
.preview-chip.is-success { color: var(--el-color-success); border-color: var(--el-color-success-light-5); background: var(--el-color-success-light-9); }
.preview-chip.is-warning { color: var(--el-color-warning); border-color: var(--el-color-warning-light-5); background: var(--el-color-warning-light-9); }
.preview-chip.is-danger { color: var(--el-color-danger); border-color: var(--el-color-danger-light-5); background: var(--el-color-danger-light-9); }
.preview-chip.is-info { color: var(--el-color-info); border-color: var(--el-color-info-light-5); background: var(--el-color-info-light-9); }
.preview-chip.is-more { border-style: dotted; }
.preview-chip.is-disabled { opacity: 0.5; cursor: not-allowed; }
.preview-chip.is-hidden { opacity: 0.4; text-decoration: line-through; }
.preview-chip.is-selected { outline: 2px solid var(--el-color-primary); outline-offset: 2px; }
.preview-chip.is-add { border-style: dashed; color: var(--el-color-primary); border-color: color-mix(in srgb, var(--el-color-primary) 50%, var(--el-bg-color)); background: transparent; }
.preview-chip:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 2px; }
</style>
