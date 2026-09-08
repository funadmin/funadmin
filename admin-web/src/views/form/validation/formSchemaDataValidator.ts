export interface FormSchemaValidationRule {
  type: string;
  value?: unknown;
  message?: string;
}

export interface FormSchemaValidationError {
  field: string;
  rule: string;
  message: string;
}

const DEFAULT_MESSAGES: Record<string, string> = {
  required: '字段不能为空',
  type: '字段类型不正确',
  min: '字段值过小',
  max: '字段值过大',
  minLength: '字段长度不足',
  maxLength: '字段长度过长',
  length: '字段长度不正确',
  enum: '字段值不在允许范围内',
  pattern: '字段格式不正确',
  format: '字段格式不正确',
  same: '字段值不一致',
  different: '字段值必须不同',
  before: '字段日期必须更早',
  after: '字段日期必须更晚',
  precision: '字段小数位数过多',
  array: '字段必须为数组',
  object: '字段必须为对象'
};

const isPlainObject = (value: unknown): value is Record<string, unknown> => (
  typeof value === 'object' && value !== null && !Array.isArray(value)
);

const isEmpty = (value: unknown): boolean => (
  value === null
  || value === undefined
  || (typeof value === 'string' && value.trim() === '')
  || (Array.isArray(value) && value.length === 0)
);

const hasType = (value: unknown, type: string): boolean => {
  if (type === 'string') return typeof value === 'string';
  if (type === 'number') return typeof value === 'number' && Number.isFinite(value);
  if (type === 'integer') return typeof value === 'number' && Number.isInteger(value);
  if (type === 'boolean') return typeof value === 'boolean';
  if (type === 'array') return Array.isArray(value);
  if (type === 'object') return isPlainObject(value);
  if (type === 'null') return value === null;
  return false;
};

const lengthOf = (value: unknown): number | null => {
  if (typeof value === 'string') return Array.from(value).length;
  if (Array.isArray(value)) return value.length;
  if (isPlainObject(value)) return Object.keys(value).length;
  return null;
};

const matchesFormat = (value: unknown, format: string): boolean => {
  if (typeof value !== 'string') return false;
  if (format === 'email') return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  if (format === 'url') {
    try {
      const url = new URL(value);
      return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
      return false;
    }
  }
  if (format === 'date') return /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(`${value}T00:00:00Z`));
  if (format === 'dateTime' || format === 'datetime') return /^\d{4}-\d{2}-\d{2}T/.test(value) && !Number.isNaN(Date.parse(value));
  if (format === 'uuid') return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value);
  return false;
};

const dateTimestamp = (value: unknown): number | null => {
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})?)?$/.test(value)) return null;
  const timestamp = Date.parse(value.length === 10 ? `${value}T00:00:00Z` : value);
  return Number.isNaN(timestamp) ? null : timestamp;
};

const hasPrecision = (value: unknown, precision: number): boolean => {
  if (precision < 0 || !['number', 'string'].includes(typeof value)) return false;
  const text = String(value);
  if (!/^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(text)) return false;
  return (text.split('.')[1]?.length ?? 0) <= precision;
};

const passes = (
  type: string,
  value: unknown,
  argument: unknown,
  values: Record<string, unknown>
): boolean => {
  if (type === 'required') return !isEmpty(value);
  if (type === 'type') return hasType(value, String(argument));
  if (type === 'min') return typeof value === 'number' && value >= Number(argument);
  if (type === 'max') return typeof value === 'number' && value <= Number(argument);
  if (type === 'minLength') return lengthOf(value) !== null && lengthOf(value)! >= Number(argument);
  if (type === 'maxLength') return lengthOf(value) !== null && lengthOf(value)! <= Number(argument);
  if (type === 'length') return lengthOf(value) === Number(argument);
  if (type === 'enum') return Array.isArray(argument) && argument.some((item) => Object.is(item, value));
  if (type === 'pattern') {
    if (typeof value !== 'string' || typeof argument !== 'string') return false;
    try {
      return new RegExp(argument, 'u').test(value);
    } catch {
      return false;
    }
  }
  if (type === 'format') return matchesFormat(value, String(argument));
  if (type === 'same') return Object.prototype.hasOwnProperty.call(values, String(argument)) && Object.is(value, values[String(argument)]);
  if (type === 'different') return Object.prototype.hasOwnProperty.call(values, String(argument)) && !Object.is(value, values[String(argument)]);
  if (type === 'before' || type === 'after') {
    const left = dateTimestamp(value);
    const right = dateTimestamp(values[String(argument)]);
    if (left === null || right === null) return false;
    return type === 'before' ? left < right : left > right;
  }
  if (type === 'precision') return hasPrecision(value, Number(argument));
  if (type === 'array') return Array.isArray(value);
  if (type === 'object') return isPlainObject(value);
  return false;
};

export const validateFormSchemaField = (
  field: string,
  value: unknown,
  rules: FormSchemaValidationRule[],
  values: Record<string, unknown>
): FormSchemaValidationError[] => {
  for (const rule of rules) {
    if (rule.type !== 'required' && isEmpty(value)) continue;
    if (passes(rule.type, value, rule.value, values)) continue;
    return [{
      field,
      rule: rule.type,
      message: rule.message ?? DEFAULT_MESSAGES[rule.type] ?? '字段校验失败'
    }];
  }
  return [];
};

export const validateFormSchemaValues = (
  values: Record<string, unknown>,
  fieldRules: Record<string, FormSchemaValidationRule[]>
): FormSchemaValidationError[] => Object.entries(fieldRules).flatMap(([field, rules]) => (
  validateFormSchemaField(field, values[field], rules, values)
));
