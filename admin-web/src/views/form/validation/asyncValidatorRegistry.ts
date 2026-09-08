export interface FieldError {
  path: string;
  message: string;
}

export interface AsyncValidatorContext {
  value: unknown;
  values: Record<string, unknown>;
  options: Record<string, unknown>;
  signal: AbortSignal;
}

export interface AsyncValidatorResult {
  valid: boolean;
  message?: string;
}

export interface AsyncValidatorOptions {
  debounce?: number;
  timeout?: number;
  cacheTtl?: number;
  values?: Record<string, unknown>;
  params?: Record<string, unknown>;
  message?: string;
}

export type AsyncValidatorHandler = (context: AsyncValidatorContext) => Promise<AsyncValidatorResult>;
export type ElementPlusAsyncValidator = (rule: unknown, value: unknown) => Promise<void>;

interface CacheEntry {
  expiresAt: number;
  result: AsyncValidatorResult;
}

const resultCache = new Map<string, CacheEntry>();

const serialize = (value: unknown): string => {
  if (Array.isArray(value)) return `[${value.map(serialize).join(',')}]`;
  if (value && typeof value === 'object') {
    return `{${Object.entries(value as Record<string, unknown>).sort(([left], [right]) => left.localeCompare(right)).map(([key, item]) => `${JSON.stringify(key)}:${serialize(item)}`).join(',')}}`;
  }
  return JSON.stringify(value);
};

const validationError = (result: AsyncValidatorResult, fallback?: string): void => {
  if (!result.valid) throw new Error(result.message ?? fallback ?? '字段校验失败');
};

export const clearAsyncValidatorCache = (): void => resultCache.clear();

export const mapFieldErrors = (errors: FieldError[]): Record<string, string> => Object.fromEntries(errors.map((error) => {
  const path = error.path.startsWith('/')
    ? error.path.slice(1).split('/').map((segment) => segment.replaceAll('~1', '/').replaceAll('~0', '~')).join('.')
    : error.path;
  return [path, error.message];
}));

export const createAsyncValidatorRegistry = (handlers: Record<string, AsyncValidatorHandler>) => ({
  rule: (key: string, options: AsyncValidatorOptions): ElementPlusAsyncValidator => {
    const handler = handlers[key];
    if (!handler) throw new Error('FORM_ASYNC_VALIDATOR_NOT_REGISTERED');
    let controller: AbortController | null = null;
    let sequence = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let settleScheduled: (() => void) | null = null;

    return (_rule, value) => new Promise<void>((resolve, reject) => {
      const current = ++sequence;
      controller?.abort();
      controller = new AbortController();
      const requestController = controller;
      if (timer) {
        clearTimeout(timer);
        settleScheduled?.();
      }
      settleScheduled = resolve;
      const debounce = options.debounce ?? 300;
      timer = setTimeout(async () => {
        timer = null;
        settleScheduled = null;
        const cacheKey = serialize([key, value, options.params ?? {}]);
        const cached = resultCache.get(cacheKey);
        try {
          if (cached && cached.expiresAt > Date.now()) {
            validationError(cached.result, options.message);
            resolve();
            return;
          }
          const timeout = options.timeout ?? 5000;
          const timeoutId = setTimeout(() => requestController.abort('timeout'), timeout);
          let result: AsyncValidatorResult;
          try {
            result = await Promise.race([
              handler({ value, values: options.values ?? {}, options: options.params ?? {}, signal: requestController.signal }),
              new Promise<never>((_, fail) => setTimeout(() => fail(new Error('FORM_ASYNC_VALIDATOR_TIMEOUT')), timeout))
            ]);
          } finally {
            clearTimeout(timeoutId);
          }
          if (current !== sequence) {
            resolve();
            return;
          }
          const ttl = options.cacheTtl ?? 0;
          if (ttl > 0) resultCache.set(cacheKey, { expiresAt: Date.now() + ttl, result });
          validationError(result, options.message);
          resolve();
        } catch (reason) {
          if (current !== sequence) resolve();
          else reject(reason);
        }
      }, debounce);
    });
  }
});
