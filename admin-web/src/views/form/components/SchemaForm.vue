<template>
  <el-form ref="formRef" :model="values" :rules="rules" label-width="110px">
    <el-row :gutter="16">
      <el-col v-for="field in visibleFields" :key="field.field_name" :span="field.form_span || 24">
        <el-form-item :label="field.label" :prop="field.field_name">
          <el-switch v-if="field.type === 'switch'" v-model="values[field.field_name]" :disabled="disabled(field)" :active-value="1" :inactive-value="0" />
          <el-input-number v-else-if="field.type === 'number'" v-model="values[field.field_name]" class="w-full" :disabled="disabled(field)" />
          <el-select
            v-else-if="field.type === 'select'"
            v-model="values[field.field_name]"
            :multiple="field.relation_multiple === 1"
            :placeholder="field.placeholder || field.label"
            :disabled="disabled(field)"
            class="w-full"
          >
            <el-option v-for="option in optionsOf(field)" :key="String(option.value)" :label="String(option.label)" :value="option.value" />
          </el-select>
          <el-date-picker
            v-else-if="field.type === 'date'"
            v-model="values[field.field_name]"
            type="date"
            value-format="YYYY-MM-DD"
            :placeholder="field.placeholder || field.label"
            :disabled="disabled(field)"
            class="w-full"
          />
          <el-input
            v-else
            v-model="values[field.field_name]"
            :type="field.type === 'textarea' ? 'textarea' : 'text'"
            :rows="3"
            :placeholder="field.placeholder || field.label"
            :disabled="disabled(field)"
          />
        </el-form-item>
      </el-col>
    </el-row>
  </el-form>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import type { FormFieldDef } from '@/api/form';
import { formDataApi } from '@/api/formData';
import { evaluateLinkRules } from '../linkRules';

const props = defineProps<{ formKey: string; fields: FormFieldDef[]; values: Record<string, any> }>();

const formRef = ref<FormInstance>();
const remoteOptions = ref<Record<string, Array<{ label: string; value: string | number }>>>({});

const linkState = computed(() => evaluateLinkRules(props.fields, props.values));
const visibleFields = computed(() => props.fields.filter((field) => !linkState.value.effects[field.field_name]?.hidden));
const disabled = (field: FormFieldDef) => Boolean(linkState.value.effects[field.field_name]?.disabled);

const needsRemote = (field: FormFieldDef) =>
  field.type === 'select' &&
  ((field.options_source?.mode === 'relation') || (field.options_source == null && field.relation_type === 'belongs_to'));

const optionsOf = (field: FormFieldDef) => {
  if (needsRemote(field)) return remoteOptions.value[field.field_name] ?? [];
  const options = field.options_source?.options;
  return Array.isArray(options) ? (options as Array<{ label: string; value: string | number }>) : [];
};

const rules = computed<FormRules>(() => {
  const result: FormRules = {};
  for (const field of props.fields) {
    const items: Array<Record<string, unknown>> = [];
    if (field.form_required === 1) {
      items.push({ required: true, message: `${field.label}不能为空`, trigger: ['blur', 'change'] });
    }
    const extra = (field.validate_rules ?? {}) as Record<string, unknown>;
    if (typeof extra.pattern === 'string' && extra.pattern) {
      items.push({ pattern: new RegExp(extra.pattern), message: `${field.label}格式不正确`, trigger: 'blur' });
    }
    if (items.length) result[field.field_name] = items;
  }
  return result;
});

// 联动回写值
watch(
  () => linkState.value.writes,
  (writes) => {
    for (const [key, value] of Object.entries(writes)) {
      if (props.values[key] !== value) props.values[key] = value;
    }
  },
  { deep: true }
);

onMounted(async () => {
  const targets = props.fields.filter(needsRemote);
  await Promise.all(
    targets.map(async (field) => {
      const data = await formDataApi.options(props.formKey, field.field_name);
      remoteOptions.value[field.field_name] = data.options;
    })
  );
});

const validate = async () => formRef.value?.validate();
defineExpose({ validate });
</script>
