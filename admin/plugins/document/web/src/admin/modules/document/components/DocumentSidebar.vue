<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { DocumentCategory, DocumentScope } from '../types';

const props = defineProps<{ tree: DocumentCategory[]; scope: DocumentScope | ''; categoryId: number | null }>();
const emit = defineEmits<{ select: [scope: DocumentScope | '', categoryId: number | null] }>();

const { t } = useI18n();
const SCOPE_ICONS: Array<{ scope: DocumentScope; icon: string }> = [
  { scope: 'framework', icon: '◫' },
  { scope: 'plugin_dev', icon: '⌘' },
  { scope: 'official_plugin', icon: '◆' },
  { scope: 'third_party', icon: '◇' }
];

/** 某个范围下的目录（根目录本身就是范围入口，因此只列其子目录）。 */
const childrenOf = (scope: DocumentScope) => {
  const roots = props.tree.filter((node) => node.scope === scope);
  return roots.length === 1 ? roots[0].children : roots;
};
const sections = computed(() => SCOPE_ICONS.map((item) => ({ ...item, label: t(`pluginDocument.scope.${item.scope}`), children: childrenOf(item.scope) })));
</script>

<template>
  <aside class="doc-sidebar">
    <button type="button" class="doc-side-item" :class="{ active: !scope && !categoryId }" @click="emit('select', '', null)">
      <span class="icon">▤</span><span>{{ t('pluginDocument.all') }}</span>
    </button>
    <div v-for="section in sections" :key="section.scope" class="doc-side-section">
      <button
        type="button"
        class="doc-side-item"
        :data-scope="section.scope"
        :class="{ active: scope === section.scope && !categoryId }"
        @click="emit('select', section.scope, null)"
      >
        <span class="icon">{{ section.icon }}</span><span>{{ section.label }}</span>
      </button>
      <el-tree
        v-if="section.children.length"
        :data="section.children"
        node-key="id"
        :props="{ label: 'name', children: 'children' }"
        :current-node-key="categoryId ?? undefined"
        :expand-on-click-node="false"
        default-expand-all
        highlight-current
        class="doc-side-tree"
        @node-click="(node: DocumentCategory) => emit('select', node.scope, node.id)"
      >
        <template #default="{ data }">
          <span class="doc-tree-node" :data-category="data.id">{{ data.name }}</span>
        </template>
      </el-tree>
    </div>
  </aside>
</template>

<style scoped>
.doc-sidebar { padding: 12px 8px; overflow: auto; }
.doc-side-section { margin-top: 4px; }
.doc-side-item { display: flex; align-items: center; gap: 8px; width: 100%; height: 34px; padding: 0 10px; border: 0; border-radius: 6px; background: transparent; color: var(--el-text-color-regular); font: inherit; font-size: 13px; text-align: left; cursor: pointer; }
.doc-side-item:hover { background: var(--el-fill-color-light); }
.doc-side-item.active { background: var(--el-color-primary-light-9); color: var(--el-color-primary); font-weight: 600; }
.doc-side-item .icon { width: 16px; text-align: center; opacity: .8; }
.doc-side-tree { background: transparent; margin-left: 14px; font-size: 13px; }
.doc-tree-node { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
