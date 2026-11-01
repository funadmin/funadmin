<template>
  <div class="flex min-h-8 items-center justify-between gap-2">
    <div class="flex min-w-0 flex-1 items-center gap-2">
      <slot name="left" />
    </div>
    <div class="flex shrink-0 items-center gap-1">
      <el-tooltip v-if="showRefresh" :content="t('layout.refresh')" placement="top">
        <button
          type="button"
          :class="TOOLBAR_BTN"
          :disabled="loading"
          @click="$emit('refresh')"
        >
          <i class="i-ep-refresh" :class="{ 'animate-spin': loading }" />
        </button>
      </el-tooltip>

      <el-dropdown v-if="showDensity" trigger="click" @command="onDensity">
        <span class="inline-flex">
          <el-tooltip :content="t('table.density')" placement="top">
            <button type="button" :class="TOOLBAR_BTN">
              <i class="i-ep-histogram" />
            </button>
          </el-tooltip>
        </span>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="small" :class="{ 'is-active': display.size === 'small' }">
              {{ t('table.densitySmall') }}
            </el-dropdown-item>
            <el-dropdown-item command="default" :class="{ 'is-active': display.size === 'default' }">
              {{ t('table.densityDefault') }}
            </el-dropdown-item>
            <el-dropdown-item command="large" :class="{ 'is-active': display.size === 'large' }">
              {{ t('table.densityLarge') }}
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>

      <el-tooltip
        v-if="showFullscreen"
        :content="isFullscreen ? t('layout.exitFullscreen') : t('layout.fullscreen')"
        placement="top"
      >
        <button type="button" :class="TOOLBAR_BTN" @click="$emit('toggle-fullscreen')">
          <i :class="isFullscreen ? 'i-ep-close' : 'i-ep-full-screen'" />
        </button>
      </el-tooltip>

      <el-popover
        v-if="showColumnSetting && cols.length"
        placement="bottom-end"
        :width="280"
        trigger="click"
      >
        <template #reference>
          <div class="inline-flex">
            <el-tooltip :content="t('table.columnSetting')" placement="top">
              <button type="button" :class="TOOLBAR_BTN">
                <i class="i-ep-grid" />
              </button>
            </el-tooltip>
          </div>
        </template>
        <div class="max-h-[360px] overflow-auto py-1">
          <div
            v-for="col in cols"
            :key="col.key"
            class="flex items-center gap-2.5 rounded-app-sm px-2.5 py-2 hover:bg-[var(--el-fill-color-light)]"
          >
            <el-checkbox
              :model-value="columnKeys.includes(col.key)"
              :disabled="col.alwaysVisible"
              @change="(v: boolean | string | number) => onToggleColumn(col.key, v === true)"
            />
            <span class="flex-1 text-[13px] text-[var(--el-text-color-primary)]">{{ col.label }}</span>
          </div>
          <p class="mx-2.5 mt-2 mb-0 text-xs leading-snug text-[var(--el-text-color-secondary)]">
            {{ t('table.columnOrderTip') }}
          </p>
        </div>
      </el-popover>
      <el-tooltip
        v-else-if="showColumnSetting"
        :content="t('table.columnSettingDisabledTip')"
        placement="top"
      >
        <span class="inline-flex cursor-not-allowed">
          <button type="button" :class="TOOLBAR_BTN" disabled>
            <i class="i-ep-grid" />
          </button>
        </span>
      </el-tooltip>

      <el-popover v-if="showDisplaySetting" placement="bottom-end" :width="280" trigger="click">
        <template #reference>
          <div class="inline-flex">
            <el-tooltip :content="t('table.displaySetting')" placement="top">
              <button type="button" :class="TOOLBAR_BTN">
                <i class="i-ep-setting" />
              </button>
            </el-tooltip>
          </div>
        </template>
        <div class="flex flex-col gap-2.5 py-1">
          <el-checkbox v-model="stripeLocal" @change="emitDisplay">{{ t('table.stripe') }}</el-checkbox>
          <el-checkbox v-model="borderLocal" @change="emitDisplay">{{ t('table.border') }}</el-checkbox>
          <el-checkbox v-model="headerBgLocal" @change="emitDisplay">{{ t('table.headerBg') }}</el-checkbox>
        </div>
      </el-popover>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { DataTableColumnOption, DataTableDisplayState } from './types';

const { t } = useI18n();

const TOOLBAR_BTN =
  'inline-flex size-8 cursor-pointer items-center justify-center rounded-app-sm border-0 bg-[var(--el-fill-color-light)] p-0 text-[var(--el-text-color-regular)] transition-[background,color] duration-150 hover:not-disabled:bg-[var(--el-color-primary-light-9)] hover:not-disabled:text-[var(--el-color-primary)] disabled:cursor-not-allowed disabled:opacity-55 [&_i]:text-base [&_i]:text-inherit [&_i]:transition-colors hover:not-disabled:[&_i]:text-[var(--el-color-primary)]';

const props = withDefaults(
  defineProps<{
    display: DataTableDisplayState;
    loading?: boolean;
    isFullscreen?: boolean;
    columnOptions?: DataTableColumnOption[];
    columnKeys: string[];
    showRefresh?: boolean;
    showDensity?: boolean;
    showFullscreen?: boolean;
    showColumnSetting?: boolean;
    showDisplaySetting?: boolean;
  }>(),
  {
    loading: false,
    isFullscreen: false,
    columnOptions: () => [],
    columnKeys: () => [],
    showRefresh: true,
    showDensity: true,
    showFullscreen: true,
    showColumnSetting: true,
    showDisplaySetting: true
  }
);

const emit = defineEmits<{
  'update:display': [DataTableDisplayState];
  'update:columnKeys': [string[]];
  refresh: [];
  'toggle-fullscreen': [];
}>();

const cols = computed(() => props.columnOptions ?? []);

const stripeLocal = ref(props.display.stripe);
const borderLocal = ref(props.display.border);
const headerBgLocal = ref(props.display.headerBg);

watch(
  () => props.display,
  (d) => {
    stripeLocal.value = d.stripe;
    borderLocal.value = d.border;
    headerBgLocal.value = d.headerBg;
  },
  { deep: true }
);

function emitDisplay() {
  emit('update:display', {
    ...props.display,
    stripe: stripeLocal.value,
    border: borderLocal.value,
    headerBg: headerBgLocal.value
  });
}

function onDensity(cmd: string) {
  const size = cmd as DataTableDisplayState['size'];
  if (size !== 'small' && size !== 'default' && size !== 'large') return;
  emit('update:display', { ...props.display, size });
}

function onToggleColumn(key: string, checked: boolean) {
  const opt = cols.value.find((c) => c.key === key);
  if (opt?.alwaysVisible && !checked) return;
  const set = new Set(props.columnKeys);
  if (checked) set.add(key);
  else set.delete(key);
  const ordered = cols.value.map((c) => c.key).filter((k) => set.has(k));
  emit('update:columnKeys', ordered);
}
</script>

<style>
.el-dropdown-menu__item.is-active {
  color: var(--el-color-primary);
  font-weight: 600;
}
</style>
