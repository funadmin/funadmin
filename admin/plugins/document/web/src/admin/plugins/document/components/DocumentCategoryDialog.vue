<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { documentApi } from '../api';
import { SCOPES, type DocumentCategory, type DocumentScope } from '../types';

const props = defineProps<{ tree: DocumentCategory[] }>();
const emit = defineEmits<{ changed: [] }>();
const visible = defineModel<boolean>({ required: true });
const { t } = useI18n();
const tt = (key: string, args?: Record<string, unknown>) => (args ? t(`pluginDocument.categories.${key}`, args) : t(`pluginDocument.categories.${key}`));

/** 正在编辑的目录：id 为 null 表示新建。 */
const editing = ref<null | { id: number | null }>(null);
const form = reactive({ name: '', slug: '', scope: 'framework' as DocumentScope, parentId: 0, sortOrder: 0 });
const saving = ref(false);

const flatten = (nodes: DocumentCategory[]): DocumentCategory[] => nodes.flatMap((node) => [node, ...flatten(node.children || [])]);
const flat = computed(() => flatten(props.tree));
const parentName = computed(() => flat.value.find((c) => c.id === form.parentId)?.name ?? '');
const formTitle = computed(() => {
  if (editing.value?.id != null) return tt('editTitle', { name: flat.value.find((c) => c.id === editing.value?.id)?.name ?? '' });
  return form.parentId ? tt('addChildTitle', { name: parentName.value }) : tt('addRoot');
});

/** 有子目录或文档的目录不能删除（服务端同样校验）。 */
const removable = (node: DocumentCategory) => !(node.children?.length) && !(node.documentCount ?? 0);

watch(visible, (open) => { if (!open) editing.value = null; });

function startAdd(parent: DocumentCategory | null) {
  editing.value = { id: null };
  const siblings = parent ? parent.children || [] : props.tree;
  const nextSort = siblings.reduce((max, c) => Math.max(max, c.sortOrder), 0) + 10;
  Object.assign(form, { name: '', slug: '', scope: parent?.scope ?? 'framework', parentId: parent?.id ?? 0, sortOrder: Math.min(nextSort, 9999) });
}
function startEdit(node: DocumentCategory) {
  editing.value = { id: node.id };
  Object.assign(form, { name: node.name, slug: node.slug, scope: node.scope, parentId: node.parentId, sortOrder: node.sortOrder });
}

const message = (e: unknown) => {
  const m = (e as { msg?: unknown } | null)?.msg;
  return typeof m === 'string' && m ? m : tt('failed');
};

async function save() {
  if (!editing.value) return;
  saving.value = true;
  try {
    const payload = { name: form.name.trim(), slug: form.slug.trim(), scope: form.scope, parentId: form.parentId, sortOrder: form.sortOrder };
    if (editing.value.id === null) await documentApi.createCategory(payload);
    else await documentApi.updateCategory(editing.value.id, payload);
    editing.value = null;
    ElMessage.success(tt('saved'));
    emit('changed');
  } catch (e) {
    ElMessage.error(message(e));
  } finally {
    saving.value = false;
  }
}

async function remove(node: DocumentCategory) {
  const ok = await ElMessageBox.confirm(tt('deleteBody', { name: node.name }), tt('deleteTitle'), { type: 'warning' }).then(() => true, () => false);
  if (!ok) return;
  try {
    await documentApi.deleteCategory(node.id);
    if (editing.value?.id === node.id) editing.value = null;
    ElMessage.success(tt('deleted'));
    emit('changed');
  } catch (e) {
    ElMessage.error(message(e));
  }
}
</script>

