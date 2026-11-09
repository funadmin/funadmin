import { controlMeta } from '@/views/form/registry';
import type { FormSchemaDatabase, FormSchemaListColumn, FormSchemaNode, FormSchemaRelationConfig } from './types';

/**
 * 字段节点的纯函数编辑：只写对应的 v2 切片并合并回节点，不修改入参；
 * 没有任何值变化时返回原节点引用。
 */

/** 把 patch 中已定义的键合并进 current；所有键都未变化时返回 undefined。 */
const mergeSlice = <T extends object>(current: T | undefined, patch: Partial<T>): T | undefined => {
  const entries = Object.entries(patch).filter(([, value]) => value !== undefined);
  const base = (current ?? {}) as Record<string, unknown>;
  if (entries.every(([key, value]) => key in base && Object.is(base[key], value))) return undefined;
  return { ...base, ...Object.fromEntries(entries) } as T;
};

export const withRequired = (node: FormSchemaNode, required: boolean): FormSchemaNode => {
  const rules = node.validation ?? [];
  if (rules.some((rule) => rule.type === 'required') === required) return node;
  return {
    ...node,
    validation: required ? [{ type: 'required', value: true }, ...rules] : rules.filter((rule) => rule.type !== 'required')
  };
};

export const patchList = (node: FormSchemaNode, patch: Partial<FormSchemaListColumn>): FormSchemaNode => {
  const list = mergeSlice(node.list, patch);
  return list ? { ...node, list } : node;
};

export const patchDatabase = (node: FormSchemaNode, patch: Partial<FormSchemaDatabase>): FormSchemaNode => {
  const database = mergeSlice(node.database, patch);
  return database ? { ...node, database } : node;
};

export const patchRelation = (node: FormSchemaNode, patch: Partial<FormSchemaRelationConfig>): FormSchemaNode => {
  const relation = mergeSlice(node.database?.relation, patch);
  return relation ? { ...node, database: { ...node.database, relation } } : node;
};

/** 布局编辑写入 layout.span / layout.group。 */
export const patchLayout = (node: FormSchemaNode, patch: { span?: number; group?: string }): FormSchemaNode => {
  const layout = mergeSlice(node.layout, patch);
  return layout ? { ...node, layout } : node;
};

const sameJson = (left: unknown, right: unknown): boolean => JSON.stringify(left) === JSON.stringify(right);

/**
 * 切换字段控件：选项来源与属性重置为注册表默认值，valueType 取自控件元信息（未声明则移除）。
 * resetColumnType 为 true 时 database.columnType 改为控件默认列类型（设计器新建表）；
 * 为 false 时保留原列类型（字段表格、采纳表不应静默改动已有列的物理类型）。
 */
export const changeControl = (node: FormSchemaNode, type: string, opts: { resetColumnType: boolean }): FormSchemaNode => {
  const meta = controlMeta(type);
  if (meta.type !== type) throw new Error(`未知控件：${type}`);
  if (meta.kind === 'layout' || meta.kind === 'relation-container') throw new Error(`字段不能切换为容器控件：${type}`);
  const next: FormSchemaNode = {
    ...node,
    type,
    dataSource: meta.defaultOptions === null ? null : structuredClone(meta.defaultOptions),
    props: structuredClone(meta.defaultProps ?? {})
  };
  if (meta.valueType) next.valueType = meta.valueType as FormSchemaNode['valueType'];
  else Reflect.deleteProperty(next, 'valueType');
  const database = opts.resetColumnType ? mergeSlice(node.database, { columnType: meta.defaultColumnType }) : undefined;
  if (database) next.database = database;
  const unchanged = !database
    && node.type === next.type
    && node.valueType === next.valueType
    && sameJson(node.props, next.props)
    && sameJson(node.dataSource, next.dataSource);
  return unchanged ? node : next;
};
