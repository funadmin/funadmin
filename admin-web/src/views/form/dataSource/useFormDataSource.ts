import { onScopeDispose, ref, watch, type Ref } from 'vue';
import { formDataApi } from '@/api/formData';
import { applyStaleValuePolicy, type DataSourceOption, type StaleValuePolicy } from './dataSourceRegistry';

export interface FormDataSourceDefinition {
  kind?: string;
  dependsOn?: string[];
  params?: Record<string, unknown>;
  staleValue?: StaleValuePolicy;
  debounce?: number;
  cacheTtl?: number;
  searchable?: boolean;
  pagination?: { pageSize?: number };
}

export interface FormDataSourceResult {
  options: DataSourceOption[];
  total?: number;
}

export type FormDataSourceRequest = (
  formKey: string,
  field: string,
  params: Record<string, unknown>,
  signal: AbortSignal
) => Promise<FormDataSourceResult>;

export interface FormDataSourceControlState {
  loading: boolean;
  error?: unknown;
  page: number;
  pageSize: number;
  total: number;
  searchable: boolean;
  paginated: boolean;
  search: (keyword: string) => void;
  setPage: (page: number) => void;
  retry: () => Promise<void>;
}

interface UseFormDataSourceOptions {
  formKey: string;
  field: string;
  definition: FormDataSourceDefinition;
  values: Record<string, unknown>;
  request?: FormDataSourceRequest;
  debounceMs?: number;
}

interface CacheEntry {
  expiresAt: number;
  result: FormDataSourceResult;
}

const cache = new Map<string, CacheEntry>();
const pending = new Map<string, Promise<FormDataSourceResult>>();

const defaultRequest: FormDataSourceRequest = (formKey, field, params, signal) => (
  formDataApi.options(formKey, field, params, signal)
);

const stableKey = (value: unknown): string => {
  if (Array.isArray(value)) return `[${value.map(stableKey).join(',')}]`;
  if (value && typeof value === 'object') {
    return `{${Object.entries(value as Record<string, unknown>).sort(([left], [right]) => left.localeCompare(right)).map(([key, item]) => `${JSON.stringify(key)}:${stableKey(item)}`).join(',')}}`;
  }
  return JSON.stringify(value);
};

const resolveParameter = (value: unknown, values: Record<string, unknown>): unknown => {
  if (typeof value !== 'string' || !value.startsWith('$form.')) return value;
  return value.slice(6).split('.').reduce<unknown>((subject, segment) => (
    subject && typeof subject === 'object' ? (subject as Record<string, unknown>)[segment] : undefined
  ), values);
};

const cachedRequest = (
  key: string,
  ttl: number,
  request: () => Promise<FormDataSourceResult>
): Promise<FormDataSourceResult> => {
  const found = cache.get(key);
  if (found && found.expiresAt > Date.now()) return Promise.resolve(found.result);
  const inFlight = pending.get(key);
  if (inFlight) return inFlight;
  const promise = request().then((result) => {
    if (ttl > 0) cache.set(key, { expiresAt: Date.now() + ttl, result });
    return result;
  }).finally(() => pending.delete(key));
  pending.set(key, promise);
  return promise;
};

export const clearFormDataSourceCache = (): void => {
  cache.clear();
  pending.clear();
};

export const useFormDataSource = (config: UseFormDataSourceOptions): {
  options: Ref<DataSourceOption[]>;
  loading: Ref<boolean>;
  error: Ref<unknown>;
  page: Ref<number>;
  total: Ref<number>;
  pageSize: number;
  searchable: boolean;
  paginated: boolean;
  search: (keyword: string) => void;
  setPage: (page: number) => void;
  refresh: () => Promise<void>;
} => {
  const options = ref<DataSourceOption[]>([]);
  const loading = ref(false);
  const error = ref<unknown>();
  const page = ref(1);
  const total = ref(0);
  const keyword = ref('');
  const debounceMs = config.debounceMs ?? config.definition.debounce ?? 300;
  const pageSize = config.definition.pagination?.pageSize ?? 20;
  const ttl = config.definition.cacheTtl ?? 0;
  let sequence = 0;
  let controller: AbortController | null = null;
  let timer: ReturnType<typeof setTimeout> | null = null;
  let dependencyChanged = false;
  let activeKey = '';
  let activeRefresh: Promise<void> | null = null;

  const parameters = (): Record<string, unknown> => {
    const declared = Object.fromEntries(Object.entries(config.definition.params ?? {}).map(([name, value]) => [name, resolveParameter(value, config.values)]));
    const dependencyValues = Object.fromEntries((config.definition.dependsOn ?? []).map((field) => [field, config.values[field]]));
    return { ...declared, ...dependencyValues, keyword: keyword.value, page: page.value, pageSize };
  };

  const refresh = (): Promise<void> => {
    const params = parameters();
    const key = stableKey([config.formKey, config.field, params]);
    if (activeRefresh && activeKey === key) return activeRefresh;

    const current = ++sequence;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = undefined;
    activeKey = key;
    const requestController = controller;
    let task!: Promise<void>;
    task = (async (): Promise<void> => {
      try {
        const result = await cachedRequest(key, ttl, () => (config.request ?? defaultRequest)(config.formKey, config.field, params, requestController.signal));
        if (current !== sequence) return;
        options.value = result.options;
        total.value = result.total ?? result.options.length;
        if (dependencyChanged) {
          config.values[config.field] = applyStaleValuePolicy(
            config.definition.staleValue ?? 'clear',
            config.values[config.field],
            result.options.map((option) => option.value)
          );
          dependencyChanged = false;
        }
      } catch (reason) {
        if (current === sequence && !(reason instanceof DOMException && reason.name === 'AbortError')) error.value = reason;
      } finally {
        if (current === sequence) loading.value = false;
        if (activeRefresh === task) activeRefresh = null;
      }
    })();
    activeRefresh = task;
    return task;
  };

  const schedule = (): void => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => { void refresh(); }, debounceMs);
  };

  const search = (value: string): void => {
    if (keyword.value === value && page.value === 1) return;
    keyword.value = value;
    page.value = 1;
    schedule();
  };
  const setPage = (value: number): void => {
    const nextPage = Math.max(1, value);
    if (page.value === nextPage) return;
    page.value = nextPage;
    schedule();
  };

  const dependencies = config.definition.dependsOn ?? [];
  watch(() => dependencies.map((field) => config.values[field]), () => {
    dependencyChanged = true;
    page.value = 1;
    schedule();
  }, { deep: true });

  schedule();
  onScopeDispose(() => {
    if (timer) clearTimeout(timer);
    controller?.abort();
  });

  return {
    options, loading, error, page, total, pageSize,
    searchable: config.definition.searchable ?? true,
    paginated: Boolean(config.definition.pagination),
    search, setPage, refresh
  };
};
