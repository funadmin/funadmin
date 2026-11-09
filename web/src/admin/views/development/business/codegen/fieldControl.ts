import { controlMeta } from '@/views/form/registry';
import type { FormSchemaDocument } from '@/views/form/schema/types';
import { applyFieldPatches, updateNodeAtPath } from './fieldTable';

/** 业务表是否为采纳的已有表：Schema 文档 database.source 优先（与后端 FormCrudDefinitionFactory 一致），其次表单来源。 */
export const isAdoptedTable = (doc: FormSchemaDocument, sourceType: string | undefined): boolean => {
  const source = (doc as FormSchemaDocument & { database?: { source?: unknown } }).database?.source;
  return (typeof source === 'string' && source !== '' ? source : sourceType) === 'adopted';
};

/**
 * 字段表格切换控件：控件、选项来源、属性按 nodeEdits.changeControl 重置；
 * 新建表与设计器 PropsPanel.onTypeChange 一致，物理类型改为控件默认列类型；采纳表保留实际列类型。
 */
export const changeFieldControl = (doc: FormSchemaDocument, path: string, type: string, adopted: boolean): FormSchemaDocument => {
  const next = applyFieldPatches(doc, { [path]: { component: type } });
  if (adopted || next === doc) return next;
  const columnType = controlMeta(type).defaultColumnType;
  if (!columnType) return next;
  return updateNodeAtPath(next, path, (node) => node.database?.columnType === columnType
    ? node
    : { ...node, database: { ...node.database, columnType } });
};

/** 开启查询时的默认查询方式：文本包含、日期范围、其他等于。 */
export const defaultFilterFor = (columnType: string): string => {
  const type = columnType.trim().toLowerCase();
  if (/^(var)?char\b|^(tiny|medium|long)?text\b/.test(type)) return 'like';
  if (/^(date|datetime|timestamp)\b/.test(type)) return 'date';
  return 'eq';
};
