import type { FormFieldDef } from '@/api/form';

/** 属性面板只读摘要：把校验规则 / 联动条件写成一句话。仅用于展示，不改写字段数据。 */
export type SummaryTranslate = (key: string, named: Record<string, unknown>, fallback: string) => string;

export interface ValidationSummaryEntry {
  type: string;
  value: unknown;
  text: string;
}

const K = 'formDesigner.propsPanel.';
const display = (value: unknown): string => {
  if (typeof value === 'string') return value;
  if (Array.isArray(value)) return value.map(display).join(' / ');
  return JSON.stringify(value) ?? String(value);
};

const describeFormat = (value: unknown, tr: SummaryTranslate): string => {
  if (value === 'email') return tr(`${K}ruleEmail`, {}, '邮箱格式');
  if (value === 'url') return tr(`${K}ruleUrl`, {}, '网址格式');
  return tr(`${K}ruleFormat`, { n: display(value) }, '格式 {n}');
};

/** 单条校验规则的说明；返回空串表示该规则只是其他规则的修饰（如 type=array），摘要中省略。 */
export const describeValidationRule = (type: string, value: unknown, rules: Record<string, unknown>, tr: SummaryTranslate): string => {
  const n = display(value);
  const items = rules.type === 'array';
  switch (type) {
    case 'required': return tr(`${K}ruleRequired`, {}, '必填');
    case 'min': return items ? tr(`${K}ruleMinItems`, { n }, '最少 {n} 项') : tr(`${K}ruleMin`, { n }, '最小 {n}');
    case 'max': return items ? tr(`${K}ruleMaxItems`, { n }, '最多 {n} 项') : tr(`${K}ruleMax`, { n }, '最大 {n}');
    case 'minLength':
    case 'minlen': return items ? tr(`${K}ruleMinItems`, { n }, '最少 {n} 项') : tr(`${K}ruleMinLength`, { n }, '最少 {n} 字符');
    case 'maxLength':
    case 'maxlen': return items ? tr(`${K}ruleMaxItems`, { n }, '最多 {n} 项') : tr(`${K}ruleMaxLength`, { n }, '最大 {n} 字符');
    case 'length': return tr(`${K}ruleLength`, { n }, '长度 {n}');
    case 'pattern':
    case 'regex': return tr(`${K}rulePattern`, { n }, '正则 {n}');
    case 'email': return value === true ? tr(`${K}ruleEmail`, {}, '邮箱格式') : '';
    case 'format': return describeFormat(value, tr);
    case 'type':
      if (value === 'email' || value === 'url') return describeFormat(value, tr);
      return value === 'array' ? '' : tr(`${K}ruleType`, { n }, '类型 {n}');
    case 'enum': return tr(`${K}ruleEnum`, { n }, '可选值 {n}');
    case 'precision': return tr(`${K}rulePrecision`, { n }, '精度 {n} 位');
    case 'same': return tr(`${K}ruleSame`, { n }, '与 {n} 一致');
    case 'different': return tr(`${K}ruleDifferent`, { n }, '与 {n} 不同');
    case 'before': return tr(`${K}ruleBefore`, { n }, '早于 {n}');
    case 'after': return tr(`${K}ruleAfter`, { n }, '晚于 {n}');
    default: return value === true ? type : `${type} ${n}`;
  }
};

/** 与改版前相同的规则来源：form_required 开关 + validate_rules（其中的 required 键由开关代表）。 */
export const validationEntries = (field: FormFieldDef, tr: SummaryTranslate): ValidationSummaryEntry[] => {
  const rules = field.validate_rules ?? {};
  return [
    ...(field.form_required === 1 ? [{ type: 'required', value: true as unknown }] : []),
    ...Object.entries(rules).filter(([type]) => type !== 'required').map(([type, value]) => ({ type, value }))
  ].map((rule) => ({ ...rule, text: describeValidationRule(rule.type, rule.value, rules, tr) }));
};

const OPERATORS: Record<string, [string, string]> = {
  eq: ['opEq', '='],
  neq: ['opNeq', '≠'],
  gt: ['opGt', '>'],
  gte: ['opGte', '≥'],
  lt: ['opLt', '<'],
  lte: ['opLte', '≤'],
  in: ['opIn', '属于'],
  notIn: ['opNotIn', '不属于'],
  contains: ['opContains', '包含'],
  startsWith: ['opStartsWith', '开头是'],
  endsWith: ['opEndsWith', '结尾是'],
  matches: ['opMatches', '匹配'],
  empty: ['opEmpty', '为空'],
  notEmpty: ['opNotEmpty', '不为空']
};
const UNARY = new Set(['empty', 'notEmpty']);
const ACTIONS: Record<string, [string, string]> = {
  show: ['actShow', '显示'],
  hide: ['actHide', '隐藏'],
  enable: ['actEnable', '可编辑'],
  disable: ['actDisable', '禁用'],
  setRequired: ['actSetRequired', '设为必填'],
  setValue: ['actSetValue', '赋值'],
  clearValue: ['actClearValue', '清空']
};

const asRecord = (value: unknown): Record<string, unknown> | null => (value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : null);

export const describeCondition = (input: unknown, tr: SummaryTranslate, nested = false): string => {
  const condition = asRecord(input);
  if (!condition) return '';
  const op = String(condition.op ?? '');
  if (op === 'and' || op === 'or') {
    const children = (Array.isArray(condition.conditions) ? condition.conditions : [])
      .map((child) => describeCondition(child, tr, true))
      .filter(Boolean);
    const joiner = op === 'and' ? tr(`${K}condAnd`, {}, '且') : tr(`${K}condOr`, {}, '或');
    const text = children.join(` ${joiner} `);
    return nested && children.length > 1 ? `(${text})` : text;
  }
  if (op === 'not') {
    const inner = describeCondition(condition.condition, tr, true);
    return inner ? tr(`${K}condNot`, { inner }, '非 {inner}') : '';
  }
  const field = String(condition.field ?? '');
  if (!field && !op) return '';
  const [key, fallback] = OPERATORS[op] ?? ['', op];
  const label = key ? tr(`${K}${key}`, {}, fallback) : fallback;
  return UNARY.has(op) ? `${field} ${label}` : `${field} ${label} ${display(condition.value)}`;
};

export const describeConditionRule = (input: unknown, tr: SummaryTranslate): string => {
  const rule = asRecord(input);
  const when = describeCondition(rule?.when, tr);
  const actionName = String(asRecord(rule?.then)?.action ?? '');
  const [key, fallback] = ACTIONS[actionName] ?? ['', actionName];
  const action = key ? tr(`${K}${key}`, {}, fallback) : fallback;
  if (!action) return tr(`${K}condNoAction`, {}, '未设置动作');
  return when ? tr(`${K}condRule`, { when, action }, '{when} 时{action}') : action;
};
