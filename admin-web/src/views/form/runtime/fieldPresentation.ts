import dayjs from 'dayjs';
import { computed, onBeforeUnmount, ref, watch, toValue, type MaybeRefOrGetter } from 'vue';
import { formDataApi } from '@/api/formData';
import { resolveDataSourceParameters } from '../dataSource/useFormDataSource';

export type PresentationOption = { label: string; value: unknown; [key: string]: unknown };
export type FieldOptionsRequest = (formKey: string, field: string, params: Record<string, unknown>, signal?: AbortSignal) => Promise<{ options: unknown[]; total?: number }>;

const fieldOptionsCache = new Map<string, Promise<PresentationOption[]>>();
const requestScopes = new WeakMap<FieldOptionsRequest, number>();
let nextRequestScope = 0;
const defaultRequest: FieldOptionsRequest = (key, field, params, signal) => formDataApi.options(key, field, params, signal);
const stableKey = (value: unknown): string => {
  if (Array.isArray(value)) return `[${value.map(stableKey).join(',')}]`;
  if (value && typeof value === 'object') return `{${Object.entries(value as Record<string, unknown>).sort(([a], [b]) => a.localeCompare(b)).map(([key, item]) => `${JSON.stringify(key)}:${stableKey(item)}`).join(',')}}`;
  return JSON.stringify(value);
};

export type PresentationNode = { id?: string | null; field?: string | null; dataSource?: { kind?: string; options?: unknown; params?: Record<string, unknown>; dependsOn?: string[] } | null; listFormatter?: string; formatter?: string };

type RawOption = { label: unknown; value: unknown; [key: string]: unknown };

const isOption = (item: unknown): item is RawOption => item !== null && typeof item === 'object' && !Array.isArray(item) && 'label' in item && ('value' in item || ('children' in item && Array.isArray(item.children)));

export function findFieldOption(target: unknown, options: PresentationOption[]): PresentationOption | undefined {
  const pending: unknown[] = [...options];
  const visited = new Set<object>();
  let compatible: PresentationOption | undefined;
  while (pending.length) {
    const option = pending.shift();
    if (!isOption(option) || visited.has(option)) continue;
    visited.add(option);
    if ('value' in option) {
      const candidate = { ...option, label: String(option.label) };
      if (Object.is(option.value, target)) return candidate;
      const numericString = (typeof option.value === 'number' && typeof target === 'string')
        || (typeof option.value === 'string' && typeof target === 'number');
      if (!compatible && numericString && String(option.value) === String(target)) compatible = candidate;
    }
    if (Array.isArray(option.children)) pending.push(...option.children);
  }
  return compatible;
}

// 仅适配控件展示，不修改模型；用户选择时仍使用选项声明的值。
export function adaptFieldSelection(value: unknown, options: PresentationOption[]): unknown {
  const adapt = (item: unknown) => {
    const option = findFieldOption(item, options);
    return option ? option.value : item;
  };
  return Array.isArray(value) ? value.map(adapt) : adapt(value);
}
const displayValue = (value: unknown): string => {
  if (value === null || value === undefined) return '';
  if (typeof value === 'object') { try { return JSON.stringify(value); } catch { return ''; } }
  return String(value);
};

export function clearFieldOptionsCache(): void { fieldOptionsCache.clear(); }

export async function resolveFieldOptionsAsync(formKey: string, node: PresentationNode, params: Record<string, unknown> = {}, request: FieldOptionsRequest = defaultRequest, signal?: AbortSignal): Promise<PresentationOption[]> {
  const field = node.field ?? node.id ?? '';
  const requestParams = { ...resolveDataSourceParameters(node.dataSource ?? {}, params), ...params };
  if (!requestScopes.has(request)) requestScopes.set(request, ++nextRequestScope);
  const key = stableKey([requestScopes.get(request), formKey, field, requestParams]);
  const existing = fieldOptionsCache.get(key);
  if (existing) return existing;
  const pending = request(formKey, field, requestParams, signal).then(result => result.options.filter(isOption).map(item => ({ ...item, label: String(item.label) })));
  fieldOptionsCache.set(key, pending);
  pending.catch(() => { if (fieldOptionsCache.get(key) === pending) fieldOptionsCache.delete(key); });
  return pending;
}

export const isDynamicFieldOptions = (node: PresentationNode): boolean => !!node.dataSource?.kind && node.dataSource.kind !== 'static';

// schema 节点优先于旧字段投影，保留嵌套节点声明的实际依赖。
export function presentationNodes(fields: Array<{ field_name: string; options_source?: PresentationNode['dataSource'] }>, nodes: Array<PresentationNode & { children?: any[] }> = []): PresentationNode[] {
  const sources = new Map<string, PresentationNode>();
  const visit = (items: typeof nodes) => items.forEach(node => { if (node.field) sources.set(node.field, node); visit(node.children ?? []); });
  visit(nodes);
  return fields.map(field => ({ field: field.field_name, dataSource: sources.get(field.field_name)?.dataSource ?? field.options_source }));
}

