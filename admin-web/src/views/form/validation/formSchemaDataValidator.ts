export interface FormSchemaCondition {
  field?: string;
  op: string;
  value?: unknown;
  conditions?: FormSchemaCondition[];
  condition?: FormSchemaCondition;
}

export interface FormSchemaAsyncValidation {
  key: string;
  params?: Record<string, unknown>;
  debounce?: number;
  timeout?: number;
  cacheTtl?: number;
}

export interface FormSchemaValidationRule {
  type: string;
  value?: unknown;
  message?: string;
  trigger?: string[];
  validator?: FormSchemaAsyncValidation;
  when?: FormSchemaCondition;
  severity?: 'error' | 'warning';
  bail?: boolean;
}

export interface FormSchemaValidationError {
  field: string;
  rule: string;
  message: string;
  severity: 'error' | 'warning';
}

export interface ElementPlusValidationRule {
  required?: boolean;
  message?: string;
  trigger?: string[];
  validator?: (rule: unknown, value: unknown, callback: (error?: Error) => void) => Promise<void>;
}

const DEFAULT_MESSAGES: Record<string, string> = {
  required: '字段不能为空', type: '字段类型不正确', min: '字段值过小', max: '字段值过大',
  minLength: '字段长度不足', maxLength: '字段长度过长', length: '字段长度不正确',
  enum: '字段值不在允许范围内', pattern: '字段格式不正确', format: '字段格式不正确',
  same: '字段值不一致', different: '字段值必须不同', before: '字段日期必须更早',
  after: '字段日期必须更晚', precision: '字段小数位数过多', file: '文件不符合要求',
  array: '字段必须为数组', object: '字段必须为对象', items: '数组成员不符合要求',
  properties: '对象属性不符合要求'
};

const isPlainObject = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);
const isEmpty = (value: unknown): boolean => value === null || value === undefined || (typeof value === 'string' && value.trim() === '') || (Array.isArray(value) && value.length === 0);

export const isCompatibleSafePattern = (pattern: string): boolean => {
  if (!pattern || pattern.length > 512 || /\(\?(?!:)|\\(?:[1-9kAGRKXCQEhHvV])|&&|--|\[\[:|[+*?}]\+/.test(pattern)) return false;
  if (/\((?:[^()\\]|\\.)*[+*](?:[^()\\]|\\.)*\)[+*{]/.test(pattern)) return false;
  if (/\([^()]*\|[^()]*\)[+*{]/.test(pattern)) return false;
  try { new RegExp(pattern, 'u'); return true; } catch { return false; }
};

const matchesPattern = (value: unknown, pattern: unknown): boolean => typeof value === 'string'
  && typeof pattern === 'string' && isCompatibleSafePattern(pattern) && new RegExp(pattern, 'u').test(value);

const conditionMatches = (condition: FormSchemaCondition | undefined, values: Record<string, unknown>): boolean => {
  if (!condition) return true;
  if (condition.op === 'and' || condition.op === 'or') {
    const matches = (condition.conditions ?? []).map((nested) => conditionMatches(nested, values));
    return condition.op === 'and' ? matches.every(Boolean) : matches.some(Boolean);
  }
  if (condition.op === 'not') return !conditionMatches(condition.condition, values);
  const actual = values[condition.field ?? ''];
  const expected = condition.value;
  if (condition.op === 'eq') return Object.is(actual, expected);
  if (condition.op === 'neq') return !Object.is(actual, expected);
  if (condition.op === 'gt') return typeof actual === 'number' && actual > Number(expected);
  if (condition.op === 'gte') return typeof actual === 'number' && actual >= Number(expected);
  if (condition.op === 'lt') return typeof actual === 'number' && actual < Number(expected);
  if (condition.op === 'lte') return typeof actual === 'number' && actual <= Number(expected);
  if (condition.op === 'in') return Array.isArray(expected) && expected.some((item) => Object.is(item, actual));
  if (condition.op === 'notIn') return Array.isArray(expected) && !expected.some((item) => Object.is(item, actual));
  if (condition.op === 'contains') return typeof actual === 'string' && typeof expected === 'string' && actual.includes(expected);
  if (condition.op === 'startsWith') return typeof actual === 'string' && typeof expected === 'string' && actual.startsWith(expected);
  if (condition.op === 'endsWith') return typeof actual === 'string' && typeof expected === 'string' && actual.endsWith(expected);
  if (condition.op === 'empty') return isEmpty(actual);
  if (condition.op === 'notEmpty') return !isEmpty(actual);
  if (condition.op === 'matches') return matchesPattern(actual, expected);
  return false;
};

const hasType = (value: unknown, type: string): boolean => {
  if (type === 'string') return typeof value === 'string';
  if (type === 'number') return typeof value === 'number' && Number.isFinite(value);
  if (type === 'integer') return typeof value === 'number' && Number.isInteger(value);
  if (type === 'boolean') return typeof value === 'boolean';
  if (type === 'array') return Array.isArray(value);
  if (type === 'object') return isPlainObject(value);
  return type === 'null' && value === null;
};

const lengthOf = (value: unknown): number | null => typeof value === 'string' ? Array.from(value).length
  : Array.isArray(value) ? value.length : isPlainObject(value) ? Object.keys(value).length : null;

const dateTimestamp = (value: unknown): number | null => {
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})?)?$/.test(value)) return null;
  const timestamp = Date.parse(value.length === 10 ? `${value}T00:00:00Z` : value);
  return Number.isNaN(timestamp) ? null : timestamp;
};

