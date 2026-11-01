import { describe, expect, it } from 'vitest';
import { evaluateCondition } from './conditionEvaluator';

describe('FormSchema v2 条件求值器', () => {
  const values = {
    profile: { age: 20, name: 'Alice' },
    status: 'active',
    tags: ['vip', 'new'],
    emptyText: '',
    missing: null
  };

  it('递归执行 and、or、not 逻辑条件', () => {
    expect(evaluateCondition({
      op: 'and',
      conditions: [
        { field: 'status', op: 'eq', value: 'active' },
        {
          op: 'or',
          conditions: [
            { field: 'profile.age', op: 'lt', value: 18 },
            { op: 'not', condition: { field: 'status', op: 'eq', value: 'disabled' } }
          ]
        }
      ]
    }, values)).toBe(true);
  });

  it.each([
    ['eq', 'status', 'active', true],
    ['neq', 'status', 'disabled', true],
    ['gt', 'profile.age', 18, true],
    ['gte', 'profile.age', 20, true],
    ['lt', 'profile.age', 21, true],
    ['lte', 'profile.age', 20, true],
    ['in', 'status', ['active', 'pending'], true],
    ['notIn', 'status', ['disabled', 'pending'], true],
    ['contains', 'tags', 'vip', true],
    ['contains', 'profile.name', 'lic', true],
    ['startsWith', 'profile.name', 'Ali', true],
    ['endsWith', 'profile.name', 'ice', true],
    ['empty', 'emptyText', undefined, true],
    ['empty', 'missing', undefined, true],
    ['notEmpty', 'tags', undefined, true],
    ['matches', 'profile.name', '^A[a-z]+$', true]
  ] as const)('执行 %s 比较符', (op, field, value, expected) => {
    expect(evaluateCondition({ field, op, value }, values)).toBe(expected);
  });

  it('对类型不兼容及无效正则安全返回 false', () => {
    expect(evaluateCondition({ field: 'profile.age', op: 'contains', value: 2 }, values)).toBe(false);
    expect(evaluateCondition({ field: 'profile.name', op: 'matches', value: '[' }, values)).toBe(false);
  });

  it('拒绝未知条件操作符', () => {
    expect(() => evaluateCondition({ field: 'status', op: 'execute' } as never, values))
      .toThrow('条件操作符未注册：execute');
  });
});
