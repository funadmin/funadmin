<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { SCOPES, TYPES, type DocumentCategory, type DocumentMetaInput, type DocumentScope, type DocumentSummary } from '../types';

const props = defineProps<{ row: DocumentSummary | null; tree: DocumentCategory[]; defaultScope: DocumentScope | ''; defaultCategoryId: number | null }>();
const emit = defineEmits<{ submit: [data: DocumentMetaInput] }>();
const visible = defineModel<boolean>({ required: true });

const SCOPE_LABELS: Record<DocumentScope, string> = { framework: 'FunAdmin 主框架', plugin_dev: '插件开发', official_plugin: '官方插件', third_party: '第三方插件' };
const TYPE_LABELS: Record<string, string> = { guide: '指南', reference: '参考', api: 'API', architecture: '架构设计', deployment: '部署运维', adr: '技术决策（ADR）', changelog: '更新日志' };

const formRef = ref<FormInstance>();
const blank = (): DocumentMetaInput => ({ title: '', slug: '', summary: '', scope: 'framework', type: 'guide', pluginCode: '', appliesTo: '', categoryIds: [] });
const form = reactive<DocumentMetaInput>(blank());
const rules: FormRules = {
  title: [{ required: true, message: '请输入标题', trigger: 'blur' }, { max: 200, message: '不超过 200 个字符', trigger: 'blur' }],
  slug: [{ required: true, message: '请输入标识', trigger: 'blur' }, { pattern: /^[a-z0-9][a-z0-9-]*$/, message: '小写字母、数字和连字符', trigger: 'blur' }],
  pluginCode: [{ pattern: /^$|^[a-z][a-z0-9]*$/, message: '插件标识为小写字母开头的字母数字', trigger: 'blur' }]
};

const flatten = (nodes: DocumentCategory[], depth = 0): Array<DocumentCategory & { depth: number }> =>
  nodes.flatMap((node) => [{ ...node, depth }, ...flatten(node.children || [], depth + 1)]);

watch(visible, (open) => {
  if (!open) return;
  Object.assign(form, blank(), props.row
    ? { title: props.row.title, slug: props.row.slug, summary: props.row.summary, scope: props.row.scope, type: props.row.type, pluginCode: props.row.pluginCode, appliesTo: props.row.appliesTo, categoryIds: [...props.row.categoryIds] }
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
  <el-dialog v-model="visible" :title="row ? '文档信息' : '新建文档'" width="min(560px, 94vw)" :close-on-click-modal="false" append-to-body>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="84px">
      <el-form-item label="标题" prop="title"><el-input v-model="form.title" maxlength="200" /></el-form-item>
      <el-form-item label="标识" prop="slug"><el-input v-model="form.slug" placeholder="plugin-quick-start" /></el-form-item>
      <el-form-item label="范围">
        <el-select v-model="form.scope"><el-option v-for="s in SCOPES" :key="s" :label="SCOPE_LABELS[s]" :value="s" /></el-select>
      </el-form-item>
      <el-form-item label="类型">
        <el-select v-model="form.type"><el-option v-for="t in TYPES" :key="t" :label="TYPE_LABELS[t]" :value="t" /></el-select>
      </el-form-item>
      <el-form-item label="目录">
        <el-select v-model="form.categoryIds" multiple clearable placeholder="可选">
          <el-option v-for="c in flatten(tree).filter((x) => x.scope === form.scope)" :key="c.id" :label="'　'.repeat(c.depth) + c.name" :value="c.id" />
        </el-select>
      </el-form-item>
      <el-form-item v-if="form.scope === 'official_plugin' || form.scope === 'third_party'" label="插件标识" prop="pluginCode">
        <el-input v-model="form.pluginCode" placeholder="如 mail、cms" />
      </el-form-item>
      <el-form-item label="适用版本"><el-input v-model="form.appliesTo" maxlength="64" placeholder="如 v2.0" /></el-form-item>
      <el-form-item label="摘要"><el-input v-model="form.summary" type="textarea" :rows="2" maxlength="500" /></el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="submit">确定</el-button>
    </template>
  </el-dialog>
</template>
