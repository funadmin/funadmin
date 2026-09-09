import { describe, expect, it } from 'vitest';
import {
  ACTION_PARAMETER_SCHEMAS,
  DATA_SOURCE_KINDS,
  VALIDATION_TYPES,
  createConditionRule,
  createDataSource,
  createEventAction,
  createValidationRule,
  detectConditionCycles,
  normalizePropertySchema,
  patchDynamicProperty
} from './structuredEditor';

describe('表单设计器结构化编辑模型', () => {
  it('根据组件 propertySchema 生成动态属性字段', () => {
    const fields = normalizePropertySchema({
      type: 'object',
      properties: {
        max: { type: 'integer', title: '最大值', minimum: 1 },
        readonly: { type: 'boolean', title: '只读' },
        size: { type: 'string', enum: ['small', 'large'] }
      }
    });

    expect(fields).toEqual([
      expect.objectContaining({ name: 'max', type: 'number', title: '最大值', minimum: 1 }),
      expect.objectContaining({ name: 'readonly', type: 'boolean' }),
      expect.objectContaining({ name: 'size', type: 'select', options: ['small', 'large'] })
    ]);
    expect(patchDynamicProperty({ max: 5 }, 'max', undefined)).toEqual({});
  });

  it('验证规则包含完整结构化字段默认值', () => {
    expect(VALIDATION_TYPES).toContain('required');
    expect(createValidationRule()).toEqual({
      type: 'required', trigger: ['change'], message: '', value: '', condition: '', severity: 'error', bail: true
    });
  });

  it('联动支持 and/or 条件组和循环检测', () => {
    expect(createConditionRule()).toEqual({
      when: { op: 'and', conditions: [{ field: '', op: 'eq', value: '' }] },
      then: { action: 'show', target: '' }
    });
    expect(detectConditionCycles([
      { field: 'province', conditions: [{ then: { target: 'city' } }] },
      { field: 'city', conditions: [{ then: { target: 'province' } }] }
    ])).toEqual(['city', 'province']);
  });

  it('事件动作使用白名单和动态参数', () => {
    expect(createEventAction('request')).toEqual({ type: 'request', key: '', concurrency: 'latest' });
    expect(ACTION_PARAMETER_SCHEMAS.request.map((item) => item.name)).toEqual(['key', 'concurrency']);
    expect(ACTION_PARAMETER_SCHEMAS.navigate.map((item) => item.name)).toEqual(['to']);
  });

  it('数据源覆盖注册键、映射、依赖、搜索分页缓存与旧值策略', () => {
    expect(DATA_SOURCE_KINDS).toEqual(['static', 'dictionary', 'department', 'user', 'relation', 'endpoint', 'computed']);
    expect(createDataSource('endpoint')).toEqual({
      kind: 'endpoint', endpoint: '', params: {}, response: { items: 'data', label: 'label', value: 'value' },
      dependsOn: [], searchable: false, pagination: { pageSize: 20 }, cacheTtl: 0, staleValue: 'clear'
    });
  });
});