<template>
  <el-dialog v-model="visible" :title="tt('title')" width="min(880px, 94vw)" class="cat-dialog" append-to-body>
    <div class="cat-toolbar">
      <p class="cat-intro">{{ tt('intro') }}</p>
      <el-button type="primary" data-category-add-root @click="startAdd(null)">+ {{ tt('addRoot') }}</el-button>
    </div>

    <div class="cat-layout">
      <div class="cat-list">
        <el-empty v-if="!tree.length" :description="tt('empty')" :image-size="56" />
        <el-tree v-else :data="tree" node-key="id" default-expand-all :expand-on-click-node="false" :props="{ label: 'name', children: 'children' }">
          <template #default="{ data }">
            <div class="cat-node" :class="{ 'is-active': editing?.id === data.id, 'is-root': !data.parentId }">
              <div class="cat-main">
                <span class="cat-name">{{ data.name }}</span>
                <span class="cat-meta">
                  <span v-if="!data.parentId" class="cat-scope">{{ t(`pluginDocument.scope.${data.scope}`) }}</span>
                  <code>{{ data.slug }}</code>
                  <span>{{ tt('sortValue', { n: data.sortOrder }) }}</span>
                  <span v-if="data.documentCount !== undefined">{{ tt('docCount', { n: data.documentCount }) }}</span>
                </span>
              </div>
              <span class="cat-actions">
                <el-button link size="small" type="primary" :data-category-add="data.id" @click.stop="startAdd(data)">{{ tt('add') }}</el-button>
                <el-button link size="small" type="primary" :data-category-edit="data.id" @click.stop="startEdit(data)">{{ tt('edit') }}</el-button>
                <span :title="removable(data) ? '' : tt('notEmpty')">
                  <el-button link size="small" type="danger" :disabled="!removable(data)" :data-category-delete="data.id" @click.stop="remove(data)">{{ tt('delete') }}</el-button>
                </span>
              </span>
            </div>
          </template>
        </el-tree>
      </div>

      <aside class="cat-panel">
        <el-form v-if="editing" class="cat-form" label-position="top" @submit.prevent="save">
          <h3 class="cat-form-title">{{ formTitle }}</h3>
          <el-form-item :label="tt('name')"><el-input v-model="form.name" maxlength="100" :placeholder="tt('namePh')" /></el-form-item>
          <el-form-item :label="tt('slug')">
            <el-input v-model="form.slug" placeholder="framework-database" />
            <small class="cat-field-hint">{{ tt('slugHint') }}</small>
          </el-form-item>
          <el-form-item :label="tt('scope')">
            <el-select v-model="form.scope" :disabled="form.parentId > 0">
              <el-option v-for="s in SCOPES" :key="s" :label="t(`pluginDocument.scope.${s}`)" :value="s" />
            </el-select>
            <small v-if="form.parentId > 0" class="cat-field-hint">{{ tt('scopeInherit') }}</small>
          </el-form-item>
          <el-form-item :label="tt('sortOrder')">
            <el-input-number v-model="form.sortOrder" :min="0" :max="9999" :step="10" controls-position="right" />
            <small class="cat-field-hint">{{ tt('sortHint') }}</small>
          </el-form-item>
          <div class="cat-form-actions">
            <el-button @click="editing = null">{{ tt('cancel') }}</el-button>
            <el-button type="primary" :loading="saving" data-testid="category-save" @click="save">{{ tt('save') }}</el-button>
          </div>
        </el-form>
        <div v-else class="cat-placeholder">
          <strong>{{ tt('pickTitle') }}</strong>
          <p>{{ tt('pickBody') }}</p>
        </div>
      </aside>
    </div>
  </el-dialog>
</template>

<style scoped>
.cat-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.cat-intro { margin: 0; font-size: 13px; line-height: 1.6; color: var(--el-text-color-secondary); }
.cat-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 16px; min-height: 360px; }
.cat-list { max-height: 60vh; overflow: auto; padding: 6px; border: 1px solid var(--el-border-color-lighter); border-radius: 8px; }
.cat-list :deep(.el-tree-node__content) { height: auto; padding-top: 2px; padding-bottom: 2px; border-radius: 6px; }
.cat-list :deep(.el-tree-node__content:hover) { background: var(--el-fill-color-light); }
.cat-node { flex: 1; min-width: 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 8px 8px 2px; border-radius: 6px; }
.cat-node.is-active { background: var(--el-color-primary-light-9); box-shadow: inset 2px 0 0 var(--el-color-primary); }
.cat-main { min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.cat-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; color: var(--el-text-color-primary); }
.cat-node.is-root .cat-name { font-weight: 600; }
.cat-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 10px; font-size: 12px; color: var(--el-text-color-secondary); }
.cat-meta code { padding: 0 5px; border-radius: 4px; background: var(--el-fill-color); font-size: 11px; color: var(--el-text-color-regular); }
.cat-scope { padding: 0 6px; border-radius: 4px; background: var(--el-color-primary-light-9); color: var(--el-color-primary); }
.cat-actions { flex-shrink: 0; display: inline-flex; align-items: center; gap: 2px; opacity: 0; transition: opacity .15s; }
.cat-actions :deep(.el-button + .el-button) { margin-left: 6px; }
.cat-actions > span { margin-left: 6px; }
.cat-node:hover .cat-actions, .cat-node:focus-within .cat-actions, .cat-node.is-active .cat-actions { opacity: 1; }
.cat-panel { padding: 16px; border-radius: 8px; background: var(--el-fill-color-extra-light); border: 1px solid var(--el-border-color-lighter); }
.cat-form-title { margin: 0 0 14px; font-size: 15px; font-weight: 600; color: var(--el-text-color-primary); overflow-wrap: anywhere; }
.cat-form :deep(.el-select), .cat-form :deep(.el-input-number) { width: 100%; }
.cat-field-hint { display: block; width: 100%; margin-top: 4px; font-size: 12px; line-height: 1.5; color: var(--el-text-color-secondary); }
.cat-form-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }
.cat-placeholder { height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 6px; text-align: center; color: var(--el-text-color-secondary); font-size: 13px; }
.cat-placeholder strong { color: var(--el-text-color-regular); font-size: 14px; font-weight: 600; }
.cat-placeholder p { margin: 0; max-width: 220px; line-height: 1.6; }
@media (hover: none) { .cat-actions { opacity: 1; } }
@media (max-width: 760px) {
  .cat-toolbar { flex-direction: column; align-items: stretch; }
  .cat-layout { grid-template-columns: 1fr; }
  .cat-actions { opacity: 1; }
}
</style>
