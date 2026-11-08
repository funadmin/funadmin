<template>
  <div ref="listRef" class="schema-node-tree" :class="{ 'is-nested': depth > 0 }" :data-parent-id="parentId ?? ''">
    <div
      v-for="node in visibleNodes"
      :key="node.id"
      class="schema-tree-node"
      :data-node-id="node.id"
    >
      <div
        class="schema-tree-row"
        :class="store.selectedNodeId.value === node.id ? 'is-selected' : ''"
        @click="store.selectNode(node.id)"
      >
        <span class="schema-tree-label">
          <i v-if="isContainer(node.type)" :class="controlIcon(node)" class="schema-tree-icon" aria-hidden="true" />
          <span class="schema-tree-title">{{ node.title }}</span>
          <code v-if="node.field" class="schema-tree-field">{{ node.field }}</code>
          <span v-else class="schema-tree-type">{{ typeLabel(node.type) }}</span>
        </span>
        <span class="schema-tree-ops">
          <el-button link size="small" :title="t('formDesigner.moveUp', '上移')" :disabled="indexOf(node) === 0" @click.stop="store.moveNode(node.id, parentId, indexOf(node) - 1)"><i class="i-ep-top" /></el-button>
          <el-button link size="small" :title="t('formDesigner.moveDown', '下移')" :disabled="indexOf(node) === nodes.length - 1" @click.stop="store.moveNode(node.id, parentId, indexOf(node) + 2)"><i class="i-ep-bottom" /></el-button>
          <el-button v-if="parentId" link size="small" :title="t('formDesigner.moveToRoot', '移至根层')" @click.stop="store.moveNode(node.id, null, store.nodes.value.length)">{{ t('formDesigner.root', '根') }}</el-button>
          <el-button link size="small" :title="t('formDesigner.duplicate', '复制')" @click.stop="store.duplicateNode(node.id)"><i class="i-ep-copy-document" /></el-button>
          <el-dropdown v-if="isContainer(node.type)" trigger="click" @command="(type: string) => store.addNode(type, node.id)">
            <el-button link size="small" :title="t('formDesigner.addInContainer', '容器内添加')" @click.stop><i class="i-ep-plus" /></el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="input">{{ t('formDesigner.addInput', '输入框') }}</el-dropdown-item>
                <el-dropdown-item command="select">{{ t('formDesigner.addSelect', '下拉选择') }}</el-dropdown-item>
                <el-dropdown-item command="group">{{ t('formDesigner.addGroup', '分组容器') }}</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </span>
      </div>
      <div v-if="isContainer(node.type) || node.children.length" class="schema-tree-children">
        <SchemaNodeTree
          :nodes="node.children"
          :store="store"
          :parent-id="node.id"
          :depth="depth + 1"
          :filter="filter"
        />
      </div>
    </div>
    <el-empty v-if="depth === 0 && !visibleNodes.length" :description="filter ? t('formDesigner.layout.noOutlineMatch', '没有匹配的字段') : t('formDesigner.noNodes', '暂无表单节点')" :image-size="48" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Sortable from 'sortablejs';
import type { FormSchemaNode } from '@/api/form';
import type { DesignerStore } from '../../composables/useDesigner';
import { controlMeta } from '../../registry';
import { controlIcon } from '../controlPalette';

defineOptions({ name: 'SchemaNodeTree' });

const { t } = useI18n();

const props = withDefaults(defineProps<{
  nodes: FormSchemaNode[];
  store: DesignerStore;
  parentId?: string | null;
  depth?: number;
  /** 按标题/字段名过滤；过滤期间保留匹配节点的祖先，并禁用拖拽（可见序号与真实序号不一致）。 */
  filter?: string;
}>(), { parentId: null, depth: 0, filter: '' });

const typeLabel = (type: string) => {
  const meta = controlMeta(type);
  return meta.type === type ? meta.label : t('formDesigner.unknownControl', '未知控件');
};
const isContainer = (type: string) => ['group', 'grid', 'collapse', 'tabs', 'repeatable', 'subform'].includes(type);
const query = computed(() => props.filter.trim().toLowerCase());
const matches = (node: FormSchemaNode): boolean =>
  [node.title, node.field ?? ''].some((text) => String(text).toLowerCase().includes(query.value))
  || node.children.some(matches);
const visibleNodes = computed(() => query.value ? props.nodes.filter(matches) : props.nodes);
const indexOf = (node: FormSchemaNode) => props.nodes.indexOf(node);

