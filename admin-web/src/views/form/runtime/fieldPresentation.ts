import dayjs from 'dayjs';

export type PresentationOption = { label: string; value: unknown };

type PresentationNode = { id?: string | null; field?: string | null; dataSource?: { options?: unknown } | null; listFormatter?: string; formatter?: string };

type RawOption = { label: unknown; value: unknown; [key: string]: unknown };

const isOption = (item: unknown): item is RawOption => item !== null && typeof item === 'object' && !Array.isArray(item) && 'label' in item && 'value' in item;
const displayValue = (value: unknown): string => {
  if (value === null || value === undefined) return '';
  if (typeof value === 'object') { try { return JSON.stringify(value); } catch { return ''; } }
  return String(value);
};

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
  const find = (target: unknown): PresentationOption | undefined => {
    const pending: unknown[] = [...options];
    const visited = new Set<object>();
    while (pending.length) {
      const option = pending.shift();
      if (!isOption(option) || visited.has(option)) continue;
      visited.add(option);
      if (Object.is(option.value, target) || (typeof option.value !== 'object' && typeof target !== 'object' && String(option.value) === String(target))) return { ...option, label: String(option.label) };
      if (Array.isArray(option.children)) pending.push(...option.children);
    }
    return undefined;
  };
  const labels = values.map(item => find(item)?.label ?? (formatter === 'boolean' || formatter === 'switch' ? (['1', 'true', 'yes', 'on'].includes(String(item).toLowerCase()) ? '是' : '否') : displayValue(item)));
  return labels.join(', ');
}
