import { inject, provide, type InjectionKey } from 'vue';
import type { FormListActionCatalog, FormListActionRequest, FormListActionReply } from '@/api/formData';
import type { ListButtonContext } from './listButtonExecutor';
import type { FormListButton, FormListButtonLocation, FormListBuiltinAction, FormSchemaCondition } from '../schema/types';
import { evaluateCondition, type Condition } from './conditionEvaluator';
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
export const listButtonLabels: Record<FormListBuiltinAction, string> = { create: '新增', edit: '编辑', detail: '详情', delete: '删除', batchDelete: '批量删除', import: '导入', export: '导出', recycle: '回收站', restore: '恢复', destroy: '永久删除', addChild: '加子级' };
export const listButtonKeys: Record<FormListButtonLocation, FormListBuiltinAction[]> = { toolbar: ['create', 'export'], row: ['edit', 'detail', 'delete'], categoryToolbar: ['create'], categoryNode: ['addChild', 'edit', 'delete'] };
export function defaultListButtons(location: FormListButtonLocation): FormListButton[] {
  return listButtonKeys[location].map(key => ({ id: key.toLowerCase(), label: listButtonLabels[key], color: key === 'delete' ? 'danger' : 'primary', action: { type: 'builtin', key } }));
}
export function listActionKey(button: FormListButton): string {
  return button.action.type === 'builtin' ? button.action.key : button.action.type === 'form' ? 'edit' : button.action.type;
}
interface StateOptions { resource?: (button: FormListButton) => boolean; handlers: ListButtonHandlers; allowed: (button: FormListButton) => boolean; registered?: (button: FormListButton) => boolean; values?: Record<string, unknown>; fields?: readonly string[] }
export function listButtonState(button: FormListButton, options: StateOptions) {
  const state = { visible: !button.hidden, disabled: Boolean(button.disabled), reason: button.disabledReason ?? '' };
  let budget = 100;
  const validate = (condition: FormSchemaCondition, depth = 0): void => {
    if (--budget < 0 || depth > 8) throw Error('条件复杂度超限');
    if (condition.op === 'and' || condition.op === 'or') {
      if (!condition.conditions?.length || condition.conditions.length > 20) throw Error('条件组无效');
      condition.conditions.forEach(child => validate(child, depth + 1));
    } else if (condition.op === 'not') {
      if (!condition.condition) throw Error('条件无效');
      validate(condition.condition, depth + 1);
    } else if (!['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'notIn', 'contains', 'startsWith', 'endsWith', 'empty', 'notEmpty'].includes(condition.op)
      || !condition.field || !/^[a-z][a-z0-9_]*$/.test(condition.field) || ['constructor', 'prototype', '__proto__'].includes(condition.field) || !options.fields?.includes(condition.field)) throw Error('条件引用不可读字段或不支持的运算');
  };
  try {
    if (button.action.type !== 'registered' && !options.resource?.(button) && Object.keys(button.params ?? {}).length) throw Error('参数绑定尚未接通安全宿主适配，不可执行或发布');
    for (const condition of [button.visibleWhen, button.disabledWhen]) if (condition) validate(condition);
    if (button.visibleWhen) state.visible &&= evaluateCondition(button.visibleWhen as Condition, options.values ?? {});
    if (button.disabledWhen) state.disabled ||= evaluateCondition(button.disabledWhen as Condition, options.values ?? {});
    if (!options.allowed(button)) state.visible = false;
    if (button.action.type === 'registered' ? !options.registered?.(button) : ['navigate', 'external', 'download', 'copy'].includes(button.action.type) ? !options.resource?.(button) : !Object.hasOwn(options.handlers, listActionKey(button)) || !options.handlers[listActionKey(button)]) {
      state.disabled = true; state.reason = '该动作尚未接通安全宿主适配，不可执行或发布';
    }
  } catch (error) { state.disabled = true; state.reason = error instanceof Error ? error.message : '配置无效'; }
  return state;
}
