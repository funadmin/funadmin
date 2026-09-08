export type ComparisonOperator =
  | 'eq' | 'neq' | 'gt' | 'gte' | 'lt' | 'lte' | 'in' | 'notIn'
  | 'contains' | 'startsWith' | 'endsWith' | 'empty' | 'notEmpty' | 'matches';

export interface ComparisonCondition {
  field: string;
  op: ComparisonOperator;
  value?: unknown;
}

export interface AndCondition {
  op: 'and';
  conditions: Condition[];
}

export interface OrCondition {
  op: 'or';
  conditions: Condition[];
}

export interface NotCondition {
  op: 'not';
  condition: Condition;
}

export type Condition = ComparisonCondition | AndCondition | OrCondition | NotCondition;
export type ConditionValues = Readonly<Record<string, unknown>>;

const readPath = (values: ConditionValues, path: string): unknown => path
  .split('.')
  .filter(Boolean)
  .reduce<unknown>((current, segment) => {
    if (current === null || typeof current !== 'object') return undefined;
    return (current as Record<string, unknown>)[segment];
  }, values);

const isEmpty = (value: unknown): boolean => value === null
  || value === undefined
  || value === ''
  || (Array.isArray(value) && value.length === 0);

const compare = (condition: ComparisonCondition, actual: unknown): boolean => {
  const expected = condition.value;
  switch (condition.op) {
    case 'eq': return actual === expected;
    case 'neq': return actual !== expected;
    case 'gt': return typeof actual === 'number' && typeof expected === 'number' && actual > expected;
    case 'gte': return typeof actual === 'number' && typeof expected === 'number' && actual >= expected;
    case 'lt': return typeof actual === 'number' && typeof expected === 'number' && actual < expected;
    case 'lte': return typeof actual === 'number' && typeof expected === 'number' && actual <= expected;
    case 'in': return Array.isArray(expected) && expected.includes(actual);
    case 'notIn': return Array.isArray(expected) && !expected.includes(actual);
    case 'contains':
      if (Array.isArray(actual)) return actual.includes(expected);
      return typeof actual === 'string' && typeof expected === 'string' && actual.includes(expected);
    case 'startsWith': return typeof actual === 'string' && typeof expected === 'string' && actual.startsWith(expected);
    case 'endsWith': return typeof actual === 'string' && typeof expected === 'string' && actual.endsWith(expected);
    case 'empty': return isEmpty(actual);
    case 'notEmpty': return !isEmpty(actual);
    case 'matches':
      if (typeof actual !== 'string' || typeof expected !== 'string') return false;
      try {
        return new RegExp(expected).test(actual);
      } catch {
        return false;
      }
    default:
      throw new Error(`条件操作符未注册：${String((condition as ComparisonCondition).op)}`);
  }
};

/** 纯函数求值 FormSchema v2 声明式条件，不执行表达式或脚本。 */
export const evaluateCondition = (condition: Condition, values: ConditionValues): boolean => {
  switch (condition.op) {
    case 'and': return condition.conditions.every((item) => evaluateCondition(item, values));
    case 'or': return condition.conditions.some((item) => evaluateCondition(item, values));
    case 'not': return !evaluateCondition(condition.condition, values);
    default: return compare(condition, readPath(values, condition.field));
  }
};
