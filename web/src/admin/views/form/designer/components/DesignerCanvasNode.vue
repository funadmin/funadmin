<template>
  <div
    class="designer-node"
    :class="{ 'is-selected': selected, 'is-container': container }"
    role="treeitem"
    tabindex="0"
    :aria-level="depth"
    :aria-selected="selected"
    :aria-expanded="container ? !collapsed : undefined"
    :data-node-id="node.id"
    :data-node-type="node.type"
    @click.stop="store.selectNode(node.id)"
    @keydown="onKeydown"
  >
    <template v-if="selected">
      <span class="designer-node-badge" data-node-badge>{{ badge }}</span>
      <span class="designer-node-ops" data-node-ops @click.stop @keydown.stop>
        <button type="button" data-node-op="up" :disabled="index === 0" :title="t('formDesigner.moveUp', '上移')" :aria-label="t('formDesigner.moveUp', '上移')" @click="store.moveNodeByKeyboard(node.id, 'up')"><i class="i-ep-top" aria-hidden="true" /></button>
        <button type="button" data-node-op="down" :disabled="index >= count - 1" :title="t('formDesigner.moveDown', '下移')" :aria-label="t('formDesigner.moveDown', '下移')" @click="store.moveNodeByKeyboard(node.id, 'down')"><i class="i-ep-bottom" aria-hidden="true" /></button>
        <button type="button" data-node-op="duplicate" @click="store.duplicateNode(node.id)">{{ t('formDesigner.duplicate', '复制') }}</button>
        <button type="button" data-node-op="remove" class="is-danger" @click="store.removeNode(node.id)">{{ t('common.remove', '删除') }}</button>
      </span>
    </template>

    <template v-if="container">
      <div class="designer-group-title">
        <button
          type="button"
          class="collapse-toggle"
          data-collapse-toggle
          :aria-label="collapsed ? t('formDesigner.expandNode', '展开节点') : t('formDesigner.collapseNode', '折叠节点')"
          @click.stop="collapsed = !collapsed"
        >{{ collapsed ? '▸' : '▾' }}</button>
        <span class="min-w-0 flex-1 truncate">{{ node.title }}</span>
        <span class="designer-group-type">{{ controlMeta(node.type).label }}</span>
      </div>
      <div v-if="collapsed" class="collapsed-summary">{{ t('formDesigner.collapsedSummary', { n: descendantCount }, '已折叠 {n} 个后代节点，仍可拖入此容器') }}</div>
      <DesignerCanvas
        :nodes="collapsed ? EMPTY_NODES : node.children"
        :store="store"
        :parent-id="node.id"
        :depth="depth + 1"
        :root="false"
      />
    </template>

    <div v-else class="designer-field-preview" @click.stop="store.selectNode(node.id)">
      <span v-if="node.field && node.type !== 'hidden'" class="designer-field-label" :class="{ 'is-required': isRequiredNode(node) }">{{ node.title }}</span>
      <FormControlRenderer
        v-if="node.field"
        class="designer-field-control"
        :node="node"
        :model-value="node.defaultValue"
        :options="previewOptions"
        disabled
        preview
      />
      <span v-else class="designer-field-placeholder">{{ node.title }} · {{ controlMeta(node.type).label }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormSchemaNode } from '@/api/form';
import { controlMeta } from '../../registry';
import { isRequiredNode } from '../../schema/fieldNodes';
import type { DesignerKeyboardMove, DesignerStore } from '../../composables/useDesigner';
import FormControlRenderer from '../../components/FormControlRenderer.vue';
import DesignerCanvas from './DesignerCanvas.vue';

defineOptions({ name: 'DesignerCanvasNode' });

const { t } = useI18n();

const EMPTY_NODES: FormSchemaNode[] = [];

const props = withDefaults(defineProps<{
  node: FormSchemaNode;
  store: DesignerStore;
  depth: number;
  /** 在同级中的位置，用于浮动操作「上移/下移」的可用状态。 */
  index?: number;
  count?: number;
}>(), { index: 0, count: 1 });

