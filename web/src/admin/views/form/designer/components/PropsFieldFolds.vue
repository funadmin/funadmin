<template>
  <el-collapse v-model="openFolds" class="props-folds">
    <el-collapse-item v-if="!layout" name="validation" data-fold="validation">
      <template #title>
        <span class="props-fold__label" data-fold-label>{{ t('formDesigner.propsPanel.foldValidation', '校验规则') }}</span>
        <em class="props-fold__summary" data-fold-summary :title="validationSummary">{{ validationSummary }}</em>
      </template>
      <ul v-if="validationRules.length" class="props-rule-list">
        <li v-for="rule in validationRules" :key="rule.type">
          <span data-rule-summary>{{ rule.text || displayRuleValue(rule.value) }}</span>
          <code>{{ rule.type }}</code>
        </li>
      </ul>
      <p class="props-fold-hint">{{ t('formDesigner.layout.validationHint', '必填在上方开关设置；其余规则随字段一起保存，此处为只读摘要。') }}</p>
    </el-collapse-item>
    <el-collapse-item v-if="!layout" name="conditions" data-fold="conditions">
      <template #title>
        <span class="props-fold__label" data-fold-label>{{ t('formDesigner.propsPanel.foldConditions', '联动条件') }}</span>
        <em class="props-fold__summary" data-fold-summary :title="conditionsSummary">{{ conditionsSummary }}</em>
      </template>
      <ul v-if="conditionTexts.length" class="props-rule-list">
        <li v-for="(text, index) in conditionTexts" :key="index"><span data-condition-summary>{{ text }}</span></li>
      </ul>
      <pre v-if="conditionRules.length" class="props-json-view">{{ conditionsJson }}</pre>
      <p class="props-fold-hint">{{ t('formDesigner.layout.conditionsHint', '联动条件随字段一起保存，此处为只读摘要。') }}</p>
    </el-collapse-item>
    <el-collapse-item v-if="!layout" name="dataSource" data-fold="dataSource">
      <template #title><span class="props-fold__label" data-fold-label>{{ t('formDesigner.tabDataSource', '数据源') }}</span></template>
      <el-form label-position="top" size="small" class="props-fold-form">
        <el-form-item v-if="field.type === 'dictionary'" :label="t('formDesigner.dictionaryCode', '字典编码')">
          <el-select
            data-testid="dictionary-code-select"
            :model-value="dictionaryCode"
            filterable
            clearable
            class="w-full"
            :loading="dictTypesLoading"
            :placeholder="t('formDesigner.dictionaryPlaceholder', '请选择字典')"
            @visible-change="loadDictTypes"
            @update:model-value="updateDictionary"
          >
            <el-option v-for="item in dictTypes" :key="item.code" :label="`${item.name}（${item.code}）`" :value="item.code" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('formDesigner.optionsSource', '选项来源')">
          <el-input :model-value="optionsJson" type="textarea" :rows="6" placeholder='{"kind":"static"}' @change="onOptionsJson" />
        </el-form-item>
      </el-form>
    </el-collapse-item>
    <el-collapse-item name="raw" data-fold="raw">
      <template #title><span class="props-fold__label" data-fold-label>{{ t('formDesigner.rawJson', '原始 JSON（高级）') }}</span></template>
      <el-form label-position="top" size="small" class="props-fold-form">
        <el-form-item label="props">
          <el-input :model-value="propsJson" type="textarea" :rows="6" placeholder='{"size":"large"}' @change="onPropsJson" />
        </el-form-item>
      </el-form>
    </el-collapse-item>
  </el-collapse>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import type { FormFieldDef } from '@/api/form';
import { dictTypeApi, type DictType } from '@/api/system/dict';
import { describeConditionRule, validationEntries, type SummaryTranslate } from './propsSummary';

defineOptions({ name: 'PropsFieldFolds' });

const { t } = useI18n();
const props = defineProps<{ field: FormFieldDef; layout: boolean }>();
const emit = defineEmits<{ (event: 'update', patch: Partial<FormFieldDef>): void }>();
const patch = (value: Partial<FormFieldDef>) => emit('update', value);

