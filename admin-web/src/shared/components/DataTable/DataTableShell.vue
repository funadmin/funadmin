<template>
  <div
    ref="rootRef"
    class="relative flex w-full flex-col gap-0"
    :class="{
      'box-border overflow-auto bg-[var(--el-bg-color-page)] p-4': isFullscreen
    }"
  >
    <div
      v-if="hasSearch"
      class="mb-3 pb-0 [&_.search-form]:border-0 [&_.search-form]:bg-transparent [&_.search-form]:p-0 [&_.search-form]:shadow-none"
    >
      <slot name="search" />
    </div>

    <div class="mb-3">
      <DataTableToolbar
        v-model:display="display"
        :column-options="cols"
        :column-keys="columnKeys"
        :loading="loading"
        :is-fullscreen="isFullscreen"
        :show-refresh="showRefresh"
        :show-density="showDensity"
        :show-fullscreen="showFullscreen"
        :show-column-setting="showColumnSetting"
        :show-display-setting="showDisplaySetting"
        @update:column-keys="onColumnKeys"
        @refresh="emit('refresh')"
        @toggle-fullscreen="toggleFullscreen"
      >
        <template #left>
          <slot name="toolbar-left" />
        </template>
      </DataTableToolbar>
    </div>

    <div class="min-w-0">
      <slot
        :size="display.size"
        :stripe="display.stripe"
        :border="display.border"
        :header-cell-style="headerCellStyle"
        :column-keys="columnKeys"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useSlots, watch } from 'vue';
import DataTableToolbar from './DataTableToolbar.vue';
import type { DataTableColumnOption, DataTableDisplayState } from './types';
import { defaultDisplayState, loadColumnKeys, loadDisplayState, saveColumnKeys, saveDisplayState } from './tableDisplayStorage';

const props = withDefaults(
  defineProps<{
    storageKey: string;
    loading?: boolean;
    columnOptions?: DataTableColumnOption[];
    showRefresh?: boolean;
    showDensity?: boolean;
    showFullscreen?: boolean;
    showColumnSetting?: boolean;
    showDisplaySetting?: boolean;
  }>(),
  {
    loading: false,
    columnOptions: () => [],
    showRefresh: true,
    showDensity: true,
    showFullscreen: true,
    showColumnSetting: true,
    showDisplaySetting: true
  }
);

const emit = defineEmits<{
  refresh: [];
}>();

const slots = useSlots();
const hasSearch = computed(() => Boolean(slots.search));

const rootRef = ref<HTMLElement | null>(null);
const isFullscreen = ref(false);

const display = ref<DataTableDisplayState>({ ...defaultDisplayState() });
// null 表示默认全选；数组保留完整偏好，不随异步列裁剪。
const preferredColumnKeys = ref<string[] | null>(null);

const cols = computed(() => props.columnOptions ?? []);
const columnKeys = computed(() => {
  const available = cols.value.map((column) => column.key);
  const preferred = preferredColumnKeys.value;
  if (preferred === null) return available;
  const valid = new Set(available);
  return preferred.filter((key) => valid.has(key));
});

const headerCellStyle = computed(() => {
  if (display.value.headerBg) return undefined;
  return {
    background: 'transparent',
    fontWeight: 600
  };
});

function hydrate() {
  const key = props.storageKey;
  const d = loadDisplayState(key);
  if (d) display.value = { ...defaultDisplayState(), ...d };
  const saved = loadColumnKeys(key);
  preferredColumnKeys.value = saved?.length ? [...saved] : null;
}

function onColumnKeys(keys: string[]) {
  const available = new Set(cols.value.map((c) => c.key));
  preferredColumnKeys.value = (preferredColumnKeys.value ?? cols.value.map((c) => c.key))
    .filter((key) => !available.has(key))
    .concat(keys);
  saveColumnKeys(props.storageKey, preferredColumnKeys.value);
}

watch(
  display,
  (v) => {
    saveDisplayState(props.storageKey, v);
  },
  { deep: true }
);

watch(
  () => props.storageKey,
  () => hydrate()
);

function onFsChange() {
  isFullscreen.value = document.fullscreenElement === rootRef.value;
}

function toggleFullscreen() {
  const el = rootRef.value;
  if (!el) return;
  if (!document.fullscreenElement) {
    void el.requestFullscreen();
  } else {
    void document.exitFullscreen();
  }
}

onMounted(() => {
  hydrate();
  document.addEventListener('fullscreenchange', onFsChange);
});

onBeforeUnmount(() => {
  document.removeEventListener('fullscreenchange', onFsChange);
});

defineExpose({
  display,
  columnKeys,
  isFullscreen,
  rootRef
});
</script>
