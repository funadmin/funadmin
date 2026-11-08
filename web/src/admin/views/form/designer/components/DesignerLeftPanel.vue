<template>
  <div class="designer-left-panel">
    <div class="left-tabs" role="tablist" :aria-label="t('formDesigner.layout.leftTabsAria', '控件与大纲')">
      <button
        type="button"
        role="tab"
        class="left-tab"
        :class="{ 'is-active': tab === 'controls' }"
        data-left-tab="controls"
        :aria-selected="tab === 'controls'"
        @click="tab = 'controls'"
      >{{ t('formDesigner.layout.tabControls', '控件') }}</button>
      <button
        type="button"
        role="tab"
        class="left-tab"
        :class="{ 'is-active': tab === 'outline' }"
        data-left-tab="outline"
        :aria-selected="tab === 'outline'"
        @click="tab = 'outline'"
      >{{ t('formDesigner.layout.tabOutline', '大纲') }} <small data-outline-count class="left-tab-count">{{ store.flattenedNodes.value.length }}</small></button>
    </div>

    <div v-show="tab === 'controls'" class="left-pane" role="tabpanel">
      <el-input
        v-model="controlQuery"
        data-testid="palette-search"
        class="left-search"
        size="small"
        clearable
        :placeholder="t('formDesigner.layout.searchControls', '搜索控件')"
      >
        <template #prefix><i class="i-ep-search" aria-hidden="true" /></template>
      </el-input>
      <div ref="paletteRef" class="palette-list">
        <section v-for="group in paletteGroups" :key="group.name" class="palette-group">
          <h3 class="palette-group-title"><span class="palette-group-name">{{ group.name }}</span><span class="palette-group-count">{{ group.controls.length }}</span></h3>
          <div class="palette-group-grid">
            <div
              v-for="control in group.controls"
              :key="control.type"
              class="palette-item"
              :class="{ 'is-layout': control.kind === 'layout' }"
              :data-type="control.type"
              :title="control.label"
              role="button"
              tabindex="0"
              :aria-label="t('formDesigner.addControlAria', { label: control.label }, '添加{label}')"
              @click="append(control.type)"
              @keydown.enter.prevent="append(control.type)"
            >
              <i :class="controlIcon(control)" aria-hidden="true" />
              <span>{{ control.label }}</span>
            </div>
          </div>
        </section>
        <p v-if="!paletteGroups.length" class="left-empty">{{ t('formDesigner.layout.noControlMatch', '没有匹配的控件') }}</p>
      </div>
    </div>

    <div v-show="tab === 'outline'" class="left-pane" role="tabpanel">
      <el-input
        v-model="outlineQuery"
        data-testid="outline-search"
        class="left-search"
        size="small"
        clearable
        :placeholder="t('formDesigner.layout.searchFields', '搜索字段')"
      >
        <template #prefix><i class="i-ep-search" aria-hidden="true" /></template>
      </el-input>
      <div class="outline-list">
        <SchemaNodeTree :nodes="store.nodes.value" :store="store" :filter="outlineQuery" />
        <button type="button" class="outline-add" data-action="add-layout-group" @click="store.addNode('group')">
          <i class="i-ep-plus" aria-hidden="true" /> {{ t('formDesigner.addLayoutGroup', '添加布局分组') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Sortable from 'sortablejs';
import type { ControlMeta } from '../../registry';
import type { DesignerStore } from '../../composables/useDesigner';
import { controlIcon, paletteContainers } from '../controlPalette';
import SchemaNodeTree from './SchemaNodeTree.vue';

defineOptions({ name: 'DesignerLeftPanel' });

const { t } = useI18n();
const props = defineProps<{ store: DesignerStore; controls: ControlMeta[] }>();
const emit = defineEmits<{ added: [] }>();

// 页签与搜索词只是视图状态，不进入 Schema、历史和自动保存。
const tab = ref<'controls' | 'outline'>('controls');
const controlQuery = ref('');
const outlineQuery = ref('');

const paletteGroups = computed(() => {
  const query = controlQuery.value.trim().toLowerCase();
  const visible = query
    ? props.controls.filter((control) => control.label.toLowerCase().includes(query) || control.type.toLowerCase().includes(query))
    : props.controls;
  return [...new Set(visible.map((control) => control.group))]
    .map((name) => ({ name, controls: visible.filter((control) => control.group === name) }));
});

/** 点击/回车追加：有选中节点时插到它之后（同一容器内），否则追加到根末尾。 */
const append = (type: string) => {
  const selectedId = props.store.selectedNodeId.value;
  const entry = selectedId ? props.store.flattenedNodes.value.find((item) => item.node.id === selectedId) : undefined;
  if (entry) {
    const siblings = entry.parentId ? props.store.findNode(entry.parentId)?.children ?? [] : props.store.nodes.value;
    props.store.addNode(type, entry.parentId, siblings.indexOf(entry.node) + 1);
  } else {
    props.store.addNode(type);
  }
  emit('added');
};

const paletteRef = ref<HTMLElement>();
let paletteSortables: Sortable[] = [];
const initializePalette = () => {
  paletteSortables.forEach((sortable) => sortable.destroy());
  paletteSortables = [];
  if (!paletteRef.value) return;
  paletteSortables = paletteContainers(paletteRef.value).map((container) => Sortable.create(container, {
    group: { name: 'form-designer', pull: 'clone', put: false },
    draggable: '.palette-item',
    sort: false,
    animation: 150
  }));
};
onMounted(initializePalette);
// 搜索会增删分组网格，重建拖拽源以覆盖新容器。
watch(() => paletteGroups.value.map((group) => `${group.name}:${group.controls.length}`).join('|'), () => nextTick(initializePalette));
onBeforeUnmount(() => paletteSortables.forEach((sortable) => sortable.destroy()));
</script>

<style scoped>
.designer-left-panel {
  /* 布局类控件的强调色，与样机一致；沿用插件页的紫色配方。 */
  --designer-layout-accent: #8b5cf6;
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}
/* 夜间模式：浅色下的紫色在深色背景上偏暗，换用更亮的紫色（scoped 下编译为 html.dark .designer-left-panel[data-v-…]）。 */
html.dark .designer-left-panel {
  --designer-layout-accent: #a78bfa;
}
.left-tabs {
  display: flex;
  gap: 2px;
  padding: 8px 10px 0;
  border-bottom: 1px solid var(--el-border-color-lighter);
}
.left-tab {
  flex: 1;
  padding: 6px 0;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: var(--el-text-color-regular);
  cursor: pointer;
  font-size: 13px;
}
.left-tab.is-active {
  border-bottom-color: var(--el-color-primary);
  color: var(--el-color-primary);
  font-weight: 600;
}
.left-tab:focus-visible {
  outline: 2px solid var(--el-color-primary);
  outline-offset: -2px;
}
.left-tab-count {
  color: var(--el-text-color-secondary);
  font-weight: 400;
}
.left-pane {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 0;
}
.left-search {
  flex-shrink: 0;
  padding: 10px 10px 8px;
}
.palette-list,
.outline-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 0 10px 12px;
  scrollbar-width: thin;
}
.palette-group + .palette-group {
  margin-top: 10px;
}
.palette-group-title {
  display: flex;
  justify-content: space-between;
  margin: 0 0 4px;
  color: var(--el-text-color-secondary);
  font-size: 11px;
  font-weight: 600;
}
.palette-group-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px;
}
.palette-item {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  padding: 5px 8px;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 7px;
  background: var(--el-bg-color);
  color: var(--el-text-color-primary);
  cursor: grab;
  font-size: 12px;
}
.palette-item > i {
  display: grid;
  flex: none;
  place-items: center;
  width: 18px;
  height: 18px;
  border-radius: 5px;
  background: color-mix(in srgb, var(--el-color-primary) 10%, var(--el-bg-color));
  color: var(--el-color-primary);
  font-size: 12px;
}
.palette-item.is-layout > i {
  background: color-mix(in srgb, var(--designer-layout-accent) 14%, var(--el-bg-color));
  color: var(--designer-layout-accent);
}
.palette-item > span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.palette-item:hover,
.palette-item:focus-visible {
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
  outline: none;
}
.left-empty {
  margin: 24px 0;
  color: var(--el-text-color-placeholder);
  font-size: 12px;
  text-align: center;
}
.outline-add {
  display: flex;
  align-items: center;
  gap: 4px;
  width: 100%;
  margin-top: 8px;
  padding: 4px 6px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--el-color-primary);
  cursor: pointer;
  font-size: 12px;
}
.outline-add:hover,
.outline-add:focus-visible {
  background: color-mix(in srgb, var(--el-color-primary) 10%, var(--el-bg-color));
  outline: none;
}
</style>
