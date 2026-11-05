import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { createElementPlusValidationRules, isCompatibleSafePattern, validateFormSchemaField, validateFormSchemaValues } from './formSchemaDataValidator';

interface ValidationCase {
  name: string;
  field: string;
  values: Record<string, unknown>;
  rules: Array<{ type: string; value?: unknown; message?: string; when?: { field?: string; op: string; value?: unknown }; severity?: 'error' | 'warning'; bail?: boolean }>;
  valid: boolean;
  failedRules?: string[];
  message?: string;
  path?: string;
  severities?: string[];
}

interface ValidationFixture {
  protocolVersion: number;
  rules: string[];
  cases: ValidationCase[];
  unsafePatterns: string[];
}

const fixturePath = resolve(process.cwd(), '../tests/fixtures/form-schema-v2-validation.json');
const fixture = JSON.parse(readFileSync(fixturePath, 'utf8')) as ValidationFixture;

describe('FormSchema v2 双端统一验证协议', () => {
  it('共享 fixtures 明确声明协议版本并覆盖全部规则', () => {
    expect(fixture.protocolVersion).toBe(2);
    expect(new Set(fixture.rules).size).toBe(20);
  });

  it.each(fixture.cases)('$name', (testCase) => {
    const errors = validateFormSchemaField(
      testCase.field,
      testCase.values[testCase.field],
      testCase.rules,
      testCase.values
    );

    expect(errors.length === 0).toBe(testCase.valid);
    if (testCase.valid) return;
    expect(errors.map((error) => error.rule)).toEqual(testCase.failedRules);
    expect(errors[0]?.field).toBe(testCase.path ?? testCase.field);
    if (testCase.message) expect(errors[0]?.message).toBe(testCase.message);
    if (testCase.severities) expect(errors.map((error) => error.severity)).toEqual(testCase.severities);
  });

  it.each(fixture.unsafePatterns)('快速拒绝不安全或不兼容正则 %s', (pattern) => {
    expect(isCompatibleSafePattern(pattern)).toBe(false);
    const errors = validateFormSchemaField('value', 'aaaaaaaaaaaaaaaaaaaaaaaa!', [{ type: 'pattern', value: pattern }], { value: 'aaaaaaaaaaaaaaaaaaaaaaaa!' });
    expect(errors[0]?.rule).toBe('pattern');
  });

  it('为 Element Plus 保留同类型规则并注册 async key', async () => {
    const registered: string[] = [];
    const rules = createElementPlusValidationRules('value', [
      { type: 'pattern', value: '^[0-9]+$' },
      { type: 'pattern', value: '^.{5,}$' },
      { type: 'async', validator: { key: 'account.unique' } }
    ], { value: 'ABC' }, (key) => {
      registered.push(key);
      return async () => undefined;
    });
    expect(rules).toHaveLength(3);
    expect(registered).toEqual(['account.unique']);
    await expect(rules[0]?.validator?.({}, 'ABC', () => undefined)).rejects.toThrow();
  });

  it('async key 始终注册但 when 未命中时不调用远端验证', async () => {
    let calls = 0;
    const values = { email: 'used', mode: 'strict' };
    const rules = createElementPlusValidationRules('email', [{
      type: 'async',
      validator: { key: 'account.unique' },
      when: { field: 'mode', op: 'eq', value: 'strict' }
    }], values, () => async () => { calls += 1; });

    expect(rules).toHaveLength(1);
    values.mode = 'relaxed';
    await rules[0]?.validator?.({}, 'used', () => undefined);
    expect(calls).toBe(0);
  });

  it('整表验证按字段规则顺序返回错误', () => {
    const errors = validateFormSchemaValues(
      { email: 'invalid', tags: 'not-an-array' },
      {
        email: [{ type: 'format', value: 'email' }],
        tags: [{ type: 'array' }]
      }
    );
    expect(errors.map((error) => error.field)).toEqual(['email', 'tags']);
  });
});
