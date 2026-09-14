import type { FormListConfiguration, FormListButton } from '@/views/form/schema/types';

export interface PageCondition { field: string; op: 'eq' | 'neq'; value: unknown }
export interface PageAction extends Omit<FormListButton, 'visibleWhen' | 'disabledWhen'> {
  action: { type: 'registered'; key: string; capabilityVersion: string };
  visibleWhen?: PageCondition; disabledWhen?: PageCondition; activeWhen?: PageCondition;
  inactiveColor?: FormListButton['color']; selectionCount?: boolean;
}
export interface PageColumn {
  key: string; label: string; prop?: string; type?: 'selection'; width?: number; minWidth?: number; sortable?: boolean;
  align?: 'left' | 'center' | 'right'; fixed?: 'left' | 'right'; slot?: string; formatter?: string; visibleWhen?: PageCondition;
}
export interface PageSearch { field: string; label: string; type: 'input' | 'select' | 'date' | 'range'; placeholder?: string; options?: { label: string; value: string | number }[] }
export interface PageSchema {
  primaryKey?: string;
  list?: Pick<FormListConfiguration, 'category' | 'leftTree' | 'buttons' | 'tree' | 'tools'>;
  pageSchemaVersion: 1; key: string; search: PageSearch[]; columns: PageColumn[];
  toolbar: PageAction[]; rowActions: PageAction[]; pagination: { pageSize: number; pageSizes: number[]; enabled?: boolean };
}
export interface PageHandler { version: string; interaction?: FormListButton['interaction']; permission?: string; run: (row?: any) => unknown; available?: (row?: any) => boolean }
export interface PageContext { version?: string | number; values: Record<string, unknown>; permissions: string[]; handlers: Record<string, PageHandler>; row?: any }
const owns = (value: object, key: string) => Object.prototype.hasOwnProperty.call(value, key);
const identifier = (value: unknown): value is string => typeof value === 'string' && /^[a-zA-Z][a-zA-Z0-9_]{0,63}$/.test(value) && !['constructor', 'prototype', '__proto__'].includes(value);
const fail = (): never => { throw new Error('PAGE_SCHEMA_INVALID'); };
const record = (value: unknown, keys: string[]): Record<string, any> => {
  if (!value || typeof value !== 'object' || (Array.isArray(value) && value.length > 0) || Object.keys(value).some(k => !keys.includes(k))) fail();
  return value as Record<string, any>;
};
const condition = (value: unknown) => {
  const c = record(value, ['field', 'op', 'value']);
  if (!identifier(c.field) || !['eq', 'neq'].includes(c.op) || !owns(c, 'value') || (c.value !== null && !['string', 'number', 'boolean'].includes(typeof c.value))) fail();
};
const bytes = (value: string) => new TextEncoder().encode(value).length;
const buttonIdentifier = (value: unknown) => typeof value === 'string' && /^[a-z][a-z0-9_.-]{0,63}$/.test(value) && !value.split('.').some(part => ['constructor', 'prototype', '__proto__'].includes(part));
const text = (value: unknown, max: number) => {
  if (typeof value !== 'string' || !value.replace(/^[ \t\n\r\0\x0b]+|[ \t\n\r\0\x0b]+$/g, '') || bytes(value) > max || /[\x00-\x1f\x7f]/.test(value)) fail();
};
const scalar = (value: unknown) => value === null || ['string', 'boolean'].includes(typeof value) || (typeof value === 'number' && Number.isFinite(value));
function buttonCondition(value: unknown, budget = { left: 100 }, depth = 0): void {
  if (depth > 8 || --budget.left < 0) fail();
  const c = record(value, ['op', 'field', 'value', 'conditions', 'condition']);
  if (['and', 'or'].includes(c.op)) {
    record(c, ['op', 'conditions']);
    if (!Array.isArray(c.conditions) || !c.conditions.length || c.conditions.length > 20) fail();
    c.conditions.forEach((child: unknown) => buttonCondition(child, budget, depth + 1));
  } else if (c.op === 'not') {
    record(c, ['op', 'condition']); buttonCondition(c.condition, budget, depth + 1);
  } else {
    record(c, ['op', 'field', 'value']);
    if (c.field !== 'id' || !['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'notIn', 'contains', 'startsWith', 'endsWith', 'empty', 'notEmpty'].includes(c.op)) fail();
    if (['empty', 'notEmpty'].includes(c.op)) return;
    if (!owns(c, 'value')) fail();
    const values = ['in', 'notIn'].includes(c.op) ? c.value : [c.value];
    if (!Array.isArray(values) || values.length > 100 || !values.every(scalar)) fail();
  }
}
function validateButtons(value: unknown, location: string): void {
  if (!Array.isArray(value) || value.length > 50) fail();
  const ids = new Set<string>();
  for (const raw of value as unknown[]) {
    const b = record(raw, ['id', 'label', 'icon', 'color', 'size', 'tips', 'placement', 'order', 'hidden', 'disabled', 'disabledReason', 'permission', 'visibleWhen', 'disabledWhen', 'interaction', 'action', 'params', 'success', 'selection']);
    if (!buttonIdentifier(b.id) || ids.has(b.id)) fail(); ids.add(b.id);
    text(b.label, 100);
    for (const key of ['tips', 'disabledReason', 'permission']) if (owns(b, key)) text(b[key], 500);
    if (owns(b, 'icon') && !buttonIdentifier(b.icon)) fail();
    for (const [key, allowed] of Object.entries({ color: ['default', 'primary', 'success', 'warning', 'danger', 'info'], size: ['small', 'default', 'large'], placement: ['inline', 'more'] })) if (owns(b, key) && !allowed.includes(b[key])) fail();
    for (const key of ['hidden', 'disabled']) if (owns(b, key) && typeof b[key] !== 'boolean') fail();
    if (owns(b, 'order') && (!Number.isInteger(b.order) || Math.abs(b.order) > 10000)) fail();
    if (owns(b, 'selection')) {
      if (location !== 'toolbar') fail();
      const s = record(b.selection, ['min', 'max']);
      if (!Object.keys(s).length || Object.values(s).some(n => !Number.isInteger(n) || n < 0 || n > 200) || (s.min ?? 0) > (s.max ?? 200)) fail();
    }
    for (const key of ['visibleWhen', 'disabledWhen']) if (owns(b, key)) buttonCondition(b[key]);
    const a = record(b.action, ['type', 'key', 'capabilityVersion']);
    if (!['builtin', 'registered', 'form', 'detail', 'navigate', 'external', 'copy', 'download', 'refresh'].includes(a.type)) fail();
    if (a.type === 'builtin') {
      const builtins: Record<string, string[]> = { toolbar: ['create', 'batchDelete', 'import', 'export', 'recycle'], row: ['detail', 'edit', 'delete', 'restore', 'destroy', 'copyCreate'], categoryToolbar: ['create'], categoryNode: ['addChild', 'edit', 'delete'] };
      if (!builtins[location]?.includes(a.key)) fail();
    } else if (['registered', 'navigate', 'external', 'download'].includes(a.type)) { if (!buttonIdentifier(a.key)) fail(); }
    else if (owns(a, 'key')) fail();
    if (['registered', 'navigate', 'external'].includes(a.type)) text(a.capabilityVersion, 64);
    else if (owns(a, 'capabilityVersion')) fail();
    const names = new Set<string>();
    if (owns(b, 'interaction')) {
      const i = record(b.interaction, ['type', 'presentation', 'title', 'message', 'fields']);
      if (!['none', 'confirm', 'input', 'form'].includes(i.type)) fail();
      if (owns(i, 'presentation') && !['dialog', 'drawer'].includes(i.presentation)) fail();
      for (const key of ['title', 'message']) if (owns(i, key)) text(i[key], 500);
      const fields = owns(i, 'fields') ? i.fields : [];
      if (!Array.isArray(fields) || fields.length > 20 || (['none', 'confirm'].includes(i.type) && fields.length) || (i.type === 'input' && fields.length !== 1)) fail();
      for (const rawField of fields) {
        const f = record(rawField, ['name', 'label', 'type', 'required', 'min', 'max', 'maxLength', 'options']);
        if (!buttonIdentifier(f.name) || names.has(f.name)) fail(); names.add(f.name); text(f.label, 100);
        if (!['input', 'textarea', 'number', 'select', 'switch', 'date'].includes(f.type)) fail();
        if (owns(f, 'required') && typeof f.required !== 'boolean') fail();
        for (const key of ['min', 'max', 'maxLength']) if (owns(f, key) && (typeof f[key] !== 'number' || !Number.isFinite(f[key]))) fail();
        if (f.min > f.max || (owns(f, 'maxLength') && (!Number.isInteger(f.maxLength) || f.maxLength < 1 || f.maxLength > 10000))) fail();
        if (owns(f, 'options')) {
          if (!Array.isArray(f.options) || f.options.length > 100) fail();
          for (const rawOption of f.options) {
            const o = record(rawOption, ['label', 'value']); text(o.label, 100);
            if (typeof o.value !== 'string' && !Number.isSafeInteger(o.value)) fail();
          }
        }
      }
    }
    if (owns(b, 'params')) {
      const params = record(b.params, Object.keys(b.params ?? {}));
      if (Object.keys(params).length > 30) fail();
      for (const [name, rawBinding] of Object.entries(params)) {
        if (!buttonIdentifier(name)) fail();
        const binding = record(rawBinding, ['source', 'field', 'value']);
        if (!['row', 'selection', 'filter', 'category', 'form', 'literal'].includes(binding.source)) fail();
        if (binding.source === 'literal') { if (!owns(binding, 'value') || owns(binding, 'field') || !scalar(binding.value)) fail(); }
        else {
          if (owns(binding, 'value')) fail();
          if (binding.source === 'selection' ? binding.field !== 'ids' : binding.source === 'form' ? !names.has(binding.field) : binding.field !== 'id') fail();
        }
      }
    }
    if (owns(b, 'success')) {
      const effects = record(b.success, ['message', 'refresh', 'clearSelection', 'close']);
      for (const [key, effect] of Object.entries(effects)) { if (key === 'message') text(effect, 500); else if (typeof effect !== 'boolean') fail(); }
    }
  }
}
/** 仅接收闭合的数据协议，绝不把服务端对象展开为组件事件或请求参数。 */
export function parsePageSchema(value: unknown): PageSchema {
  const p = record(value, ['pageSchemaVersion', 'key', 'search', 'columns', 'toolbar', 'rowActions', 'pagination', 'list', 'primaryKey']);
  if (p.pageSchemaVersion !== 1 || !identifier(p.key)) fail();
  if (owns(p, 'primaryKey') && !identifier(p.primaryKey)) fail();
  for (const section of ['search', 'columns', 'toolbar', 'rowActions']) {
    const items = p[section]; if (!Array.isArray(items) || items.length > 100) fail();
    const ids = new Set<string>();
    for (const raw of items) {
      const keys = section === 'search' ? ['field', 'label', 'type', 'placeholder', 'options'] : section === 'columns'
        ? ['key', 'label', 'prop', 'type', 'width', 'minWidth', 'align', 'fixed', 'slot', 'formatter', 'visibleWhen', 'sortable']
        : ['id', 'label', 'icon', 'color', 'permission', 'hidden', 'disabled', 'visibleWhen', 'disabledWhen', 'activeWhen', 'inactiveColor', 'selectionCount', 'action'];
      const item = record(raw, keys); const id = item[section === 'search' ? 'field' : section === 'columns' ? 'key' : 'id'];
      if (!identifier(id) || ids.has(id) || typeof item.label !== 'string' || bytes(item.label) > 300) fail(); ids.add(id);
      for (const k of ['visibleWhen', 'disabledWhen', 'activeWhen']) if (owns(item, k)) condition(item[k]);
      if (section === 'search') {
        if (!['input', 'select', 'date', 'range'].includes(item.type)) fail();
        if (owns(item, 'placeholder') && typeof item.placeholder !== 'string') fail();
        if (owns(item, 'options')) {
          if (!Array.isArray(item.options)) fail();
          for (const option of item.options) { const o = record(option, ['label', 'value']); if (typeof o.label !== 'string' || (typeof o.value !== 'string' && !Number.isSafeInteger(o.value))) fail(); }
        }
      } else if (section === 'columns') {
        for (const k of ['prop', 'slot', 'formatter']) if (owns(item, k) && !identifier(item[k])) fail();
        for (const k of ['width', 'minWidth']) if (owns(item, k) && (!Number.isInteger(item[k]) || item[k] < 1 || item[k] > 2000)) fail();
        if (owns(item, 'type') && item.type !== 'selection') fail();
        if (owns(item, 'sortable') && typeof item.sortable !== 'boolean') fail();
        if (owns(item, 'align') && !['left', 'center', 'right'].includes(item.align)) fail();
        if (owns(item, 'fixed') && !['left', 'right'].includes(item.fixed)) fail();
      } else {
        text(item.label, 100);
        const base = Object.fromEntries(Object.entries(item).filter(([key]) => !['visibleWhen', 'disabledWhen', 'activeWhen', 'inactiveColor', 'selectionCount'].includes(key)));
        validateButtons([base], section === 'toolbar' ? 'toolbar' : 'row');
        const a = record(item.action, ['type', 'key', 'capabilityVersion']);
        if (a.type !== 'registered') fail();
        for (const k of ['hidden', 'disabled', 'selectionCount']) if (owns(item, k) && typeof item[k] !== 'boolean') fail();
        if (owns(item, 'inactiveColor') && !['primary', 'success', 'warning', 'danger', 'info'].includes(item.inactiveColor)) fail();
        if (owns(item, 'permission') && (typeof item.permission !== 'string' || !item.permission)) fail();
        if (!buttonIdentifier(item.id)) fail();
      }
    }
  }
  if (owns(p, 'list')) {
    const list = record(p.list, ['category', 'leftTree', 'buttons', 'tree', 'tools']);
    if (owns(list, 'tree')) {
      const tree = record(list.tree, ['enabled', 'parentField']);
      if (typeof tree.enabled !== 'boolean' || (owns(tree, 'parentField') && !identifier(tree.parentField)) || (tree.enabled && !tree.parentField)) fail();
    }
    if (owns(list, 'tools')) {
      const tools = record(list.tools, ['refresh', 'search', 'columns', 'density', 'fullscreen']);
      if (Object.values(tools).some(value => typeof value !== 'boolean')) fail();
    }
    if (owns(list, 'category')) {
      const c = record(list.category, ['enabled', 'field']);
      if (typeof c.enabled !== 'boolean' || (owns(c, 'field') && !identifier(c.field))) fail();
      if (c.enabled && !p.search.some((s: PageSearch) => s.field === c.field && s.type === 'select' && Array.isArray(s.options))) fail();
    }
    if (owns(list, 'leftTree')) {
      const t = record(list.leftTree, ['enabled', 'source', 'mapping', 'selection', 'actions']);
      if (typeof t.enabled !== 'boolean') fail();
      const s = record(owns(t, 'source') ? t.source : {}, ['type', 'module']);
      const m = record(owns(t, 'mapping') ? t.mapping : {}, ['valueField', 'labelField', 'targetField', 'parentField', 'sortField']);
      const selection = record(owns(t, 'selection') ? t.selection : {}, ['mode', 'includeDescendants']);
      const actions = record(owns(t, 'actions') ? t.actions : {}, ['create', 'addChild', 'edit', 'delete']);
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
      for (const location of ['categoryToolbar', 'categoryNode'] as const) {
        if (owns(list.buttons, location)) validateButtons(list.buttons[location], location);
      }
    }
  }
  const pagination = record(p.pagination, ['pageSize', 'pageSizes', 'enabled']);
  if (owns(pagination, 'enabled') && typeof pagination.enabled !== 'boolean') fail();
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
