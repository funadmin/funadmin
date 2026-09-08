import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { validateFormSchemaField, validateFormSchemaValues } from './formSchemaDataValidator';

interface ValidationCase {
  name: string;
  field: string;
  values: Record<string, unknown>;
  rules: Array<{ type: string; value?: unknown; message?: string }>;
  valid: boolean;
  failedRule?: string;
  message?: string;
}

interface ValidationFixture {
  protocolVersion: number;
  rules: string[];
  cases: ValidationCase[];
}

const fixturePath = resolve(process.cwd(), '../tests/fixtures/form-schema-v2-validation.json');
const fixture = JSON.parse(readFileSync(fixturePath, 'utf8')) as ValidationFixture;

describe('FormSchema v2 双端统一验证协议', () => {
  it('共享 fixtures 明确声明协议版本并覆盖全部规则', () => {
    expect(fixture.protocolVersion).toBe(1);
    expect(new Set(fixture.rules).size).toBe(17);
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
    expect(errors[0]).toMatchObject({ field: testCase.field, rule: testCase.failedRule });
    if (testCase.message) expect(errors[0]?.message).toBe(testCase.message);
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
