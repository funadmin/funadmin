<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { hasPermission } from '@/utils/permission';
import { useI18n } from 'vue-i18n';
import type { FormInstance, FormRules } from 'element-plus';
import { SCOPES, TYPES, type DocumentCategory, type DocumentMetaInput, type DocumentScope, type DocumentSummary } from '../types';

const props = defineProps<{ row: DocumentSummary | null; tree: DocumentCategory[]; defaultScope: DocumentScope | ''; defaultCategoryId: number | null }>();
const emit = defineEmits<{ submit: [data: DocumentMetaInput] }>();
const visible = defineModel<boolean>({ required: true });

const { t } = useI18n();
const tt = (key: string) => t(`pluginDocument.meta.${key}`);

const formRef = ref<FormInstance>();
const blank = (): DocumentMetaInput => ({ visibility: 'public', title: '', slug: '', summary: '', scope: 'framework', type: 'guide', pluginCode: '', appliesTo: '', sortOrder: 0, categoryIds: [] });
const form = reactive<DocumentMetaInput>(blank());
const rules = computed<FormRules>(() => ({
  title: [{ required: true, message: tt('titleRequired'), trigger: 'blur' }, { max: 200, message: tt('titleMax'), trigger: 'blur' }],
  slug: [{ required: true, message: tt('slugRequired'), trigger: 'blur' }, { pattern: /^[a-z0-9][a-z0-9-]*$/, message: tt('slugPattern'), trigger: 'blur' }],
  pluginCode: [{ pattern: /^$|^[a-z][a-z0-9]*$/, message: tt('pluginPattern'), trigger: 'blur' }]
}));

const flatten = (nodes: DocumentCategory[], depth = 0): Array<DocumentCategory & { depth: number }> =>
  nodes.flatMap((node) => [{ ...node, depth }, ...flatten(node.children || [], depth + 1)]);

watch(visible, (open) => {
  if (!open) return;
  Object.assign(form, blank(), props.row
    ? { visibility: props.row.visibility ?? 'admin', title: props.row.title, slug: props.row.slug, summary: props.row.summary, scope: props.row.scope, type: props.row.type, pluginCode: props.row.pluginCode, appliesTo: props.row.appliesTo, sortOrder: props.row.sortOrder ?? 0, categoryIds: [...props.row.categoryIds] }
    : { scope: props.defaultScope || 'framework', categoryIds: props.defaultCategoryId ? [props.defaultCategoryId] : [] });
});
// 切换范围时清掉不属于新范围的目录，避免后端拒绝。
watch(() => form.scope, (scope) => {
  const allowed = new Set(flatten(props.tree).filter((c) => c.scope === scope).map((c) => c.id));
  form.categoryIds = (form.categoryIds || []).filter((id) => allowed.has(id));
});

async function submit() {
  await formRef.value?.validate();
  emit('submit', { ...form, categoryIds: [...(form.categoryIds || [])] });
}
</script>

<template>
  <el-dialog v-model="visible" :title="row ? tt('titleEdit') : tt('titleNew')" width="min(560px, 94vw)" :close-on-click-modal="false" append-to-body>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="84px">
      <el-form-item :label="tt('title')" prop="title"><el-input v-model="form.title" maxlength="200" /></el-form-item>
      <el-form-item :label="tt('slug')" prop="slug"><el-input v-model="form.slug" placeholder="plugin-quick-start" /></el-form-item>
      <el-form-item :label="tt('scope')">
        <el-select v-model="form.scope"><el-option v-for="s in SCOPES" :key="s" :label="t(`pluginDocument.scope.${s}`)" :value="s" /></el-select>
      </el-form-item>
      <el-form-item :label="tt('visibility')">
        <div class="visibility-field">
          <el-select v-model="form.visibility" :disabled="!!row && !hasPermission('document:document:publish')">
            <el-option :label="tt('public')" value="public" />
            <el-option :label="tt('adminOnly')" value="admin" />
          </el-select>
          <small>{{ tt('visibilityHint') }}</small>
        </div>
      </el-form-item>
      <el-form-item :label="tt('type')">
        <el-select v-model="form.type"><el-option v-for="docType in TYPES" :key="docType" :label="t(`pluginDocument.type.${docType}`)" :value="docType" /></el-select>
      </el-form-item>
      <el-form-item :label="tt('category')">
        <el-select v-model="form.categoryIds" multiple clearable :placeholder="tt('optional')">
          <el-option v-for="c in flatten(tree).filter((x) => x.scope === form.scope)" :key="c.id" :label="'　'.repeat(c.depth) + c.name" :value="c.id" />
        </el-select>
      </el-form-item>
      <el-form-item :label="tt('sortOrder')">
        <el-input-number v-model="form.sortOrder" :min="0" :max="9999" :step="10" controls-position="right" />
        <span class="meta-hint">{{ tt('sortOrderHint') }}</span>
      </el-form-item>
      <el-form-item v-if="form.scope === 'official_plugin' || form.scope === 'third_party'" :label="tt('pluginCode')" prop="pluginCode">
        <el-input v-model="form.pluginCode" :placeholder="tt('pluginCodePh')" />
      </el-form-item>
      <el-form-item :label="tt('appliesTo')"><el-input v-model="form.appliesTo" maxlength="64" :placeholder="tt('appliesToPh')" /></el-form-item>
      <el-form-item :label="tt('summary')"><el-input v-model="form.summary" type="textarea" :rows="2" maxlength="500" /></el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">{{ tt('cancel') }}</el-button>
      <el-button type="primary" @click="submit">{{ tt('confirm') }}</el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.visibility-field { width: 100%; }
.meta-hint { margin-left: 10px; font-size: 12px; color: var(--el-text-color-secondary); }
.visibility-field small { display: block; margin-top: 6px; color: var(--el-text-color-secondary); line-height: 1.6; }
</style>
