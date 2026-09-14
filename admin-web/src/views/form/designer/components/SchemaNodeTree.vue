<template>
  <div class="schema-node-tree">
    <div
      v-for="(node, index) in nodes"
      :key="node.id"
      class="schema-tree-node"
      :style="{ marginLeft: depth ? '14px' : '0' }"
    >
      <div
        class="schema-tree-row"
        :class="store.selectedNodeId.value === node.id ? 'is-selected' : ''"
        @click="store.selectNode(node.id)"
      >
        <span class="schema-tree-label">{{ node.title }}<template v-if="node.field">（{{ node.field }}）</template> · {{ typeLabel(node.type) }}</span>
        <el-button link size="small" title="上移" :disabled="index === 0" @click.stop="store.moveNode(node.id, parentId, index - 1)">↑</el-button>
        <el-button link size="small" title="下移" :disabled="index === nodes.length - 1" @click.stop="store.moveNode(node.id, parentId, index + 2)">↓</el-button>
        <el-button v-if="parentId" link size="small" title="移至根层" @click.stop="store.moveNode(node.id, null, store.nodes.value.length)">根</el-button>
        <el-button link size="small" @click.stop="store.duplicateNode(node.id)">复制</el-button>
      </div>
      <div v-if="isContainer(node.type)" class="schema-tree-actions">
        <el-dropdown trigger="click" @command="(type: string) => store.addNode(type, node.id)">
          <el-button link size="small">容器内添加</el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="input">输入框</el-dropdown-item>
              <el-dropdown-item command="select">下拉选择</el-dropdown-item>
              <el-dropdown-item command="group">分组容器</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
      <SchemaNodeTree
        v-if="node.children.length"
        :nodes="node.children"
        :store="store"
        :parent-id="node.id"
        :depth="depth + 1"
      />
    </div>
    <el-empty v-if="depth === 0 && !nodes.length" description="暂无表单节点" :image-size="48" />
  </div>
</template>

<script setup lang="ts">
import type { FormSchemaNode } from '@/api/form';
import type { DesignerStore } from '../../composables/useDesigner';
import { controlMeta } from '../../registry';

defineOptions({ name: 'SchemaNodeTree' });

withDefaults(defineProps<{
  nodes: FormSchemaNode[];
  store: DesignerStore;
  parentId?: string | null;
  depth?: number;
}>(), { parentId: null, depth: 0 });

const typeLabel = (type: string) => {
  const meta = controlMeta(type);
  return meta.type === type ? meta.label : '未知控件';
};
const isContainer = (type: string) => ['group', 'grid', 'collapse', 'tabs', 'repeatable', 'subform'].includes(type);
</script>

<style scoped>
.schema-tree-label {
  flex: 1 1 160px;
  min-width: 0;
  overflow-wrap: anywhere;
}
.schema-tree-row {
  align-items: center;
  flex-wrap: wrap;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  gap: 2px;
  margin-top: 4px;
  min-height: 32px;
  padding: 2px 6px;
}
.schema-tree-row.is-selected {
  background: var(--el-color-primary-light-9);
  border-color: var(--el-color-primary);
}
.schema-tree-actions {
  padding-left: 8px;
}
</style>