// 拖拽排序/改层级：只读取 Sortable 的目标位置并还原 DOM，结构变更一律交给 store.moveNode。
const listRef = ref<HTMLElement>();
let sortable: Sortable | null = null;
const containsNode = (node: FormSchemaNode, nodeId: string): boolean =>
  node.id === nodeId || node.children.some((child) => containsNode(child, nodeId));
const moveAllowed = (nodeId: string, parentId: string | null) => {
  const moving = props.store.findNode(nodeId);
  return Boolean(moving && (!parentId || !containsNode(moving, parentId)));
};
onMounted(() => {
  if (!listRef.value) return;
  sortable = Sortable.create(listRef.value, {
    group: { name: 'form-designer-outline', pull: true, put: true },
    draggable: '.schema-tree-node',
    animation: 150,
    fallbackOnBody: true,
    swapThreshold: 0.65,
    disabled: Boolean(query.value),
    onMove: (event) => {
      const nodeId = (event.dragged as HTMLElement).dataset.nodeId;
      return Boolean(nodeId && moveAllowed(nodeId, (event.to as HTMLElement).dataset.parentId || null));
    },
    onEnd: (event) => {
      const item = event.item as HTMLElement;
      const from = event.from as HTMLElement;
      const to = event.to as HTMLElement;
      item.remove();
      from.insertBefore(item, from.children[event.oldIndex ?? 0] ?? null);
      const nodeId = item.dataset.nodeId;
      const oldIndex = event.oldDraggableIndex ?? event.oldIndex ?? 0;
      const newIndex = event.newDraggableIndex ?? event.newIndex ?? 0;
      if (!nodeId || (from === to && oldIndex === newIndex)) return;
      const parentId = to.dataset.parentId || null;
      if (!moveAllowed(nodeId, parentId)) return;
      // moveNode 的序号指向原数组中的插入位置；同层下移时 Sortable 给出的是最终位置，需 +1。
      props.store.moveNode(nodeId, parentId, from === to && newIndex > oldIndex ? newIndex + 1 : newIndex);
    }
  });
});
watch(query, (value) => sortable?.option('disabled', Boolean(value)));
onBeforeUnmount(() => sortable?.destroy());
</script>

<style scoped>
.schema-node-tree.is-nested {
  min-height: 6px;
}
.schema-tree-row {
  align-items: center;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  font-size: 12px;
  gap: 6px;
  margin-top: 2px;
  min-height: 28px;
  padding: 2px 6px;
  transition: background-color 0.15s ease;
}
.schema-tree-row:hover {
  background: var(--el-fill-color-light);
}
.schema-tree-row.is-selected {
  background: var(--el-color-primary-light-9);
}
.schema-tree-row.is-selected .schema-tree-title {
  color: var(--el-color-primary);
  font-weight: 600;
}
.schema-tree-label {
  align-items: center;
  display: flex;
  flex: 1 1 auto;
  gap: 6px;
  min-width: 0;
}
.schema-tree-icon {
  color: var(--el-color-primary);
  flex-shrink: 0;
}
.schema-tree-title {
  color: var(--el-text-color-regular);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.schema-tree-field,
.schema-tree-type {
  color: var(--el-text-color-secondary);
  flex-shrink: 0;
  font-size: 11px;
  margin-left: auto;
  max-width: 45%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.schema-tree-field {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
/* 操作只在悬停/选中时占位，避免压窄 248px 左栏里的标题。 */
.schema-tree-ops {
  align-items: center;
  display: none;
  flex-shrink: 0;
  gap: 0;
}
.schema-tree-ops :deep(.el-button + .el-button),
.schema-tree-ops :deep(.el-dropdown) {
  margin-left: 2px;
}
.schema-tree-row:hover .schema-tree-ops,
.schema-tree-row:focus-within .schema-tree-ops,
.schema-tree-row.is-selected .schema-tree-ops {
  display: inline-flex;
}
.schema-tree-row:hover .schema-tree-field,
.schema-tree-row:hover .schema-tree-type,
.schema-tree-row.is-selected .schema-tree-field,
.schema-tree-row.is-selected .schema-tree-type {
  display: none;
}
.schema-tree-children {
  border-left: 1px dashed var(--el-border-color);
  margin-left: 10px;
  padding-left: 8px;
}
</style>
