<template>
  <div class="schema-node-tree">
    <div
      v-for="(node, index) in nodes"
      :key="node.id"
      class="schema-tree-node"
    >
      <div
        class="schema-tree-row"
        :class="store.selectedNodeId.value === node.id ? 'is-selected' : ''"
        @click="store.selectNode(node.id)"
      >
        <span class="schema-tree-label">
          <span class="schema-tree-title">{{ node.title }}</span>
          <code v-if="node.field" class="schema-tree-field">{{ node.field }}</code>
          <el-tag size="small" type="info" effect="plain" class="schema-tree-type">{{ typeLabel(node.type) }}</el-tag>
        </span>
        <span class="schema-tree-ops">
          <el-button link size="small" :title="t('formDesigner.moveUp', '上移')" :disabled="index === 0" @click.stop="store.moveNode(node.id, parentId, index - 1)"><i class="i-ep-top" /></el-button>
          <el-button link size="small" :title="t('formDesigner.moveDown', '下移')" :disabled="index === nodes.length - 1" @click.stop="store.moveNode(node.id, parentId, index + 2)"><i class="i-ep-bottom" /></el-button>
          <el-button v-if="parentId" link size="small" :title="t('formDesigner.moveToRoot', '移至根层')" @click.stop="store.moveNode(node.id, null, store.nodes.value.length)">{{ t('formDesigner.root', '根') }}</el-button>
          <el-button link size="small" :title="t('formDesigner.duplicate', '复制')" @click.stop="store.duplicateNode(node.id)"><i class="i-ep-copy-document" /></el-button>
        </span>
      </div>
      <div v-if="isContainer(node.type)" class="schema-tree-add">
        <el-dropdown trigger="click" @command="(type: string) => store.addNode(type, node.id)">
          <el-button size="small" class="schema-tree-add-btn"><i class="i-ep-plus" /> {{ t('formDesigner.addInContainer', '容器内添加') }}</el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="input">{{ t('formDesigner.addInput', '输入框') }}</el-dropdown-item>
              <el-dropdown-item command="select">{{ t('formDesigner.addSelect', '下拉选择') }}</el-dropdown-item>
              <el-dropdown-item command="group">{{ t('formDesigner.addGroup', '分组容器') }}</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
      <div v-if="node.children.length" class="schema-tree-children">
        <SchemaNodeTree
          :nodes="node.children"
          :store="store"
          :parent-id="node.id"
          :depth="depth + 1"
        />
      </div>
    </div>
    <el-empty v-if="depth === 0 && !nodes.length" :description="t('formDesigner.noNodes', '暂无表单节点')" :image-size="48" />
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { FormSchemaNode } from '@/api/form';
import type { DesignerStore } from '../../composables/useDesigner';
import { controlMeta } from '../../registry';

defineOptions({ name: 'SchemaNodeTree' });

const { t } = useI18n();

withDefaults(defineProps<{
  nodes: FormSchemaNode[];
  store: DesignerStore;
  parentId?: string | null;
  depth?: number;
}>(), { parentId: null, depth: 0 });

const typeLabel = (type: string) => {
  const meta = controlMeta(type);
  return meta.type === type ? meta.label : t('formDesigner.unknownControl', '未知控件');
};
const isContainer = (type: string) => ['group', 'grid', 'collapse', 'tabs', 'repeatable', 'subform'].includes(type);
</script>

<style scoped>
.schema-tree-row {
  align-items: center;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  gap: 8px;
  margin-top: 2px;
  min-height: 34px;
  padding: 4px 8px;
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
.schema-tree-title {
  color: var(--app-text);
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.schema-tree-field {
  background: var(--el-fill-color);
  border-radius: 4px;
  color: var(--el-text-color-secondary);
  flex-shrink: 0;
  font-size: 12px;
  padding: 0 4px;
}
.schema-tree-type {
  flex-shrink: 0;
}
.schema-tree-ops {
  align-items: center;
  display: flex;
  flex-shrink: 0;
  gap: 2px;
  opacity: 0;
  transition: opacity 0.15s ease;
}
.schema-tree-row:hover .schema-tree-ops,
.schema-tree-row.is-selected .schema-tree-ops {
  opacity: 1;
}
.schema-tree-children {
  border-left: 1px dashed var(--el-border-color);
  margin-left: 12px;
  padding-left: 10px;
}
.schema-tree-add {
  margin: 2px 0 2px 12px;
}
.schema-tree-add-btn {
  border-style: dashed;
  color: var(--el-text-color-regular);
}
</style>
