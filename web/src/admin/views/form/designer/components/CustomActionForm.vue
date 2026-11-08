<template>
  <el-form label-position="top" size="small" class="custom-action-form" @submit.prevent>
    <el-alert v-if="errors.form" :title="errors.form" type="error" :closable="false" class="custom-action-form__alert" />
    <el-form-item :label="t('formDesigner.customAction.label', '名称')">
      <el-input :model-value="label" :aria-label="t('formDesigner.customAction.label', '名称')" maxlength="50" :placeholder="t('formDesigner.customAction.labelPlaceholder', '如：审核通过')" @update:model-value="changeLabel" />
      <div v-if="errors.label" class="custom-action-form__error" role="alert">{{ errors.label }}</div>
    </el-form-item>
    <el-form-item :label="t('formDesigner.customAction.key', '标识')">
      <el-input :model-value="key" :aria-label="t('formDesigner.customAction.key', '标识')" :readonly="editing" maxlength="41" @update:model-value="changeKey" />
      <div v-if="errors.key" class="custom-action-form__error" role="alert">{{ errors.key }}</div>
      <div class="custom-action-form__hint">
        <template v-if="editing">{{ t('formDesigner.customAction.keyLocked', '标识创建后不可修改，避免已绑定的按钮失效。') }}</template>
        <template v-else>{{ t('formDesigner.customAction.keyHint', '小写字母开头，仅含小写字母、数字和下划线；由名称自动建议，可改。') }}</template>
        <span v-if="key" class="custom-action-form__code">{{ t('formDesigner.customAction.registryKey', '注册键') }} {{ registryKey }} · {{ t('formDesigner.customAction.permission', '权限') }} {{ permission }}</span>
      </div>
    </el-form-item>
    <el-form-item :label="t('formDesigner.customAction.locations', '位置')">
      <el-checkbox-group v-model="locations" :aria-label="t('formDesigner.customAction.locations', '位置')">
        <el-checkbox v-for="option in locationOptions" :key="option.value" :value="option.value" :data-location-option="option.value">{{ option.label }}</el-checkbox>
      </el-checkbox-group>
      <div v-if="errors.locations" class="custom-action-form__error" role="alert">{{ errors.locations }}</div>
    </el-form-item>
    <div class="custom-action-form__switches">
      <label class="custom-action-form__switch">
        <span>{{ t('formDesigner.customAction.batch', '批量') }}</span>
        <el-switch data-custom-batch :model-value="batch" disabled :aria-label="t('formDesigner.customAction.batch', '批量')" />
      </label>
      <label class="custom-action-form__switch">
        <span>{{ t('formDesigner.customAction.requiresConfirmation', '二次确认') }}</span>
        <el-switch v-model="requiresConfirmation" data-custom-confirm :aria-label="t('formDesigner.customAction.requiresConfirmation', '二次确认')" />
      </label>
    </div>
    <div class="custom-action-form__hint custom-action-form__hint--block">{{ t('formDesigner.customAction.batchHint', '顶部操作总是批量作用于已选记录；仅行操作时作用于当前记录。') }}</div>
    <el-form-item :label="t('formDesigner.customAction.effect', '读写')">
      <el-radio-group v-model="effect">
        <el-radio value="write">{{ t('formDesigner.customAction.effectWrite', '写入') }}</el-radio>
        <el-radio value="read">{{ t('formDesigner.customAction.effectRead', '只读') }}</el-radio>
      </el-radio-group>
    </el-form-item>
    <el-form-item :label="t('formDesigner.customAction.parameters', '参数')">
      <div class="custom-action-form__params">
        <div v-for="(parameter, index) in parameters" :key="index" class="custom-action-form__param" data-custom-parameter>
          <div class="custom-action-form__param-row">
            <el-input v-model="parameter.name" :aria-label="t('formDesigner.customAction.parameterName', { n: index + 1 }, '参数 {n} 名称')" maxlength="40" :placeholder="t('formDesigner.customAction.parameterNamePlaceholder', '参数名，如 reason')" />
            <el-select v-model="parameter.type" :aria-label="t('formDesigner.customAction.parameterType', { n: index + 1 }, '参数 {n} 类型')">
              <el-option v-for="type in parameterTypes" :key="type" :value="type" :label="type" />
            </el-select>
            <el-button @click="parameters.splice(index, 1)">{{ t('formDesigner.customAction.removeParameter', '移除') }}</el-button>
          </div>
          <div v-if="errors[`parameter${index}`]" class="custom-action-form__error" role="alert">{{ errors[`parameter${index}`] }}</div>
        </div>
        <el-button :disabled="parameters.length >= maxParameters" @click="addParameter"><i class="i-ep-plus" />{{ t('formDesigner.customAction.addParameter', '添加参数') }}</el-button>
        <span class="custom-action-form__hint">{{ t('formDesigner.customAction.parametersHint', { n: maxParameters }, '最多 {n} 个；按钮上通过参数绑定取值。行操作会自动收到当前记录 ID，顶部操作收到已选记录 ID。') }}</span>
      </div>
    </el-form-item>
    <div class="custom-action-form__footer">
      <el-button @click="emit('cancel')">{{ t('common.cancel', '取消') }}</el-button>
      <el-button type="primary" @click="submit">{{ t('common.save', '保存') }}</el-button>
    </div>
  </el-form>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormListCustomAction, FormListCustomActionLocation, FormListCustomActionParameterType } from '../../schema/types';