const CONTAINER_TYPES = new Set(['group', 'grid', 'tabs', 'collapse', 'repeatable', 'subform']);
const COLLAPSE_THRESHOLD = 8;
const container = computed(() => CONTAINER_TYPES.has(props.node.type));
const selected = computed(() => props.store.selectedNodeId.value === props.node.id);
const badge = computed(() => [props.node.field, controlMeta(props.node.type).label].filter(Boolean).join(' · '));
const descendantCount = computed(() => {
  const count = (nodes: FormSchemaNode[]): number => nodes.reduce((total, child) => total + 1 + count(child.children), 0);
  return count(props.node.children);
});
const collapsed = ref(container.value && descendantCount.value > COLLAPSE_THRESHOLD);
const previewOptions = computed(() => {
  const options = props.node.dataSource?.options;
  return Array.isArray(options) ? options as Array<{ label: string; value: string | number }> : [];
});

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Enter') {
    event.preventDefault();
    props.store.addNode('input', container.value ? props.node.id : null);
    return;
  }
  if (event.key === 'Delete') {
    event.preventDefault();
    props.store.removeNode(props.node.id);
    return;
  }
  if (!event.altKey) return;
  const directions: Partial<Record<string, DesignerKeyboardMove>> = {
    ArrowUp: 'up', ArrowDown: 'down', ArrowRight: 'indent', ArrowLeft: 'outdent'
  };
  const direction = directions[event.key];
  if (!direction) return;
  event.preventDefault();
  props.store.moveNodeByKeyboard(props.node.id, direction);
};
</script>

<style scoped>
.designer-node {
  /* 布局容器的强调色，与左栏布局控件一致。 */
  --designer-layout-accent: #8b5cf6;
  position: relative;
  border: 1px dashed transparent;
  border-radius: 10px;
  padding: 8px 10px;
  background: transparent;
  cursor: pointer;
}
/* 夜间模式：浅色下的紫色在深色背景上偏暗，换用更亮的紫色（scoped 下编译为 html.dark .designer-node[data-v-…]）。 */
html.dark .designer-node {
  --designer-layout-accent: #a78bfa;
}
.designer-node:hover {
  border-color: var(--el-border-color);
}
.designer-node.is-container {
  border: 1px solid var(--el-border-color-lighter);
  padding: 10px 12px 6px;
}
.designer-node:focus-visible,
.designer-node.is-selected {
  border: 1.5px solid var(--el-color-primary);
  background: color-mix(in srgb, var(--el-color-primary) 3%, var(--el-bg-color));
  outline: none;
}
.designer-node-badge {
  position: absolute;
  top: -9px;
  left: 10px;
  z-index: 2;
  max-width: calc(100% - 200px);
  overflow: hidden;
  padding: 0 6px;
  border-radius: 5px;
  background: var(--el-color-primary);
  color: var(--el-color-white);
  font-size: 10px;
  line-height: 16px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.designer-node-ops {
  position: absolute;
  top: -11px;
  right: 8px;
  z-index: 2;
  display: flex;
  gap: 2px;
}
.designer-node-ops button {
  display: inline-flex;
  align-items: center;
  height: 20px;
  padding: 0 5px;
  border: 1px solid var(--el-border-color);
  border-radius: 6px;
  background: var(--el-bg-color);
  color: var(--el-text-color-regular);
  cursor: pointer;
  font-size: 11px;
}
.designer-node-ops button:hover:not(:disabled) {
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
}
.designer-node-ops button.is-danger {
  color: var(--el-color-danger);
}
.designer-node-ops button.is-danger:hover:not(:disabled) {
  border-color: var(--el-color-danger);
  color: var(--el-color-danger);
}
.designer-node-ops button:disabled {
  color: var(--el-text-color-disabled);
  cursor: not-allowed;
}
.designer-group-title {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--el-text-color-regular);
  font-size: 12px;
  font-weight: 600;
}
.designer-group-title::before {
  content: "";
  flex: none;
  width: 3px;
  height: 12px;
  border-radius: 2px;
  background: var(--designer-layout-accent);
}
.designer-group-type {
  color: var(--el-text-color-secondary);
  font-size: 11px;
  font-weight: 400;
}
.collapse-toggle {
  order: -1;
  padding: 0 2px;
  border: 0;
  background: transparent;
  color: var(--el-text-color-secondary);
  cursor: pointer;
}
.designer-field-preview {
  display: grid;
  grid-template-columns: 96px minmax(0, 1fr);
  align-items: center;
  gap: 10px;
  font-size: 13px;
}
.designer-field-label {
  overflow: hidden;
  color: var(--el-text-color-regular);
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.designer-field-label.is-required::before {
  content: "* ";
  color: var(--el-color-danger);
}
.designer-field-control {
  min-width: 0;
  pointer-events: none;
}
.designer-field-placeholder {
  grid-column: 1 / -1;
  color: var(--el-text-color-placeholder);
  font-size: 12px;
}
.collapsed-summary {
  color: var(--el-text-color-placeholder);
  font-size: 12px;
  padding: 8px;
}
@media (max-width: 759px) {
  .designer-field-preview {
    grid-template-columns: minmax(0, 1fr);
    gap: 4px;
  }
  .designer-field-label {
    text-align: left;
  }
}
</style>
