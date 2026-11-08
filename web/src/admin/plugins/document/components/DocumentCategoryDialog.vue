<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { documentApi } from '../api';
import { SCOPES, type DocumentCategory, type DocumentScope } from '../types';

const props = defineProps<{ tree: DocumentCategory[] }>();
const emit = defineEmits<{ changed: [] }>();
const visible = defineModel<boolean>({ required: true });
const { t } = useI18n();
const tt = (key: string, args?: Record<string, unknown>) => (args ? t(`pluginDocument.${key}`, args) : t(`pluginDocument.${key}`));

/** 正在编辑的目录：id 为 null 表示新建。 */
const editing = ref<null | { id: number | null }>(null);
const form = reactive({ name: '', slug: '', scope: 'framework' as DocumentScope, parentId: 0, sortOrder: 0 });
const saving = ref(false);

const flatten = (nodes: DocumentCategory[], depth = 0): Array<DocumentCategory & { depth: number }> =>
  nodes.flatMap((node) => [{ ...node, depth }, ...flatten(node.children || [], depth + 1)]);
const flat = computed(() => flatten(props.tree));

function startAdd(parent: DocumentCategory | null) {
  editing.value = { id: null };
  Object.assign(form, { name: '', slug: '', scope: parent?.scope ?? 'framework', parentId: parent?.id ?? 0, sortOrder: 0 });
}
function startEdit(node: DocumentCategory) {
  editing.value = { id: node.id };
  Object.assign(form, { name: node.name, slug: node.slug, scope: node.scope, parentId: node.parentId, sortOrder: node.sortOrder });
}

const message = (e: unknown) => {
  const m = (e as { msg?: unknown } | null)?.msg;
  return typeof m === 'string' && m ? m : tt('categories.failed');
};

async function save() {
  if (!editing.value) return;
  saving.value = true;
  try {
    const payload = { name: form.name.trim(), slug: form.slug.trim(), scope: form.scope, parentId: form.parentId, sortOrder: form.sortOrder };
    if (editing.value.id === null) await documentApi.createCategory(payload);
    else await documentApi.updateCategory(editing.value.id, payload);
    editing.value = null;
    ElMessage.success(tt('categories.saved'));
    emit('changed');
  } catch (e) {
    ElMessage.error(message(e));
  } finally {
    saving.value = false;
  }
}

async function remove(node: DocumentCategory) {
  const ok = await ElMessageBox.confirm(tt('categories.deleteBody', { name: node.name }), tt('categories.deleteTitle'), { type: 'warning' }).then(() => true, () => false);
  if (!ok) return;
  try {
    await documentApi.deleteCategory(node.id);
    ElMessage.success(tt('categories.deleted'));
    emit('changed');
  } catch (e) {
    ElMessage.error(message(e));
  }
}
</script>

<template>
  <el-dialog v-model="visible" :title="tt('categories.title')" width="min(760px, 94vw)" append-to-body>
    <div class="cat-layout">
      <div class="cat-tree">
        <el-button size="small" @click="startAdd(null)">{{ tt('categories.addRoot') }}</el-button>
        <el-tree :data="tree" node-key="id" default-expand-all :expand-on-click-node="false" :props="{ label: 'name', children: 'children' }">
          <template #default="{ data }">
            <span class="cat-node">
              <span class="cat-name">{{ data.name }}</span>
              <span class="cat-actions">
                <el-button link size="small" :data-category-add="data.id" @click.stop="startAdd(data)">{{ tt('categories.add') }}</el-button>
                <el-button link size="small" :data-category-edit="data.id" @click.stop="startEdit(data)">{{ tt('categories.edit') }}</el-button>
                <el-button link size="small" type="danger" :data-category-delete="data.id" @click.stop="remove(data)">{{ tt('categories.delete') }}</el-button>
              </span>
            </span>
          </template>
        </el-tree>
      </div>
      <el-form v-if="editing" class="cat-form" label-position="top" @submit.prevent="save">
        <el-form-item :label="tt('categories.name')"><el-input v-model="form.name" maxlength="100" /></el-form-item>
        <el-form-item :label="tt('categories.slug')"><el-input v-model="form.slug" placeholder="framework-database" /></el-form-item>
        <el-form-item :label="tt('categories.scope')">
          <el-select v-model="form.scope" :disabled="form.parentId > 0">
            <el-option v-for="s in SCOPES" :key="s" :label="t(`pluginDocument.scope.${s}`)" :value="s" />
          </el-select>
        </el-form-item>
        <el-form-item :label="tt('categories.sortOrder')"><el-input-number v-model="form.sortOrder" :min="0" :max="9999" /></el-form-item>
        <div class="cat-form-actions">
          <el-button @click="editing = null">{{ tt('categories.cancel') }}</el-button>
          <el-button type="primary" :loading="saving" data-testid="category-save" @click="save">{{ tt('categories.save') }}</el-button>
        </div>
        <p v-if="form.parentId" class="cat-hint">{{ flat.find((c) => c.id === form.parentId)?.name }}</p>
      </el-form>
    </div>
  </el-dialog>
</template>

<style scoped>
.cat-layout { display: grid; grid-template-columns: minmax(0, 1fr) 260px; gap: 16px; min-height: 320px; }
.cat-tree { display: flex; flex-direction: column; gap: 8px; overflow: auto; max-height: 60vh; }
.cat-node { display: flex; align-items: center; justify-content: space-between; width: 100%; gap: 8px; }
.cat-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cat-actions { flex-shrink: 0; }
.cat-form { border-left: 1px solid var(--el-border-color-lighter); padding-left: 16px; }
.cat-form-actions { display: flex; justify-content: flex-end; gap: 8px; }
.cat-hint { font-size: 12px; color: var(--el-text-color-secondary); margin: 8px 0 0; }
@media (max-width: 720px) { .cat-layout { grid-template-columns: 1fr; } .cat-form { border-left: 0; padding-left: 0; } }
</style>
