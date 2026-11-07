import { describe, expect, it } from 'vitest';
import type { FormSchemaDocument, FormSchemaNode } from '@/views/form/schema/types';
import { changeFieldControl, defaultFilterFor, isAdoptedTable } from './fieldControl';

const field = (id: string, type: string, name: string, columnType: string): FormSchemaNode => ({
  id, kind: 'field', type, field: name, title: name, children: [], validation: [], dataSource: null,
  props: {}, hidden: false, events: {}, conditions: [],
  database: { columnType, nullable: true, comment: name },
  list: { show: true, filter: '' }
});

const makeDoc = (): FormSchemaDocument => ({
  schemaVersion: 2, key: 'goods', title: '商品',
  nodes: [field('n_title', 'input', 'title', 'varchar(120)'), field('n_status', 'input', 'status', 'tinyint')]
});

describe('changeFieldControl', () => {
  it('新建表：与设计器 onTypeChange 一致，物理类型重置为控件默认列类型', () => {
    const doc = makeDoc();
    const next = changeFieldControl(doc, '/nodes/1', 'radio', false);
    expect(next.nodes[1].type).toBe('radio');
    expect(next.nodes[1].database?.columnType).toBe('varchar(100)');
    expect(next.nodes[1].database?.comment).toBe('status');
    expect(next.nodes[0]).toBe(doc.nodes[0]);
  });

  it('采纳表：保留物理列类型', () => {
    const next = changeFieldControl(makeDoc(), '/nodes/1', 'radio', true);
    expect(next.nodes[1].type).toBe('radio');
    expect(next.nodes[1].database?.columnType).toBe('tinyint');
  });

  it('控件未变化时返回原文档', () => {
    const doc = makeDoc();
    expect(changeFieldControl(doc, '/nodes/0', 'input', false)).toBe(doc);
  });
});

describe('isAdoptedTable', () => {
  it('Schema database.source 优先，其次表单来源', () => {
    const doc = makeDoc();
    expect(isAdoptedTable(doc, 'adopted')).toBe(true);
    expect(isAdoptedTable(doc, 'created')).toBe(false);
    expect(isAdoptedTable({ ...doc, database: { source: 'adopted' } } as FormSchemaDocument, 'created')).toBe(true);
    expect(isAdoptedTable({ ...doc, database: { source: 'created' } } as FormSchemaDocument, 'adopted')).toBe(false);
  });
});

describe('defaultFilterFor', () => {
  it('按物理类型给出默认查询方式', () => {
    expect(defaultFilterFor('varchar(120)')).toBe('like');
    expect(defaultFilterFor('text')).toBe('like');
    expect(defaultFilterFor('datetime')).toBe('date');
    expect(defaultFilterFor('date')).toBe('date');
    expect(defaultFilterFor('int')).toBe('eq');
    expect(defaultFilterFor('')).toBe('eq');
  });
});