const matchesFormat = (value: unknown, format: string): boolean => {
  if (typeof value !== 'string') return false;
  if (format === 'email') return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  if (format === 'url') { try { return ['http:', 'https:'].includes(new URL(value).protocol); } catch { return false; } }
  if (format === 'date') return /^\d{4}-\d{2}-\d{2}$/.test(value) && dateTimestamp(value) !== null;
  if (format === 'dateTime' || format === 'datetime') return value.includes('T') && dateTimestamp(value) !== null;
  return format === 'uuid' && /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value);
};

const fileTypeAllowed = (file: Record<string, unknown>, allowed: unknown[]): boolean => {
  const mime = String(file.type ?? file.mime ?? '').toLowerCase();
  const name = String(file.name ?? '').toLowerCase();
  return allowed.some((entry) => {
    const type = String(entry).toLowerCase();
    return type === mime || (type.endsWith('/*') && mime.startsWith(type.slice(0, -1))) || (type.startsWith('.') && name.endsWith(type));
  });
};

const validFiles = (value: unknown, constraints: unknown): boolean => {
  if (!isPlainObject(constraints)) return false;
  const files = Array.isArray(value) ? value : [value];
  if (constraints.count !== undefined && files.length > Number(constraints.count)) return false;
  return files.every((file) => isPlainObject(file)
    && (constraints.size === undefined || (typeof file.size === 'number' && file.size <= Number(constraints.size)))
    && (constraints.type === undefined || (Array.isArray(constraints.type) && fileTypeAllowed(file, constraints.type))));
};

