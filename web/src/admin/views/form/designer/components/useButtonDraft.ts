import { computed, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import type { FormListButton, FormListButtonLocation, FormListBuiltinAction, FormListButtonInteraction, FormListParameterBinding, FormListActionMetadata, FormListCustomAction, FormSchemaCondition } from '../../schema/types';
import type { ListResource } from '../../runtime/listResourceHost';
import { resolveListButtons } from '../../schema/listButtons';
import { listButtonLabels, listButtonKeys } from '../../runtime/listButtonHost';
import { customActionAppliesTo, customActionMetadata, customActionRegistryKey } from './customActions';

export interface ButtonDraftProps {
  location: FormListButtonLocation;
  fields?: string[];
  filterFields?: string[];
  categoryFields?: string[];
  resources?: Record<string, ListResource>;
  actions?: Record<string, FormListActionMetadata>;
  catalogError?: string;
  builtinKeys?: FormListBuiltinAction[];
  resourceEnabled?: boolean;
  /** 本模块 list.customActions 与其注册键使用的 entity；未生成的自定义动作以 Schema 定义作为元数据。 */
  customActions?: readonly FormListCustomAction[];
  customActionEntity?: string;
}
export interface ButtonActionOption { value: string; label: string; custom?: boolean; pending?: boolean }

/** 单个按钮草稿的编辑与校验逻辑（自原 ListButtonEditor 抽屉迁出，行为不变）。 */
export function useButtonDraft(props: ButtonDraftProps) {
  const { t } = useI18n();
  const draft = ref<FormListButton>();
  const keys = computed(() => props.builtinKeys ?? listButtonKeys[props.location]);
  // 适用于当前位置的自定义动作：注册键 → 元数据；已生成（设计目录中存在）时以目录为准。
  const customEntries = computed(() => props.customActionEntity && props.resourceEnabled !== false ? (props.customActions ?? []).filter(action => customActionAppliesTo(action, props.location)).map(action => ({ action, registryKey: customActionRegistryKey(props.customActionEntity!, action.key) })) : []);
  const catalogActions = computed<Record<string, FormListActionMetadata>>(() => ({ ...Object.fromEntries(customEntries.value.map(entry => [entry.registryKey, customActionMetadata(props.customActionEntity!, entry.action)])), ...props.actions }));
  const customOptions = computed<ButtonActionOption[]>(() => customEntries.value.map(entry => ({ value: `registered:${entry.registryKey}`, label: entry.action.label, custom: true, pending: !props.actions?.[entry.registryKey] })));
  const actionOptions = computed<ButtonActionOption[]>(() => [
    ...keys.value.map(key => ({ value: key, label: listButtonLabels[key] })), { value: 'refresh', label: t('common.refresh', '刷新') },
    ...(props.location === 'row' && keys.value.includes('edit') ? [{ value: 'form', label: t('formDesigner.currentFormEdit', '当前业务编辑表单') }] : []),
    ...(props.resourceEnabled === false ? [] : [
      ...(['row', 'categoryNode'].includes(props.location) ? [{ value: 'copy', label: t('formDesigner.copyFieldText', '复制字段文本') }] : []),
      ...(props.location === 'toolbar' && keys.value.includes('export') ? [{ value: 'download', label: t('formDesigner.authorizedExport', '授权导出下载') }] : []),
      ...Object.entries(props.resources ?? {}).map(([key, item]) => ({ value: `${item.type}:${key}`, label: item.type === 'navigate' ? t('formDesigner.navigateResource', { key }, '站内导航 · {key}') : t('formDesigner.externalResource', { key }, '受控外链 · {key}') })),
      ...Object.entries(props.actions ?? {}).filter(([key]) => !customEntries.value.some(entry => entry.registryKey === key)).filter(([, item]) => item.locations.includes(props.location) && item.targets.includes({ row: 'record', toolbar: 'selection', categoryNode: 'category', categoryToolbar: 'none' }[props.location]) && (props.location !== 'toolbar' || item.batch) && item.parameters && item.parameterTypes && item.resultContract === 'json').map(([key]) => ({ value: `registered:${key}`, label: t('formDesigner.registeredActionLabel', { key }, '注册动作 · {key}') })),
      ...customOptions.value
    ])
  ]);
  const conditionKeys = computed(() => [{ key: 'visibleWhen', label: t('formDesigner.visibleWhen', '显示条件') }, { key: 'disabledWhen', label: t('formDesigner.disabledWhen', '禁用条件') }] as const);
  function setCondition(key: 'visibleWhen' | 'disabledWhen', field: string) { if (!draft.value) return; if (!field) delete draft.value[key]; else draft.value[key] = { field, op: 'eq', value: '' }; }
  const actionKey = (button: FormListButton) => button.action.type === 'builtin' ? button.action.key : ['registered', 'navigate', 'external'].includes(button.action.type) && 'key' in button.action ? `${button.action.type}:${button.action.key}` : button.action.type;
  const actionValue = computed(() => draft.value ? actionKey(draft.value) : '');
  const metadata = computed(() => draft.value?.action.type === 'registered' && Object.hasOwn(catalogActions.value, draft.value.action.key) ? catalogActions.value[draft.value.action.key] : undefined);
  const resourceMetadata = computed(() => draft.value && ['navigate', 'external'].includes(draft.value.action.type) && 'key' in draft.value.action ? props.resources?.[draft.value.action.key] : undefined);
  const unsupported = computed(() => !actionOptions.value.some(item => item.value === actionValue.value) || Boolean(draft.value && 'capabilityVersion' in draft.value.action && draft.value.action.capabilityVersion !== (metadata.value ?? resourceMetadata.value)?.capabilityVersion));
  const requiredParameters = computed(() => metadata.value?.parameters ?? resourceMetadata.value?.params ?? (draft.value?.action.type === 'copy' ? ['text'] : []));
  const parameterNames = computed(() => [...new Set([...requiredParameters.value, ...(resourceMetadata.value?.query ?? []), ...Object.keys(draft.value?.params ?? {})])]);
  const bindingSources = computed(() => [{ value: 'row', label: t('formDesigner.sourceRow', '当前记录') }, { value: 'selection', label: t('formDesigner.sourceSelection', '当前页选择 ID') }, { value: 'filter', label: t('formDesigner.sourceFilter', '当前筛选') }, { value: 'category', label: t('formDesigner.sourceCategory', '当前分类') }, { value: 'form', label: t('formDesigner.sourceForm', '交互表单') }, { value: 'literal', label: t('formDesigner.sourceLiteral', '常量') }] as const);
  const interactionTypeLabel = (type: string) => (({ none: t('formDesigner.interactionNone', '无'), confirm: t('formDesigner.interactionConfirm', '确认'), input: t('formDesigner.interactionInput', '单项输入'), form: t('formDesigner.interactionForm', '参数表单') }) as Record<string, string>)[type];
  function sourceAllowed(source: string) {
    if (draft.value?.action.type === 'copy') return source === (props.location === 'row' ? 'row' : 'category');
    if (source === 'row') return props.location === 'row';
    if (source === 'selection') return props.location === 'toolbar' && !resourceMetadata.value;
    if (source === 'category') return Boolean(props.categoryFields?.length) && props.location !== 'categoryToolbar';
    return true;
  }
  function bindingFields(source?: string): string[] { return source === 'selection' ? ['ids'] : source === 'form' ? (draft.value?.interaction?.fields ?? []).map(field => field.name) : source === 'category' ? props.categoryFields ?? [] : source === 'filter' ? props.filterFields ?? props.fields ?? [] : props.fields ?? []; }
  function setBindingSource(name: string, source: FormListParameterBinding['source']) {
    if (!draft.value || !sourceAllowed(source)) return;
    draft.value.params ??= {};
    draft.value.params[name] = source === 'literal' ? { source, value: '' } : source === 'selection' ? { source, field: 'ids' } : { source, field: '' };
  }
  function setBindingField(name: string, field: string) { const binding = draft.value?.params?.[name]; if (binding && binding.source !== 'literal') binding.field = field; }
  function literalType(name: string) { const binding = draft.value?.params?.[name]; return binding?.source === 'literal' ? binding.value === null ? 'null' : typeof binding.value : 'string'; }
  function setLiteralType(name: string, type: string) { if (draft.value?.params) draft.value.params[name] = { source: 'literal', value: type === 'null' ? null : type === 'number' ? 0 : type === 'boolean' ? false : '' }; }
  function setLiteral(name: string, value: string) { const binding = draft.value?.params?.[name]; if (binding?.source !== 'literal') return; const type = literalType(name); binding.value = type === 'number' ? Number(value) : type === 'boolean' ? value === 'true' : type === 'null' ? null : value; }
  const actionDrafts = new Map<string, Pick<FormListButton, 'action' | 'params' | 'permission' | 'interaction'>>();
  const interactionDrafts = new Map<string, FormListButtonInteraction>();
  function reset() { actionDrafts.clear(); interactionDrafts.clear(); }
  function changeAction(key: string) {
    const button = draft.value; if (!button || !actionOptions.value.some(item => item.value === key)) return;
    actionDrafts.set(actionKey(button), JSON.parse(JSON.stringify({ action: button.action, params: button.params, permission: button.permission, interaction: button.interaction })));
    delete button.params; delete button.permission; delete button.interaction;
    const saved = actionDrafts.get(key); if (saved) { Object.assign(button, JSON.parse(JSON.stringify(saved))); return; }
    const [type, name] = key.split(':');
    if (type === 'registered' || type === 'navigate' || type === 'external') {
      const item = type === 'registered' ? catalogActions.value[name] : props.resources?.[name]; if (!item) return;
      button.action = { type, key: name, capabilityVersion: item.capabilityVersion }; button.permission = item.permission;
      const names = type === 'registered' ? (item as FormListActionMetadata).parameters : (item as ListResource).params;
      button.params = Object.fromEntries(names.map(name => [name, { source: 'literal', value: '' }]));
      if (type === 'registered') {
        const meta = item as FormListActionMetadata;
        button.interaction = { type: meta.parameters.length ? 'form' : meta.requiresConfirmation ? 'confirm' : 'none', presentation: 'dialog', ...(meta.parameters.length ? { fields: meta.parameters.filter(name => meta.parameterTypes[name] !== 'ids').map(name => ({ name, label: name, required: true, type: meta.parameterTypes[name] === 'boolean' ? 'switch' as const : ['integer', 'number'].includes(meta.parameterTypes[name]) ? 'number' as const : 'input' as const })) } : {}) };
        button.params = Object.fromEntries(meta.parameters.map(name => [name, meta.parameterTypes[name] === 'ids' && props.location === 'toolbar' ? { source: 'selection', field: 'ids' } : { source: 'form', field: name }]));
      }
    } else if (key === 'download') button.action = { type: 'download', key: 'export' };
    else if (key === 'copy') { button.action = { type: 'copy' }; button.params = { text: { source: props.location === 'row' ? 'row' : 'category', field: '' } }; }
    else button.action = ['refresh', 'form'].includes(key) ? { type: key as 'refresh' | 'form' } : { type: 'builtin', key: key as FormListBuiltinAction };
  }
  function changeInteraction(type: FormListButtonInteraction['type']) {
    if (!draft.value) return;
    const current = draft.value.interaction;
    if (current) interactionDrafts.set(`${actionValue.value}:${current.type}`, JSON.parse(JSON.stringify(current)));
    const saved = interactionDrafts.get(`${actionValue.value}:${type}`);
    draft.value.interaction = saved ? JSON.parse(JSON.stringify(saved)) : { type, presentation: current?.presentation ?? 'dialog', ...(['input', 'form'].includes(type) ? { fields: [{ name: 'reason', label: t('formDesigner.reason', '原因'), type: 'input', required: true }] } : {}) };
  }
  function addField() { const fields = draft.value?.interaction?.fields; if (!fields || fields.length >= 20) return; let index = 1; while (fields.some(field => field.name === `field${index}`)) index++; fields.push({ name: `field${index}`, label: t('formDesigner.paramDefaultLabel', '参数'), type: 'input' }); }
  function renameField(index: number, name: string) { const field = draft.value?.interaction?.fields?.[index]; if (!field) return; for (const binding of Object.values(draft.value?.params ?? {})) if (binding.source === 'form' && binding.field === field.name) binding.field = name; field.name = name; }
  function removeField(index: number) { const field = draft.value?.interaction?.fields?.[index]; if (!field) return; if (Object.values(draft.value?.params ?? {}).some(binding => binding.source === 'form' && binding.field === field.name)) { ElMessage.warning(t('formDesigner.modifyBindingFirst', '请先修改引用此字段的参数绑定')); return; } draft.value?.interaction?.fields?.splice(index, 1); }
  function setSelection(key: 'min' | 'max', value: number | undefined | null) {
    if (!draft.value) return;
    const selection = { ...draft.value.selection };
    if (value === undefined || value === null) delete selection[key]; else selection[key] = value;
    if (Object.keys(selection).length) draft.value.selection = selection; else delete draft.value.selection;
  }
  /** 校验草稿；通过时返回清理后的副本，失败时提示并返回 undefined。 */
  function validate(): FormListButton | undefined {
    if (!draft.value?.label.trim()) return;
    try { resolveListButtons({ buttons: { [props.location]: [draft.value] } }, props.location, []); }
    catch { ElMessage.error(t('formDesigner.selectionRangeError', '选择数量必须为 0–200 的整数，且最少选择不能超过最多选择')); return; }
    let budget = 100;
    const validCondition = (condition: FormSchemaCondition, depth = 0): boolean => {
      if (--budget < 0 || depth > 8) return false;
      if (['and', 'or'].includes(condition.op)) return Boolean(condition.conditions?.length && condition.conditions.length <= 20 && condition.conditions.every(child => validCondition(child, depth + 1)));
      if (condition.op === 'not') return Boolean(condition.condition && validCondition(condition.condition, depth + 1));
      return Boolean(condition.field && props.fields?.includes(condition.field));
    };
    if ([draft.value.visibleWhen, draft.value.disabledWhen].some(condition => condition && !validCondition(condition))) { ElMessage.error(t('formDesigner.conditionInvalid', '条件组不能为空，且必须引用可读字段')); return; }
    const inputs = draft.value.interaction?.fields ?? [];
    if (inputs.length > 20 || new Set(inputs.map(field => field.name)).size !== inputs.length || inputs.some(field => !/^[a-z][a-z0-9_.-]{0,63}$/.test(field.name) || field.name.split('.').some(part => ['constructor', 'prototype', '__proto__'].includes(part)) || !field.label.trim())) { ElMessage.error(t('formDesigner.inputFieldInvalid', '输入字段标识不合法或重复')); return; }
    if (!unsupported.value) {
      if (requiredParameters.value.some(name => !draft.value?.params?.[name])) { ElMessage.error(t('formDesigner.requiredParamsMissing', '请配置必需参数')); return; }
      for (const [name, binding] of Object.entries(draft.value.params ?? {})) {
        if (!sourceAllowed(binding.source) || (binding.source !== 'literal' && !bindingFields(binding.source).includes(binding.field)) || (binding.source === 'literal' && typeof binding.value === 'number' && !Number.isFinite(binding.value))) { ElMessage.error(t('formDesigner.paramBindingInvalid', { name }, '参数 {name} 的来源或字段无效')); return; }
      }
    }
    const button = JSON.parse(JSON.stringify(draft.value)) as FormListButton;
    if (button.success && !button.success.message?.trim()) delete button.success.message;
    for (const key of ['icon', 'tips', 'disabledReason', 'permission'] as const) if (!button[key]?.trim()) delete button[key];
    if (button.interaction) for (const key of ['title', 'message'] as const) if (!button.interaction[key]?.trim()) delete button.interaction[key];
    return button;
  }
  return { draft, actionValue, actionOptions, customOptions, metadata, resourceMetadata, unsupported, requiredParameters, parameterNames, bindingSources, conditionKeys, interactionTypeLabel, sourceAllowed, bindingFields, setBindingSource, setBindingField, literalType, setLiteralType, setLiteral, setCondition, changeAction, changeInteraction, addField, renameField, removeField, setSelection, validate, reset };
}
