import { describe, expect, it } from 'vitest';
import {
  buildSubmissionPayload,
  emptyRuntimeValues,
  resolveSubmissionInclude,
  sanitizeRuntimeRecord,
  stableRuntimeValues
} from './submissionPolicy';
import type { FormFieldDef } from '@/api/form';

const field = (name: string, overrides: Partial<FormFieldDef> = {}): FormFieldDef => ({
  field_name: name, label: name, type: 'input', column_type: 'varchar(255)', nullable: 1,
  default_value: '', comment: '', unsigned: 0, index_type: 'none', placeholder: '',
  relation_type: 'none', relation_table: '', relation_label_field: '', relation_value_field: '',
  relation_multiple: 0, relation_on_delete: 'restrict', list_show: 1, list_sort: 0,
  list_filter: '', list_formatter: '', list_width: 0, form_show: 1, form_required: 0,
  form_group: '', form_span: 24, form_readonly: 0, sort_order: 0, ...overrides
});

describe('表单运行时提交与敏感字段治理', () => {
  const fields = [
    field('title'),
    field('hidden', { type: 'hidden' }),
    field('disabled', { control_props: { disabled: true } }),
    field('readonly', { form_readonly: 1 }),
    field('computed', { options_source: { kind: 'computed' } }),
    field('primary', { index_type: 'unique', control_props: { primary: true } }),
    field('system', { control_props: { system: true } }),
    field('secret', { type: 'password', default_value: 'never', control_props: { sensitive: true, writeOnly: true } })
  ];

  it('默认仅提交普通字段，显式 include 可纳入受治理字段', () => {
    const values = Object.fromEntries(fields.map((item) => [item.field_name, item.field_name]));
    expect(buildSubmissionPayload(fields, values)).toEqual({ title: 'title', secret: 'secret' });
    expect(buildSubmissionPayload(fields, values, ['readonly', 'computed'])).toEqual({
      title: 'title', readonly: 'readonly', computed: 'computed', secret: 'secret'
    });
  });

  it('敏感字段不从记录回显且不使用默认值', () => {
    expect(sanitizeRuntimeRecord(fields, { title: 'ok', secret: 'stored' })).toEqual({ title: 'ok' });
    expect(emptyRuntimeValues(fields)).toMatchObject({ title: '', secret: '' });
  });

  it('嵌套子表值变化时稳定序列化结果必须变化', () => {
    const initial = { title: '订单', items: [{ sku: 'A', quantity: 1 }] };
    const changed = { items: [{ quantity: 2, sku: 'A' }], title: '订单' };

    expect(stableRuntimeValues(initial)).not.toBe(stableRuntimeValues(changed));
    expect(stableRuntimeValues(initial)).toBe(stableRuntimeValues({ items: [{ quantity: 1, sku: 'A' }], title: '订单' }));
  });

  it('优先读取 v2 submit.include 并兼容旧 form_config', () => {
    const definition = {
      schema_document: { submit: { include: ['computed', 'system'] } },
      form_config: { submitInclude: ['readonly'] }
    };
    expect(resolveSubmissionInclude(definition)).toEqual(['computed', 'system']);
    expect(resolveSubmissionInclude({ form_config: { submitInclude: ['readonly'] } })).toEqual(['readonly']);
  });
});
