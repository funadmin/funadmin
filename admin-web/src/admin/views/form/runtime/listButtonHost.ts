import { inject, provide, type InjectionKey } from 'vue';
import { i18n } from '@/locales';
import type { FormListActionCatalog, FormListActionRequest, FormListActionReply } from '@/api/formData';
import type { ListButtonContext } from './listButtonExecutor';
import type { FormListButton, FormListButtonLocation, FormListBuiltinAction, FormSchemaCondition } from '../schema/types';
import { evaluateCondition, type Condition } from './conditionEvaluator';

const t = (key: string, fallback: string): string => i18n.global.t(key, fallback);

export interface ListButtonAdapterDeclaration {
  readonly catalogPermission: string;
  readonly executePermission: string;
  readonly formKey?: string;
  readonly schemaHash?: string;
  readonly fieldMap?: Readonly<Record<string, string>>;
}
export interface ListButtonAdapter {
  readonly declaration: ListButtonAdapterDeclaration;
  readonly api: {
    listActions: (key: string, location: FormListButtonLocation, signal?: AbortSignal) => Promise<FormListActionCatalog>;
    listAction: (key: string, request: FormListActionRequest) => Promise<FormListActionReply>;
  };
}
export const listButtonAdapterKey: InjectionKey<ListButtonAdapter> = Symbol('list-button-adapter');
export const provideListButtonAdapter = (adapter: ListButtonAdapter): void => provide(listButtonAdapterKey, adapter);
export const useListButtonAdapter = (): ListButtonAdapter | undefined => inject(listButtonAdapterKey, undefined);
export function listButtonAdapterAllowed(adapter: ListButtonAdapter | undefined, permission: (code: string) => boolean, context?: ListButtonContext): boolean {
  if (!adapter) return false;
  const declaration = adapter.declaration;
  return Boolean(declaration.catalogPermission && declaration.executePermission
    && permission(declaration.catalogPermission) && permission(declaration.executePermission)
    && (!declaration.formKey || declaration.formKey === context?.formKey)
    && (!declaration.schemaHash || declaration.schemaHash === context?.schemaHash));
}
export type ListButtonHandlers = Partial<Record<string, (row?: Record<string, unknown>, input?: Record<string, unknown>) => unknown | Promise<unknown>>>;
export const listButtonLabels: Record<FormListBuiltinAction, string> = {
  create: t('common.add', '新增'),
  edit: t('common.edit', '编辑'),
  detail: t('common.detail', '详情'),
  delete: t('common.remove', '删除'),
  batchDelete: t('common.batchRemove', '批量删除'),
  import: t('common.import', '导入'),
  export: t('common.export', '导出'),
  recycle: t('formData.recycle', '回收站'),
  normal: t('formData.normalList', '正常列表'),
  restore: t('formData.restore', '恢复'),
  destroy: t('formData.destroy', '永久删除'),
  addChild: t('formData.addChild', '加子级'),
  copyCreate: t('formData.copyCreate', '复制新增')
};
export const listButtonKeys: Record<FormListButtonLocation, FormListBuiltinAction[]> = { toolbar: ['create', 'import', 'export', 'batchDelete', 'recycle', 'normal'], row: ['edit', 'detail', 'delete', 'copyCreate', 'restore', 'destroy'], categoryToolbar: ['create'], categoryNode: ['addChild', 'edit', 'delete'] };
export function defaultListButtons(location: FormListButtonLocation): FormListButton[] {
  const buttons: FormListButton[] = listButtonKeys[location].map(key => ({ id: key.toLowerCase(), label: listButtonLabels[key], color: (['delete', 'batchDelete', 'destroy'].includes(key) ? 'danger' : key === 'restore' ? 'success' : 'primary') as FormListButton['color'], action: { type: 'builtin', key } }));
  // 刷新为宿主级动作（非内置写动作），工具栏默认附带。
  if (location === 'toolbar') buttons.push({ id: 'refresh', label: t('common.refresh', '刷新'), color: 'default', action: { type: 'refresh' } });
  return buttons;
}
export function listActionKey(button: FormListButton): string {
  return button.action.type === 'builtin' ? button.action.key : button.action.type === 'form' ? 'edit' : button.action.type;
}
interface StateOptions { resource?: (button: FormListButton) => boolean; handlers: ListButtonHandlers; allowed: (button: FormListButton) => boolean; registered?: (button: FormListButton) => boolean; values?: Record<string, unknown>; fields?: readonly string[] }
export function listButtonState(button: FormListButton, options: StateOptions) {
  const state = { visible: !button.hidden, disabled: Boolean(button.disabled), reason: button.disabledReason ?? '' };
  let budget = 100;
  const validate = (condition: FormSchemaCondition, depth = 0): void => {
    if (--budget < 0 || depth > 8) throw Error(t('formDesigner.conditionTooComplex', '条件复杂度超限'));
    if (condition.op === 'and' || condition.op === 'or') {
      if (!condition.conditions?.length || condition.conditions.length > 20) throw Error(t('formDesigner.conditionGroupEmpty', '条件组无效'));
      condition.conditions.forEach(child => validate(child, depth + 1));
    } else if (condition.op === 'not') {
      if (!condition.condition) throw Error(t('formDesigner.conditionMalformed', '条件无效'));
      validate(condition.condition, depth + 1);
    } else if (!['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'notIn', 'contains', 'startsWith', 'endsWith', 'empty', 'notEmpty'].includes(condition.op)
      || !condition.field || !/^[a-z][a-z0-9_]*$/.test(condition.field) || ['constructor', 'prototype', '__proto__'].includes(condition.field) || !options.fields?.includes(condition.field)) throw Error(t('formDesigner.conditionFieldUnsupported', '条件引用不可读字段或不支持的运算'));
  };
  try {
    if (button.action.type !== 'registered' && !options.resource?.(button) && Object.keys(button.params ?? {}).length) throw Error(t('formDesigner.paramsHostNotReady', '参数绑定尚未接通安全宿主适配，不可执行或发布'));
    for (const condition of [button.visibleWhen, button.disabledWhen]) if (condition) validate(condition);
    if (button.visibleWhen) state.visible &&= evaluateCondition(button.visibleWhen as Condition, options.values ?? {});
    if (button.disabledWhen) state.disabled ||= evaluateCondition(button.disabledWhen as Condition, options.values ?? {});
    if (!options.allowed(button)) state.visible = false;
    if (button.action.type === 'registered' ? !options.registered?.(button) : ['navigate', 'external', 'download', 'copy'].includes(button.action.type) ? !options.resource?.(button) : !Object.hasOwn(options.handlers, listActionKey(button)) || !options.handlers[listActionKey(button)]) {
      state.disabled = true; state.reason = t('formDesigner.actionHostNotReady', '该动作尚未接通安全宿主适配，不可执行或发布');
    }
  } catch (error) { state.disabled = true; state.reason = error instanceof Error ? error.message : t('formDesigner.configInvalid', '配置无效'); }
  return state;
}
