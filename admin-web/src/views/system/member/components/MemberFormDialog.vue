<template>
  <el-dialog v-model="visible" :title="row?.id ? '编辑会员' : '新增会员'" width="680px" :close-on-click-modal="false" destroy-on-close>
    <el-alert v-if="!row?.id" title="后台新建会员不设置密码，会员需后续通过前台找回或设置密码后才能登录。" type="warning" :closable="false" class="mb-4" />
    <el-skeleton v-if="loading" :rows="6" animated />
    <template v-else-if="loadError">
      <el-alert :title="loadError" type="error" :closable="false" />
      <el-button class="mt-4" @click="loadDefinition">重试</el-button>
    </template>
    <template v-else-if="definition">
      <el-alert v-if="unavailable" title="存在已停用、已删除或不可用的会员关系，原值已保留，请重新选择后保存。" type="warning" :closable="false" class="mb-4" />
      <SchemaRenderer :key="generation" ref="formRef" :schema="definition.schema!" :values="values" :options="relationOptions" />
      <p class="text-xs text-[var(--el-text-color-secondary)]">头像支持常见图片格式，最大 2MB</p>
    </template>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" :disabled="loading || !!loadError || !definition" @click="onSubmit">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { memberApi, type MemberModel, type MemberOptions, type MemberPayload } from '@/api/system/member';
import type { FormFieldDef } from '@/api/form';
import SchemaRenderer from '@/views/form/components/SchemaRenderer.vue';
import { flattenSchemaNodes } from '@/views/form/schema/types';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: MemberModel | null; options: MemberOptions }>(), { row: null });
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'success'): void }>();
const visible = computed({ get: () => props.modelValue, set: (value) => emit('update:modelValue', value) });
const formRef = ref<InstanceType<typeof SchemaRenderer>>();
const definition = ref<MemberOptions>();
const fields = ref<FormFieldDef[]>([]);
const values = ref<Record<string, any>>({});
const loading = ref(false);
const loadError = ref('');
const saving = ref(false);
const generation = ref(0);
const relationKeys = ['group_ids', 'tag_ids', 'level_id'] as const;
const relationOptions = computed<Record<string, Array<{ label: string; value: unknown }>>>(() => Object.fromEntries(flattenSchemaNodes(definition.value?.schema?.nodes ?? []).filter(({ node }) => node.field && relationKeys.includes(node.field as typeof relationKeys[number])).map(({ node }) => {
  const options = fields.value.find(field => field.field_name === node.field)?.options_source?.options;
  return [node.id, Array.isArray(options) ? options : []];
})));
const unavailable = computed(() => relationKeys.some((key) => {
  const current = key === 'level_id' ? [values.value[key]] : values.value[key] ?? [];
  const options = definition.value?.fields?.find((field) => field.field_name === key)?.options_source?.options;
  return Array.isArray(options) && current.some((id: number) => id > 0 && !options.some((option: any) => option.value === id));
}));

/** 每次打开独立加载，旧请求不得覆盖新会员或已关闭弹窗。 */
async function loadDefinition() {
  const token = ++generation.value;
  const row = props.row;
  definition.value = undefined;
  fields.value = [];
  loading.value = true;
  loadError.value = '';
  try {
    const result = await memberApi.options();
    if (token !== generation.value || !props.modelValue) return;
    if (result.schema?.schemaVersion !== 2 || !Array.isArray(result.schema.nodes) || !result.fields?.length) throw new Error('会员表单定义不可用');
    fields.value = result.fields.map((field) => ({ ...field, options_source: field.options_source ? { ...field.options_source, options: [...(field.options_source.options as any[] ?? [])] } : null }));
    values.value = Object.fromEntries(flattenSchemaNodes(result.schema.nodes).filter(({ node }) => node.field).map(({ node }) => [node.field!, Array.isArray(node.defaultValue) ? [...node.defaultValue] : node.defaultValue]));
    if (row) {
      values.value = { username: row.username, mobile: row.mobile, email: row.email, sex: row.sex, status: row.status, avatar: row.avatar,
        group_ids: [...row.groupIds], tag_ids: [...row.tagIds], level_id: row.levelId };
      for (const key of relationKeys) {
        const field = fields.value.find((item) => item.field_name === key);
        const options = field?.options_source?.options as Array<{ label: string; value: number }> | undefined;
        const ids = key === 'level_id' ? [row.levelId] : key === 'group_ids' ? row.groupIds : row.tagIds;
        const names = key === 'level_id' ? [row.levelName] : key === 'group_ids' ? row.groupNames : row.tagNames;
        ids.forEach((id, index) => {
          if (id > 0 && options && !options.some((option) => option.value === id)) options.push({ value: id, label: `${names[index] || '#' + id}（不可用，请重新选择）` });
        });
      }
    }
    definition.value = result;
  } catch {
    if (token === generation.value) loadError.value = '会员表单加载失败，请重试';
  } finally {
    if (token === generation.value) loading.value = false;
  }
}
watch(() => [props.modelValue, props.row] as const, ([opened]) => {
  if (opened) void loadDefinition();
  else { generation.value++; definition.value = undefined; }
}, { immediate: true });
onBeforeUnmount(() => { generation.value++; });

async function onSubmit() {
  if (saving.value || loading.value || loadError.value || !definition.value || unavailable.value) return;
  saving.value = true;
  const token = generation.value;
  const id = props.row?.id;
  try {
    if (!(await formRef.value?.validate().catch(() => false)) || token !== generation.value || !props.modelValue) return;
    const value = values.value;
    const payload: MemberPayload = { username: value.username, mobile: value.mobile, email: value.email, sex: value.sex,
      groupIds: [...value.group_ids], tagIds: [...value.tag_ids], levelId: value.level_id, avatar: value.avatar, status: value.status };
    if (id) await memberApi.update(id, payload);
    else await memberApi.create(payload);
    if (token === generation.value && props.modelValue) { emit('success'); visible.value = false; }
  } catch {
    // 请求层已显示业务错误，保留输入以便修正后重试。
  } finally {
    saving.value = false;
  }
}
</script>
