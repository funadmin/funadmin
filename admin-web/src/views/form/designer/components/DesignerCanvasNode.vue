<template>
  <div
    class="designer-node rounded border"
    :class="store.selectedNodeId.value === node.id ? 'is-selected' : ''"
    role="treeitem"
    tabindex="0"
    :aria-level="depth"
    :aria-selected="store.selectedNodeId.value === node.id"
    :aria-expanded="container ? !collapsed : undefined"
    :data-node-id="node.id"
    :data-node-type="node.type"
    @click.stop="store.selectNode(node.id)"
    @keydown="onKeydown"
  >
    <div class="designer-node-header">
      <button
        v-if="container"
        type="button"
        class="collapse-toggle"
        data-collapse-toggle
        :aria-label="collapsed ? '展开节点' : '折叠节点'"
        @click.stop="collapsed = !collapsed"
      >{{ collapsed ? '▸' : '▾' }}</button>
      <span class="drag-handle" aria-hidden="true">⋮⋮</span>
      <span class="min-w-0 flex-1 truncate">{{ node.title }} · {{ controlMeta(node.type).label }} · {{ node.id }}</span>
      <el-button link size="small" @click.stop="store.duplicateNode(node.id)">复制</el-button>
      <el-button link size="small" type="danger" @click.stop="store.removeNode(node.id)">删除</el-button>
    </div>

    <div v-if="!container" class="designer-field-preview" @click.stop>
      <span v-if="field && node.type !== 'hidden'" class="designer-field-label">{{ field.label }}</span>
      <FormControlRenderer
        v-if="field"
        class="min-w-0 flex-1"
        :field="field"
        :model-value="field.default_value"
        :options="previewOptions"
        disabled
        preview
      />
      <span v-else class="text-xs text-[var(--el-text-color-placeholder)]">{{ node.title }}</span>
    </div>

    <template v-else>
      <div v-if="collapsed" class="collapsed-summary">已折叠 {{ descendantCount }} 个后代节点，仍可拖入此容器</div>
      <DesignerCanvas
        :nodes="collapsed ? [] : node.children"
        :store="store"
        :parent-id="node.id"
        :depth="depth + 1"
        :root="false"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import type { FormSchemaNode } from '@/api/form';
import { controlMeta } from '../../registry';
import type { DesignerKeyboardMove, DesignerStore } from '../../composables/useDesigner';
import FormControlRenderer from '../../components/FormControlRenderer.vue';
import DesignerCanvas from './DesignerCanvas.vue';

defineOptions({ name: 'DesignerCanvasNode' });

const props = defineProps<{
  node: FormSchemaNode;
  store: DesignerStore;
  depth: number;
}>();

const CONTAINER_TYPES = new Set(['group', 'grid', 'tabs', 'collapse', 'repeatable', 'subform']);
const COLLAPSE_THRESHOLD = 8;
const container = computed(() => CONTAINER_TYPES.has(props.node.type));
const descendantCount = computed(() => {
  const count = (nodes: FormSchemaNode[]): number => nodes.reduce((total, child) => total + 1 + count(child.children), 0);
  return count(props.node.children);
});
const collapsed = ref(container.value && descendantCount.value > COLLAPSE_THRESHOLD);
const field = computed(() => props.node.field
  ? props.store.fields.value.find((item) => item.field_name === props.node.field) ?? null
  : null);
const previewOptions = computed(() => {
  const options = field.value?.options_source?.options;
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
  background: var(--el-bg-color);
  border-color: var(--el-border-color);
  padding: 8px;
}
.designer-node:focus-visible,
.designer-node.is-selected {
  border-color: var(--el-color-primary);
  box-shadow: 0 0 0 1px var(--el-color-primary) inset;
  outline: none;
}
.designer-node-header {
  align-items: center;
  color: var(--el-text-color-secondary);
  display: flex;
  font-size: 12px;
  gap: 6px;
  min-height: 26px;
}
.drag-handle { cursor: grab; }
.collapse-toggle {
  background: transparent;
  border: 0;
  cursor: pointer;
  padding: 0 2px;
}
.designer-field-preview {
  align-items: center;
  display: flex;
  gap: 8px;
  padding-top: 6px;
}
.designer-field-label {
  flex: 0 0 110px;
  font-size: 14px;
  text-align: right;
}
.collapsed-summary {
  color: var(--el-text-color-placeholder);
  font-size: 12px;
  padding: 8px;
}
</style>