const passes = (type: string, value: unknown, argument: unknown, values: Record<string, unknown>): boolean => {
  if (type === 'required') return !isEmpty(value);
  if (type === 'type') return hasType(value, String(argument));
  if (type === 'min') return typeof value === 'number' && value >= Number(argument);
  if (type === 'max') return typeof value === 'number' && value <= Number(argument);
  if (type === 'minLength') return lengthOf(value) !== null && lengthOf(value)! >= Number(argument);
  if (type === 'maxLength') return lengthOf(value) !== null && lengthOf(value)! <= Number(argument);
  if (type === 'length') return lengthOf(value) === Number(argument);
  if (type === 'enum') return Array.isArray(argument) && argument.some((item) => Object.is(item, value));
  if (type === 'pattern') return matchesPattern(value, argument);
  if (type === 'format') return matchesFormat(value, String(argument));
  if (type === 'same') return Object.hasOwn(values, String(argument)) && Object.is(value, values[String(argument)]);
  if (type === 'different') return Object.hasOwn(values, String(argument)) && !Object.is(value, values[String(argument)]);
  if (type === 'before' || type === 'after') {
    const left = dateTimestamp(value); const right = dateTimestamp(values[String(argument)]);
    return left !== null && right !== null && (type === 'before' ? left < right : left > right);
  }
  if (type === 'precision') return Number(argument) >= 0 && ['number', 'string'].includes(typeof value)
    && /^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(String(value)) && (String(value).split('.')[1]?.length ?? 0) <= Number(argument);
  if (type === 'file') return validFiles(value, argument);
  if (type === 'array') return Array.isArray(value);
  if (type === 'object') return isPlainObject(value);
  return type === 'items' || type === 'properties';
};

const nestedErrors = (type: string, field: string, value: unknown, argument: unknown, values: Record<string, unknown>): FormSchemaValidationError[] => {
  if (type === 'items' && Array.isArray(value) && Array.isArray(argument)) {
    for (let index = 0; index < value.length; index += 1) {
      const errors = validateFormSchemaField(`${field}.${index}`, value[index], argument as FormSchemaValidationRule[], values);
      if (errors.length) return errors;
    }
  }
  if (type === 'properties' && isPlainObject(value) && isPlainObject(argument)) {
    for (const [property, rules] of Object.entries(argument)) {
      if (!Array.isArray(rules)) continue;
      const errors = validateFormSchemaField(`${field}.${property}`, value[property], rules as FormSchemaValidationRule[], values);
      if (errors.length) return errors;
    }
  }
  return [];
};

export const validateFormSchemaField = (field: string, value: unknown, rules: FormSchemaValidationRule[], values: Record<string, unknown>): FormSchemaValidationError[] => {
  const errors: FormSchemaValidationError[] = [];
  for (const rule of rules) {
    if (!conditionMatches(rule.when, values) || rule.type === 'async' || (rule.type !== 'required' && isEmpty(value))) continue;
    const nested = nestedErrors(rule.type, field, value, rule.value, values);
    if (!nested.length && passes(rule.type, value, rule.value, values)) continue;
    errors.push({ field: nested[0]?.field ?? field, rule: rule.type, message: rule.message ?? nested[0]?.message ?? DEFAULT_MESSAGES[rule.type] ?? '字段校验失败', severity: rule.severity ?? 'error' });
    if (rule.bail ?? true) break;
  }
  return errors;
};

export const validateFormSchemaValues = (values: Record<string, unknown>, fieldRules: Record<string, FormSchemaValidationRule[]>): FormSchemaValidationError[] => Object.entries(fieldRules)
  .flatMap(([field, rules]) => validateFormSchemaField(field, values[field], rules, values));

type AsyncRuleFactory = (key: string, rule: FormSchemaValidationRule) => (rule: unknown, value: unknown, callback: (error?: Error) => void) => Promise<void>;

export const createElementPlusValidationRules = (
  field: string,
  rules: FormSchemaValidationRule[],
  values: Record<string, unknown>,
  asyncFactory?: AsyncRuleFactory
): ElementPlusValidationRule[] => rules.map((rule) => {
  if (rule.type === 'async' && rule.validator?.key && asyncFactory) {
    const asyncValidator = asyncFactory(rule.validator.key, rule);
    return {
      validator: async (elementRule, value, callback) => {
        if (!conditionMatches(rule.when, values) || isEmpty(value)) return;
        await asyncValidator(elementRule, value, callback);
      },
      trigger: rule.trigger ?? ['blur', 'change']
    };
  }
  return {
    trigger: rule.trigger ?? ['blur', 'change'],
    validator: async (_unused, value) => {
      const errors = validateFormSchemaField(field, value, [{ ...rule, bail: true }], values);
      if (errors.length) throw new Error(errors[0]!.message);
    }
  };
});