import { CUSTOM_ACTION_KEY_PATTERN, CUSTOM_ACTION_LOCATIONS, CUSTOM_ACTION_MAX_ACTIONS, CUSTOM_ACTION_MAX_PARAMETERS, CUSTOM_ACTION_MAX_REGISTRY_KEY, CUSTOM_ACTION_PARAMETER_PATTERN, CUSTOM_ACTION_PARAMETER_TYPES, customActionMethod, customActionPermission, customActionRegistryKey, suggestCustomActionKey } from './customActions';

/** 新建 / 编辑一个列表自定义动作；校验规则与后端 FormSchemaValidator::validateCustomActions 一致。 */
const props = withDefaults(defineProps<{ action?: FormListCustomAction; existing: readonly FormListCustomAction[]; entity: string; defaultLocations?: FormListCustomActionLocation[] }>(), { defaultLocations: () => ['row'] });
const emit = defineEmits<{ submit: [action: FormListCustomAction]; cancel: [] }>();
const { t } = useI18n();
const editing = computed(() => Boolean(props.action));
const others = computed(() => props.existing.filter(item => item.key !== props.action?.key));
const label = ref(props.action?.label ?? '');
const key = ref(props.action?.key ?? '');
const locations = ref<FormListCustomActionLocation[]>([...(props.action?.locations ?? props.defaultLocations)]);
const requiresConfirmation = ref(props.action?.requiresConfirmation ?? true);
const effect = ref<'write' | 'read'>(props.action?.effect ?? 'write');
const parameters = ref<Array<{ name: string; type: FormListCustomActionParameterType }>>((props.action?.parameters ?? []).map(parameter => ({ ...parameter })));
const errors = ref<Record<string, string>>({});
const maxParameters = CUSTOM_ACTION_MAX_PARAMETERS;
const parameterTypes = CUSTOM_ACTION_PARAMETER_TYPES;
// 顶部操作的注册动作总是作用于已选记录：批量由位置决定（含顶部 ⇔ 批量），界面只展示不允许单独切换。
const batch = computed(() => locations.value.includes('toolbar'));
const registryKey = computed(() => customActionRegistryKey(props.entity, key.value));
const permission = computed(() => customActionPermission(props.entity, key.value));
const locationOptions = computed(() => [{ value: 'row' as const, label: t('formDesigner.listPanel.rowActions', '行操作') }, { value: 'toolbar' as const, label: t('formDesigner.listPanel.toolbarActions', '顶部操作') }]);
let keyTouched = editing.value;
function changeLabel(value: string) {
  label.value = value;
  if (!keyTouched) key.value = value.trim() ? suggestCustomActionKey(value, others.value.map(item => item.key)) : '';
}
function changeKey(value: string) { keyTouched = true; key.value = value.trim(); }
function addParameter() {
  if (parameters.value.length >= maxParameters) return;
  parameters.value.push({ name: '', type: 'string' });
}
function validate(): Record<string, string> {
  const result: Record<string, string> = {};
  if (!editing.value && props.existing.length >= CUSTOM_ACTION_MAX_ACTIONS) result.form = t('formDesigner.customAction.tooMany', { n: CUSTOM_ACTION_MAX_ACTIONS }, '自定义动作最多 {n} 个');
  const name = label.value.trim();
  if (!name) result.label = t('formDesigner.customAction.labelRequired', '请填写动作名称');
  else if ([...label.value].length > 50 || /[\x00-\x1f\x7f]/.test(label.value)) result.label = t('formDesigner.customAction.labelInvalid', '动作名称须为 1–50 个字符');
  const collision = others.value.find(item => customActionMethod(item.key).toLowerCase() === customActionMethod(key.value).toLowerCase());
  if (!CUSTOM_ACTION_KEY_PATTERN.test(key.value)) result.key = t('formDesigner.customAction.keyInvalid', '标识须以小写字母开头，仅含小写字母、数字和下划线，最多 41 个字符');
  else if (others.value.some(item => item.key === key.value)) result.key = t('formDesigner.customAction.keyDuplicate', '标识已存在');
  else if (collision) result.key = t('formDesigner.customAction.methodDuplicate', { key: collision.key }, '标识生成的方法名与「{key}」重复');
  else if (registryKey.value.length > CUSTOM_ACTION_MAX_REGISTRY_KEY) result.key = t('formDesigner.customAction.registryKeyTooLong', { n: CUSTOM_ACTION_MAX_REGISTRY_KEY }, '注册键超过 {n} 个字符，请缩短标识');
  if (!locations.value.length) result.locations = t('formDesigner.customAction.locationsRequired', '至少选择一个位置');
  const seen = new Set<string>();
  parameters.value.forEach((parameter, index) => {
    if (!CUSTOM_ACTION_PARAMETER_PATTERN.test(parameter.name)) result[`parameter${index}`] = t('formDesigner.customAction.parameterInvalid', '参数名须以小写字母开头，仅含小写字母、数字和下划线');
    else if (seen.has(parameter.name)) result[`parameter${index}`] = t('formDesigner.customAction.parameterDuplicate', { name: parameter.name }, '参数名重复：{name}');
    else if (!(CUSTOM_ACTION_PARAMETER_TYPES as readonly string[]).includes(parameter.type)) result[`parameter${index}`] = t('formDesigner.customAction.parameterTypeInvalid', '参数类型仅支持 string / integer / number / boolean');
    seen.add(parameter.name);
  });
  return result;
}
function submit() {
  errors.value = validate();
  if (Object.keys(errors.value).length) return;
  emit('submit', {
    key: key.value,
    label: label.value.trim(),
    locations: CUSTOM_ACTION_LOCATIONS.filter(location => locations.value.includes(location)),
    batch: batch.value,
    requiresConfirmation: requiresConfirmation.value,
    effect: effect.value,
    parameters: parameters.value.map(parameter => ({ name: parameter.name, type: parameter.type }))
  });
}
defineExpose({ submit });
</script>
<style scoped>
.custom-action-form :deep(.el-form-item) { margin-bottom: 14px; }
.custom-action-form :deep(.el-select) { width: 100%; }
.custom-action-form__alert { margin-bottom: 10px; }
.custom-action-form__hint { display: block; margin-top: 4px; font-size: 12px; line-height: 1.5; color: var(--el-text-color-secondary); }
.custom-action-form__hint--block { margin: -6px 0 12px; }
.custom-action-form__code { display: block; font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); color: var(--el-text-color-regular); word-break: break-all; }
.custom-action-form__switches { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-bottom: 10px; }
.custom-action-form__switch { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 10px; border: 1px solid var(--el-border-color-lighter); border-radius: 8px; font-size: 12.5px; color: var(--el-text-color-regular); background: var(--el-fill-color-blank); }
.custom-action-form__params { display: grid; gap: 8px; width: 100%; }
.custom-action-form__param { display: grid; gap: 2px; }
.custom-action-form__param-row { display: grid; grid-template-columns: minmax(0, 1fr) 112px auto; gap: 6px; align-items: center; }
.custom-action-form__param-row :deep(.el-button + .el-button) { margin-left: 0; }
.custom-action-form__error { width: 100%; margin-top: 2px; font-size: 12px; line-height: 1.4; color: var(--el-color-danger); }
.custom-action-form__params > .el-button { justify-self: start; }
.custom-action-form__footer { display: flex; justify-content: flex-end; gap: 8px; padding-top: 4px; }
.custom-action-form__footer :deep(.el-button + .el-button) { margin-left: 0; }
@media (max-width: 480px) {
  .custom-action-form__switches { grid-template-columns: minmax(0, 1fr); }
  .custom-action-form__param-row { grid-template-columns: minmax(0, 1fr) 96px; }
  .custom-action-form__param-row > .el-button { grid-column: 1 / -1; justify-self: start; }
}
</style>