export function useSuppliedFieldOptions(context: MaybeRefOrGetter<Record<string, unknown> | Record<string, unknown>[]> = {}) {
  type Entry = { controller: AbortController; options?: PresentationOption[]; error?: string; pending: boolean; task?: Promise<void> };
  const entries = new Map<string, Entry>();
  const revision = ref(0);
  let active = true;
  let config: { key: string; fields: PresentationNode[]; request: FieldOptionsRequest } | undefined;
  const contexts = () => { const value = toValue(context); return Array.isArray(value) ? value : [value]; };
  const identity = (node: PresentationNode, values: Record<string, unknown>) => stableKey([node, resolveDataSourceParameters(node.dataSource ?? {}, values)]);
  const invalidate = () => {
    config = undefined;
    entries.forEach(entry => entry.controller.abort()); entries.clear(); revision.value++;
  };
  onBeforeUnmount(() => { active = false; invalidate(); });
  const reconcile = async () => {
    if (!active || !config) return;
    const { key, fields, request } = config;
    const wanted = new Map<string, { node: PresentationNode; params: Record<string, unknown> }>();
    for (const values of contexts()) for (const node of fields.filter(isDynamicFieldOptions)) {
      wanted.set(identity(node, values), { node, params: resolveDataSourceParameters(node.dataSource ?? {}, values) });
    }
    entries.forEach((entry, id) => { if (!wanted.has(id)) { entry.controller.abort(); entries.delete(id); } });
    for (const [id, { node, params }] of wanted) {
      if (entries.has(id)) continue;
      const entry: Entry = { controller: new AbortController(), pending: true };
      entries.set(id, entry);
      entry.task = (async () => {
        try {
          const result = await request(key, String(node.field ?? node.id ?? ''), params, entry.controller.signal);
          if (active && entries.get(id) === entry) entry.options = result.options.filter(isOption).map(item => ({ ...item, label: String(item.label) }));
        } catch {
          if (active && entries.get(id) === entry) entry.error = '选项加载失败，请稍后重试';
        } finally {
          if (active && entries.get(id) === entry) { entry.pending = false; revision.value++; }
        }
      })();
    }
    revision.value++;
    await Promise.all([...entries.values()].map(entry => entry.task));
  };
  watch(() => toValue(context), () => { void reconcile(); }, { deep: true, flush: 'sync' });
  const load = async (key: string, fields: PresentationNode[], request: FieldOptionsRequest = defaultRequest) => {
    invalidate(); config = { key, fields, request }; await reconcile();
  };
  const entryFor = (field: string, values: Record<string, unknown>) => {
    void revision.value;
    const node = config?.fields.find(node => (node.id ?? node.field) === field);
    return node ? entries.get(identity(node, values)) : undefined;
  };
  const forContext = (values: Record<string, unknown>) => Object.fromEntries((config?.fields ?? []).flatMap(node => {
    const field = String(node.id ?? node.field ?? '');
    const options = entryFor(field, values)?.options;
    return options ? [[field, options]] : [];
  }));
  const supplied = computed(() => { void revision.value; return forContext(contexts()[0] ?? {}); });
  const errors = computed(() => { void revision.value; return Object.fromEntries((config?.fields ?? []).flatMap(node => { const field = String(node.id ?? node.field ?? ''); const error = entryFor(field, contexts()[0] ?? {})?.error; return error ? [[field, error]] : []; })); });
  const pending = computed(() => { void revision.value; return Object.fromEntries((config?.fields ?? []).map(node => { const field = String(node.id ?? node.field ?? ''); return [field, entryFor(field, contexts()[0] ?? {})?.pending ?? false]; })); });
  const placeholder = (field: string, values = contexts()[0] ?? {}) => { const entry = entryFor(field, values); return entry?.pending ? '选项加载中…' : entry?.error ?? ''; };
  return { supplied, errors, pending, load, invalidate, placeholder, forContext };
}

export function resolveFieldOptions(node: PresentationNode, supplied: Record<string, PresentationOption[]> = {}): PresentationOption[] {
  const options = (node.id ? supplied[node.id] : undefined) ?? (node.field ? supplied[node.field] : undefined);
  if (options) return options.map(item => ({ ...item, label: String(item.label) }));
  return Array.isArray(node.dataSource?.options)
    ? node.dataSource.options.filter(isOption).map(item => ({ ...item, label: String(item.label) }))
    : [];
}

export function formatFieldValue(value: unknown, options: PresentationOption[] = [], formatter?: string, _profile: 'runtime' | 'data' | 'published' = 'runtime'): string {
  if (value === null || value === undefined || value === '') return '';
  if (formatter === 'money' || formatter === 'number' || formatter === 'percent') {
    const number = Number(value);
    if (!Number.isFinite(number)) return displayValue(value);
    const fixed = formatter === 'money' || formatter === 'percent';
    const formatted = number.toLocaleString('zh-CN', fixed ? { minimumFractionDigits: 2, maximumFractionDigits: 2 } : { minimumFractionDigits: 0, maximumFractionDigits: 20 });
    return formatter === 'money' ? `￥${formatted}` : formatter === 'percent' ? `${formatted}%` : formatted;
  }
  if (formatter === 'date' || formatter === 'datetime' || formatter === 'time') {
    if (!value) return '';
    if (formatter === 'time' && /^\d{2}:\d{2}(?::\d{2})?$/.test(String(value))) return String(value);
    const date = dayjs(typeof value === 'number' ? value : String(value));
    const pattern = formatter === 'date' ? 'YYYY-MM-DD' : formatter === 'time' ? 'HH:mm:ss' : 'YYYY-MM-DD HH:mm:ss';
    return date.isValid() ? date.format(pattern) : displayValue(value);
  }
  if (formatter === 'json') {
    try { return JSON.stringify(typeof value === 'string' ? JSON.parse(value) : value) ?? ''; } catch { return displayValue(value); }
  }
  const values = Array.isArray(value) ? value : [value];
  const labels = values.map(item => findFieldOption(item, options)?.label ?? (formatter === 'boolean' || formatter === 'switch' ? (['1', 'true', 'yes', 'on'].includes(String(item).toLowerCase()) ? '是' : '否') : displayValue(item)));
  return labels.join(', ');
}
