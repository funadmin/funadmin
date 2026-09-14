import { resolveListButtons } from '@/views/form/schema/listButtons';
import type { FormListConfiguration, FormListButton } from '@/views/form/schema/types';

export interface PageCondition { field: string; op: 'eq' | 'neq'; value: unknown }
export interface PageAction extends Omit<FormListButton, 'visibleWhen' | 'disabledWhen'> {
  action: { type: 'registered'; key: string; capabilityVersion: string };
  visibleWhen?: PageCondition; disabledWhen?: PageCondition; activeWhen?: PageCondition;
  inactiveColor?: FormListButton['color']; selectionCount?: boolean;
}
export interface PageColumn {
  key: string; label: string; prop?: string; type?: 'selection'; width?: number; minWidth?: number;
  align?: 'left' | 'center' | 'right'; fixed?: 'left' | 'right'; slot?: string; formatter?: string; visibleWhen?: PageCondition;
}
export interface PageSearch { field: string; label: string; type: 'input' | 'select'; placeholder?: string; options?: { label: string; value: string | number }[] }
export interface PageSchema {
  list?: Pick<FormListConfiguration, 'category' | 'leftTree' | 'buttons'>;
  pageSchemaVersion: 1; key: string; search: PageSearch[]; columns: PageColumn[];
  toolbar: PageAction[]; rowActions: PageAction[]; pagination: { pageSize: number; pageSizes: number[] };
}
export interface PageHandler { version: string; permission?: string; run: (row?: any) => unknown; available?: (row?: any) => boolean }
export interface PageContext { values: Record<string, unknown>; permissions: string[]; handlers: Record<string, PageHandler>; row?: any }
const owns = (value: object, key: string) => Object.prototype.hasOwnProperty.call(value, key);
const identifier = (value: unknown): value is string => typeof value === 'string' && /^[a-zA-Z][a-zA-Z0-9_]{0,63}$/.test(value) && !['constructor', 'prototype', '__proto__'].includes(value);
const fail = (): never => { throw new Error('PAGE_SCHEMA_INVALID'); };
const record = (value: unknown, keys: string[]): Record<string, any> => {
  if (!value || typeof value !== 'object' || Array.isArray(value) || Object.keys(value).some(k => !keys.includes(k))) fail();
  return value as Record<string, any>;
};
const condition = (value: unknown) => {
  const c = record(value, ['field', 'op', 'value']);
  if (!identifier(c.field) || !['eq', 'neq'].includes(c.op) || !owns(c, 'value') || (c.value !== null && !['string', 'number', 'boolean'].includes(typeof c.value))) fail();
};
/** 仅接收闭合的数据协议，绝不把服务端对象展开为组件事件或请求参数。 */
export function parsePageSchema(value: unknown): PageSchema {
  const p = record(value, ['pageSchemaVersion', 'key', 'search', 'columns', 'toolbar', 'rowActions', 'pagination', 'list']);
  if (p.pageSchemaVersion !== 1 || !identifier(p.key)) fail();
  for (const section of ['search', 'columns', 'toolbar', 'rowActions']) {
    const items = p[section]; if (!Array.isArray(items) || items.length > 100) fail();
    const ids = new Set<string>();
    for (const raw of items) {
      const keys = section === 'search' ? ['field', 'label', 'type', 'placeholder', 'options'] : section === 'columns'
        ? ['key', 'label', 'prop', 'type', 'width', 'minWidth', 'align', 'fixed', 'slot', 'formatter', 'visibleWhen']
        : ['id', 'label', 'icon', 'color', 'permission', 'hidden', 'disabled', 'visibleWhen', 'disabledWhen', 'activeWhen', 'inactiveColor', 'selectionCount', 'action'];
      const item = record(raw, keys); const id = item[section === 'search' ? 'field' : section === 'columns' ? 'key' : 'id'];
      if (!identifier(id) || ids.has(id) || typeof item.label !== 'string') fail(); ids.add(id);
      for (const k of ['visibleWhen', 'disabledWhen', 'activeWhen']) if (owns(item, k)) condition(item[k]);
      if (section === 'search') {
        if (!['input', 'select'].includes(item.type)) fail();
        if (owns(item, 'placeholder') && typeof item.placeholder !== 'string') fail();
        if (owns(item, 'options')) {
          if (!Array.isArray(item.options)) fail();
          for (const option of item.options) { const o = record(option, ['label', 'value']); if (typeof o.label !== 'string' || !['string', 'number'].includes(typeof o.value)) fail(); }
        }
      } else if (section === 'columns') {
        for (const k of ['prop', 'slot', 'formatter']) if (owns(item, k) && !identifier(item[k])) fail();
        for (const k of ['width', 'minWidth']) if (owns(item, k) && (!Number.isInteger(item[k]) || item[k] < 1 || item[k] > 2000)) fail();
        if (owns(item, 'type') && item.type !== 'selection') fail();
        if (owns(item, 'align') && !['left', 'center', 'right'].includes(item.align)) fail();
        if (owns(item, 'fixed') && !['left', 'right'].includes(item.fixed)) fail();
      } else {
        const a = record(item.action, ['type', 'key', 'capabilityVersion']);
        if (a.type !== 'registered' || !identifier(a.key) || typeof a.capabilityVersion !== 'string') fail();
        for (const k of ['hidden', 'disabled', 'selectionCount']) if (owns(item, k) && typeof item[k] !== 'boolean') fail();
        for (const k of ['color', 'inactiveColor']) if (owns(item, k) && !['default', 'primary', 'success', 'warning', 'danger', 'info'].includes(item[k])) fail();
        if (owns(item, 'permission') && (typeof item.permission !== 'string' || !item.permission)) fail();
        if (owns(item, 'icon') && (typeof item.icon !== 'string' || !/^i-[a-z0-9-]+$/.test(item.icon))) fail();
      }
    }
  }
  if (owns(p, 'list')) {
    const list = record(p.list, ['category', 'leftTree', 'buttons']);
    if (owns(list, 'category')) {
      const c = record(list.category, ['enabled', 'field']);
      if (typeof c.enabled !== 'boolean' || (owns(c, 'field') && !identifier(c.field))) fail();
      if (c.enabled && !p.search.some((s: PageSearch) => s.field === c.field && s.type === 'select' && Array.isArray(s.options))) fail();
    }
    if (owns(list, 'leftTree')) {
      const t = record(list.leftTree, ['enabled', 'source', 'mapping', 'selection', 'actions']);
      if (typeof t.enabled !== 'boolean') fail();
      const s = record(t.source ?? {}, ['type', 'module']);
      const m = record(t.mapping ?? {}, ['valueField', 'labelField', 'targetField', 'parentField', 'sortField']);
      const selection = record(t.selection ?? {}, ['mode', 'includeDescendants']);
      const actions = record(t.actions ?? {}, ['create', 'addChild', 'edit', 'delete']);
      if (Object.values(actions).some(v => typeof v !== 'boolean')) fail();
      if (owns(selection, 'mode') && !['single', 'multiple'].includes(selection.mode)) fail();
      if (owns(selection, 'includeDescendants') && typeof selection.includeDescendants !== 'boolean') fail();
      if (t.enabled) {
        if (!['current', 'module'].includes(s.type) || (s.type === 'module' && (typeof s.module !== 'string' || !/^[a-z][a-z0-9_]{0,63}$/.test(s.module)))) fail();
        for (const key of ['valueField', 'labelField', 'targetField']) if (!m[key]) fail();
        for (const [key, field] of Object.entries(m)) {
          if (field === '' && ['parentField', 'sortField'].includes(key)) continue;
          if (typeof field !== 'string' || !/^[a-z_][a-z0-9_]*$/.test(field) || ['constructor', 'prototype', '__proto__'].includes(field)) fail();
        }
        if (m.parentField && m.parentField === m.valueField) fail();
      }
    }
    if (owns(list, 'buttons')) {
      record(list.buttons, ['categoryToolbar', 'categoryNode']);
      for (const location of ['categoryToolbar', 'categoryNode'] as const) resolveListButtons(list, location, []);
    }
  }
  const pagination = record(p.pagination, ['pageSize', 'pageSizes']);
  if (!Array.isArray(pagination.pageSizes) || !pagination.pageSizes.length || pagination.pageSizes.some((n: unknown) => !Number.isInteger(n) || Number(n) < 1 || Number(n) > 1000) || !pagination.pageSizes.includes(pagination.pageSize)) fail();
  return JSON.parse(JSON.stringify(p)) as PageSchema;
}
export function matchesPageCondition(c: PageCondition | undefined, values: Record<string, unknown>): boolean {
  if (!c) return true;
  try { condition(c); } catch { return false; }
  if (!owns(values, c.field)) return false;
  return c.op === 'eq' ? values[c.field] === c.value : values[c.field] !== c.value;
}
export function actionState(a: PageAction, c: PageContext) {
  try {
    for (const value of [a.visibleWhen, a.disabledWhen]) if (value) condition(value);
  } catch { return { visible: false, disabled: true }; }
  const handler = identifier(a.action.key) && owns(c.handlers, a.action.key) ? c.handlers[a.action.key] : undefined;
  const permitted = (p?: string) => !p || c.permissions.some(v => v === '*' || v === '*:*:*' || v === p);
  const visible = a.action.type === 'registered' && !!handler && handler.version === a.action.capabilityVersion && !a.hidden
    && permitted(a.permission) && permitted(handler.permission) && matchesPageCondition(a.visibleWhen, c.values);
  const disabled = !!a.disabled || (!!a.disabledWhen && (!owns(c.values, a.disabledWhen.field) || matchesPageCondition(a.disabledWhen, c.values))) || (handler?.available ? !handler.available(c.row) : false);
  return { visible, disabled };
}
export async function executePageAction(a: PageAction, c: PageContext): Promise<void> {
  const state = actionState(a, c);
  if (state.visible && !state.disabled) await c.handlers[a.action.key]!.run(c.row);
}