// 字典控件保存期强校验 options_source.dictionary，这里提供字典分类选择入口；写入统一收敛为 kind 键。
const dictTypes = ref<DictType[]>([]);
const dictTypesLoading = ref(false);
const dictionaryCode = computed(() => String(props.field.options_source?.dictionary ?? ''));
const loadDictTypes = async (visible = true) => {
  if (!visible || dictTypes.value.length || dictTypesLoading.value) return;
  dictTypesLoading.value = true;
  try {
    const result = await dictTypeApi.list({ page: 1, pageSize: 500 });
    dictTypes.value = result.list ?? [];
  } finally {
    dictTypesLoading.value = false;
  }
};
watch(() => props.field.type, (type) => { if (type === 'dictionary') void loadDictTypes(); }, { immediate: true });
const updateDictionary = (value: string | undefined) => {
  const current = (props.field.options_source ?? {}) as Record<string, unknown>;
  patch({ options_source: { ...current, kind: 'dictionary', dictionary: value ?? '' } });
};

// 折叠项：字典控件默认展开数据源，避免必填的字典编码藏在折叠里。
const openFolds = ref<string[]>([]);
watch(() => props.field.type, (type) => {
  if (type === 'dictionary' && !openFolds.value.includes('dataSource')) openFolds.value = [...openFolds.value, 'dataSource'];
}, { immediate: true });
const tr: SummaryTranslate = (key, named, fallback) => t(key, named, fallback);
// 摘要直接写出规则内容（如「必填 · 最大 32 字符」）；规则来源与改版前一致，只读不改写。
const validationRules = computed(() => validationEntries(props.field, tr));
const conditionRules = computed(() => {
  const rules = props.field.link_rules?.rules;
  return Array.isArray(rules) ? rules : [];
});
const summaryNone = () => t('formDesigner.propsPanel.summaryNone', '无');
const validationSummary = computed(() => validationRules.value.map((rule) => rule.text).filter(Boolean).join(' · ') || summaryNone());
const conditionTexts = computed(() => conditionRules.value.map((rule) => describeConditionRule(rule, tr)));
const conditionsSummary = computed(() => conditionTexts.value.join(t('formDesigner.propsPanel.condSeparator', '；')) || summaryNone());
const conditionsJson = computed(() => JSON.stringify(conditionRules.value, null, 2));
const displayRuleValue = (value: unknown) => typeof value === 'string' ? value : JSON.stringify(value);
const propsJson = computed(() => JSON.stringify(props.field.control_props ?? {}, null, 2));
const optionsJson = computed(() => JSON.stringify(props.field.options_source ?? {}, null, 2));

const onPropsJson = (value: string) => {
  try {
    patch({ control_props: value.trim() === '' ? null : JSON.parse(value) });
  } catch {
    ElMessage.warning(t('formDesigner.propsJsonInvalid', 'props JSON 不合法'));
  }
};
const onOptionsJson = (value: string) => {
  try {
    patch({ options_source: value.trim() === '' ? null : JSON.parse(value) });
  } catch {
    ElMessage.warning(t('formDesigner.optionsJsonInvalid', '选项来源 JSON 不合法'));
  }
};
</script>

<style scoped>
.props-folds {
  margin-top: 4px;
  border-bottom: 0;
}
.props-folds :deep(.el-collapse-item__header) {
  gap: 6px;
  min-width: 0;
  height: 36px;
  color: var(--el-text-color-regular);
  font-size: 12px;
}
.props-folds :deep(.el-collapse-item__title) {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.props-fold__label {
  flex: none;
}
.props-fold__summary {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--el-text-color-secondary);
  font-style: normal;
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.props-fold-form {
  min-width: 0;
}
.props-fold-form :deep(.el-form-item__content) {
  min-width: 0;
}
.props-fold-form :deep(.el-select),
.props-fold-form :deep(.el-textarea) {
  width: 100%;
}
.props-rule-list {
  display: grid;
  gap: 4px;
  margin: 0 0 6px;
  padding: 0;
  list-style: none;
  font-size: 12px;
}
.props-rule-list li {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  min-width: 0;
  overflow-wrap: anywhere;
  color: var(--el-text-color-regular);
}
.props-rule-list code {
  color: var(--el-color-primary);
}
.props-fold-hint {
  margin: 0;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.5;
}
.props-json-view {
  max-height: 200px;
  margin: 0 0 6px;
  padding: 8px;
  overflow: auto;
  border-radius: 6px;
  background: var(--el-fill-color-light);
  color: var(--el-text-color-regular);
  font: 11px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
</style>
