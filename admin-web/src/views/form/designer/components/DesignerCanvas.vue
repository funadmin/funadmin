<template>
  <div
    ref="dropZoneRef"
    class="designer-drop-zone"
    :class="{ 'designer-root-zone': root }"
    :role="root ? 'tree' : 'group'"
    :aria-label="root ? '表单设计画布' : undefined"
    :data-drop-zone="parentId ?? 'root'"
    :data-parent-id="parentId ?? ''"
  >
    <DesignerCanvasNode
      v-for="node in nodes"
      :key="node.id"
      :node="node"
      :store="store"
      :depth="depth"
    />
    <div v-if="!nodes.length" class="designer-drop-placeholder">{{ root ? '从左侧拖入控件开始设计' : '拖入控件到此容器' }}</div>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Sortable from 'sortablejs';
import type { FormSchemaNode } from '@/api/form';
import type { DesignerStore } from '../../composables/useDesigner';
import DesignerCanvasNode from './DesignerCanvasNode.vue';

defineOptions({ name: 'DesignerCanvas' });

const props = withDefaults(defineProps<{
  nodes: FormSchemaNode[];
  store: DesignerStore;
  parentId?: string | null;
  depth?: number;
  root?: boolean;
}>(), { parentId: null, depth: 1, root: true });

const dropZoneRef = ref<HTMLElement>();
let sortable: Sortable | null = null;

const containsNode = (node: FormSchemaNode, nodeId: string): boolean =>
  node.id === nodeId || node.children.some((child) => containsNode(child, nodeId));

const destinationId = (element: HTMLElement): string | null => element.dataset.parentId || null;
const moveAllowed = (nodeId: string, parentId: string | null): boolean => {
  const moving = props.store.findNode(nodeId);
  return Boolean(moving && nodeId !== parentId && (!parentId || !containsNode(moving, parentId)));
};

const initializeSortable = () => {
  sortable?.destroy();
  if (!dropZoneRef.value) return;
  sortable = Sortable.create(dropZoneRef.value, {
    group: { name: 'form-designer', pull: true, put: true },
    draggable: '.designer-node',
    animation: 150,
    fallbackOnBody: true,
    swapThreshold: 0.65,
    onMove: (event) => {
      const nodeId = (event.dragged as HTMLElement).dataset.nodeId;
      return !nodeId || moveAllowed(nodeId, destinationId(event.to as HTMLElement));
    },
    onAdd: (event) => {
      const item = event.item as HTMLElement;
      const type = item.dataset.type;
      if (!type) return;
      item.remove();
      props.store.addNode(type, destinationId(event.to as HTMLElement), event.newIndex);
    },
    onEnd: (event) => {
      const item = event.item as HTMLElement;
      const nodeId = item.dataset.nodeId;
      if (!nodeId) return;
      const parentId = destinationId(event.to as HTMLElement);
      if (!moveAllowed(nodeId, parentId) || !props.store.moveNode(nodeId, parentId, event.newIndex ?? 0)) {
        nextTick(initializeSortable);
      }
    }
  });
};

onMounted(initializeSortable);
watch(() => props.nodes, () => nextTick(initializeSortable), { deep: false });
onBeforeUnmount(() => sortable?.destroy());
</script>

<style scoped>
.designer-drop-zone {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-height: 54px;
  padding: 8px;
}
.designer-root-zone {
  min-height: max(520px, calc(100vh - 260px));
}
.designer-drop-placeholder {
  align-items: center;
  border: 1px dashed var(--el-border-color);
  border-radius: 4px;
  color: var(--el-text-color-placeholder);
  display: flex;
  font-size: 12px;
  justify-content: center;
  min-height: 38px;
}
</style>
