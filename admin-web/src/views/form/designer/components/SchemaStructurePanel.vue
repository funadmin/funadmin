<template>
  <el-tabs model-value="validation">
    <el-tab-pane label="验证规则" name="validation">
      <JsonSection v-model="draft.validation" empty-value="[]" @apply="apply('validation', $event)" />
    </el-tab-pane>
    <el-tab-pane label="联动条件" name="conditions">
      <JsonSection v-model="draft.conditions" empty-value="[]" @apply="apply('conditions', $event)" />
    </el-tab-pane>
    <el-tab-pane label="事件动作" name="events">
      <JsonSection v-model="draft.events" empty-value="{}" @apply="apply('events', $event)" />
    </el-tab-pane>
    <el-tab-pane label="数据源" name="dataSource">
      <JsonSection v-model="draft.dataSource" empty-value="null" @apply="apply('dataSource', $event)" />
    </el-tab-pane>
  </el-tabs>
</template>

<script setup lang="ts">
import { defineComponent, h, reactive, watch } from 'vue';
import { ElButton, ElInput, ElMessage } from 'element-plus';
import type { FormSchemaNode } from '@/api/form';

const props = defineProps<{ node: FormSchemaNode }>();
const emit = defineEmits<{ update: [patch: Partial<FormSchemaNode>] }>();
const draft = reactive({ validation: '', conditions: '', events: '', dataSource: '' });

const syncDraft = () => {
  draft.validation = JSON.stringify(props.node.validation ?? [], null, 2);
  draft.conditions = JSON.stringify(props.node.conditions ?? [], null, 2);
  draft.events = JSON.stringify(props.node.events ?? {}, null, 2);
  draft.dataSource = JSON.stringify(props.node.dataSource ?? null, null, 2);
};
watch(() => props.node, syncDraft, { immediate: true, deep: true });

const apply = (key: keyof typeof draft, raw: string) => {
  try {
    emit('update', { [key]: JSON.parse(raw) });
    ElMessage.success('结构配置已应用');
  } catch {
    ElMessage.warning('请输入合法 JSON');
  }
};

const JsonSection = defineComponent({
  props: { modelValue: { type: String, required: true }, emptyValue: { type: String, required: true } },
  emits: ['update:modelValue', 'apply'],
  setup(sectionProps, { emit: sectionEmit }) {
    return () => h('div', { class: 'flex flex-col gap-2' }, [
      h(ElInput, {
        modelValue: sectionProps.modelValue,
        type: 'textarea', rows: 8, placeholder: sectionProps.emptyValue,
        'onUpdate:modelValue': (value: string) => sectionEmit('update:modelValue', value)
      }),
      h(ElButton, { type: 'primary', plain: true, onClick: () => sectionEmit('apply', sectionProps.modelValue) }, () => '应用配置')
    ]);
  }
});
</script>
