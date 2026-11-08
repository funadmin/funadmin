import { describe, expect, it } from 'vitest';
import type { FormLeftTreeConfiguration, FormSchemaDocument } from './types';
import { leftTreeMissingLabels, sanitizeLeftTreeForSave } from './leftTreeGuard';

const tree = (patch: Partial<FormLeftTreeConfiguration> = {}): FormLeftTreeConfiguration => ({
  enabled: true,
  source: { type: 'current' },
  mapping: { valueField: 'id', labelField: 'title', targetField: 'category_id' },
  ...patch
});
const doc = (leftTree?: FormLeftTreeConfiguration): FormSchemaDocument => ({
  schemaVersion: 2, key: 'goods', title: '商品', nodes: [],
  list: { tools: { refresh: true }, ...(leftTree ? { leftTree } : {}) }
});

describe('sanitizeLeftTreeForSave', () => {
  it('已开启但必填映射为空：返回关闭的副本并保留映射草稿，列出缺少的键', () => {
    const input = doc(tree({ mapping: { valueField: '', labelField: '', targetField: '' } }));
    const snapshot = JSON.stringify(input);
    const result = sanitizeLeftTreeForSave(input);
    expect(result.pendingMissing).toEqual(['valueField', 'labelField', 'targetField']);
    expect(result.doc).not.toBe(input);
    expect(result.doc.list?.leftTree).toEqual({ ...input.list!.leftTree, enabled: false });
    expect(result.doc.list?.tools).toEqual({ refresh: true });
    // 不修改入参
    expect(JSON.stringify(input)).toBe(snapshot);
  });

  it('只缺部分映射时只列出缺少的键，可选映射（父级/排序）为空不算缺少', () => {
    const result = sanitizeLeftTreeForSave(doc(tree({ mapping: { valueField: 'id', labelField: 'title', targetField: '', parentField: '', sortField: '' } })));
    expect(result.pendingMissing).toEqual(['targetField']);
    expect(result.doc.list?.leftTree?.enabled).toBe(false);
    expect(result.doc.list?.leftTree?.mapping).toEqual({ valueField: 'id', labelField: 'title', targetField: '', parentField: '', sortField: '' });
  });

  it('映射对象缺失时视为三项全缺', () => {
    const broken = { ...tree(), mapping: undefined } as unknown as FormLeftTreeConfiguration;
    expect(sanitizeLeftTreeForSave(doc(broken)).pendingMissing).toEqual(['valueField', 'labelField', 'targetField']);
  });

  it('映射完整、未开启、没有分类树或没有 list 时原样返回同一引用', () => {
    for (const input of [doc(tree()), doc(tree({ enabled: false, mapping: { valueField: '', labelField: '', targetField: '' } })), doc(), { schemaVersion: 2, key: 'a', title: 'a', nodes: [] } as FormSchemaDocument]) {
      const result = sanitizeLeftTreeForSave(input);
      expect(result.doc).toBe(input);
      expect(result.pendingMissing).toEqual([]);
    }
  });
});

describe('leftTreeMissingLabels', () => {
  it('按固定顺序用 / 连接字段名称', () => {
    const t = (_key: string, fallback: string) => fallback;
    expect(leftTreeMissingLabels(t, ['valueField', 'labelField', 'targetField'])).toBe('来源主键 / 显示字段 / 右表关联字段');
    expect(leftTreeMissingLabels(t, ['targetField'])).toBe('右表关联字段');
  });
});
