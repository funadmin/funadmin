import { describe, expect, it } from 'vitest';
import type { FormSchemaNode } from '../schema/types';
import {
  buildSubmissionPayload,
  emptyRuntimeValues,
  resolveSubmissionInclude,
  sanitizeRuntimeRecord,
  stableRuntimeValues
} from './submissionPolicy';

const node = (name: string, overrides: Partial<FormSchemaNode> = {}): FormSchemaNode => ({
  id: `node_${name}`, kind: 'field', type: 'input', field: name, title: name, children: [], ...overrides
});

const layout = (id: string, children: FormSchemaNode[], type = 'group'): FormSchemaNode => ({
  id, kind: 'layout', type, field: null, title: id, children
});

const valuesOf = (names: string[]): Record<string, unknown> => Object.fromEntries(names.map((name) => [name, name]));

describe('表单运行时提交与敏感字段治理', () => {
  const nodes = [
    node('title'),
    node('hidden', { type: 'hidden' }),
    node('disabled', { props: { disabled: true } }),
    node('readonly', { disabled: true }),
    node('computed', { dataSource: { kind: 'computed' } }),
    node('computed_prop', { props: { computed: true } }),
    node('primary', { props: { primary: true } }),
    node('system', { props: { system: true } }),
    node('secret', { type: 'password', defaultValue: 'never', props: { sensitive: true, writeOnly: true } })
  ];
  const names = ['title', 'hidden', 'disabled', 'readonly', 'computed', 'computed_prop', 'primary', 'system', 'secret'];

  it('默认仅提交普通字段，显式 include 可纳入受治理字段', () => {
    expect(buildSubmissionPayload(nodes, valuesOf(names))).toEqual({ title: 'title', secret: 'secret' });
    expect(buildSubmissionPayload(nodes, valuesOf(names), ['readonly', 'computed'])).toEqual({
      title: 'title', readonly: 'readonly', computed: 'computed', secret: 'secret'
    });
  });

  it('节点 hidden 显示隐藏保留默认值和提交，不改写业务字段名', () => {
    const hidden = node('hidden_default', { hidden: true, defaultValue: '隐藏默认值' });
    expect(emptyRuntimeValues([hidden])).toEqual({ hidden_default: '隐藏默认值' });
    expect(buildSubmissionPayload([hidden], { hidden_default: '联动值', hiddenDefault: '错误别名' })).toEqual({ hidden_default: '联动值' });
  });

  it('access 策略不能被显式 include 绕过', () => {
    const policyNodes = [
      node('hidden_include', { type: 'hidden' }),
      node('hidden_always', { type: 'hidden', access: { include: 'always' } }),
      node('hidden_never', { type: 'hidden', access: { include: 'never' } }),
      node('hidden_denied', { type: 'hidden', access: { write: ['secret:write'] } })
    ];
    const policyNames = policyNodes.map((item) => String(item.field));
    expect(buildSubmissionPayload(policyNodes, valuesOf(policyNames), policyNames)).toEqual({ hidden_include: 'hidden_include', hidden_always: 'hidden_always' });
    expect(buildSubmissionPayload(policyNodes, valuesOf(policyNames), policyNames, (permission) => permission === 'secret:write'))
      .toEqual({ hidden_include: 'hidden_include', hidden_always: 'hidden_always', hidden_denied: 'hidden_denied' });
  });

  it('excludes access.include never', () => {
    const fields = [node('title'), node('note', { access: { include: 'never' } })];
    expect(buildSubmissionPayload(fields, valuesOf(['title', 'note']), ['note'], () => true)).toEqual({ title: 'title' });
  });

  it('excludes node whose access.write permission is missing', () => {
    const fields = [node('title'), node('salary', { access: { write: ['hr:salary', 'hr:write'] } })];
    const values = valuesOf(['title', 'salary']);
    expect(buildSubmissionPayload(fields, values, [], (permission) => permission === 'hr:salary')).toEqual({ title: 'title' });
    expect(buildSubmissionPayload(fields, values, [], () => true)).toEqual({ title: 'title', salary: 'salary' });
  });

  it('includes nested field inside group', () => {
    const fields = [layout('tabs_1', [layout('group_1', [node('inner')])], 'tabs'), node('title')];
    expect(buildSubmissionPayload(fields, valuesOf(['inner', 'title', 'group_1']))).toEqual({ inner: 'inner', title: 'title' });
    expect(emptyRuntimeValues(fields)).toEqual({ inner: '', title: '' });
  });

  it('ignores children of repeatable', () => {
    const fields = [node('items', { type: 'repeatable', children: [node('sku')] }), node('title')];
    const values = { items: [{ sku: 'A' }], sku: 'leak', title: 'title' };
    expect(buildSubmissionPayload(fields, values)).toEqual({ items: [{ sku: 'A' }], title: 'title' });
    expect(emptyRuntimeValues(fields)).toEqual({ items: [], title: '' });
  });

  it('敏感字段不从记录回显且不使用默认值', () => {
    const fields = [node('title'), layout('group_1', [node('secret', { type: 'password', defaultValue: 'never' })])];
    expect(sanitizeRuntimeRecord(fields, { title: 'ok', secret: 'stored' })).toEqual({ title: 'ok' });
    expect(emptyRuntimeValues(fields)).toEqual({ title: '', secret: '' });
  });

  it('按控件类型给出空值', () => {
    const fields = [
      node('enabled', { type: 'switch', defaultValue: 1 }),
      node('count', { type: 'number', defaultValue: 3 }),
      node('lines', { type: 'subform' }),
      node('tags', { type: 'select', database: { relation: { type: 'has_many' } } }),
      node('remark', { defaultValue: '默认' })
    ];
    expect(emptyRuntimeValues(fields)).toEqual({ enabled: 0, count: undefined, lines: [], tags: [], remark: '默认' });
  });

  it('嵌套子表值变化时稳定序列化结果必须变化', () => {
    const initial = { title: '订单', items: [{ sku: 'A', quantity: 1 }] };
    const changed = { items: [{ quantity: 2, sku: 'A' }], title: '订单' };

    expect(stableRuntimeValues(initial)).not.toBe(stableRuntimeValues(changed));
    expect(stableRuntimeValues(initial)).toBe(stableRuntimeValues({ items: [{ quantity: 1, sku: 'A' }], title: '订单' }));
  });

  it('读取 v2 submit.include', () => {
    expect(resolveSubmissionInclude({ schema_document: { submit: { include: ['computed', 'system', 3] } } })).toEqual(['computed', 'system']);
    expect(resolveSubmissionInclude(null)).toEqual([]);
    expect(resolveSubmissionInclude({ schema_document: null })).toEqual([]);
  });
});
