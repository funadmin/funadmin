import type { FormSchemaDocument, FormSchemaNode } from '@/api/form';

export interface SchemaParseResult {
  ok: boolean;
  schema: FormSchemaDocument | null;
  error: string;
}

export interface SchemaDebugSummary {
  nodes: number;
  fields: number;
  containers: number;
  validationRules: number;
  conditions: number;
  events: number;
  dataSources: number;
  maxDepth: number;
}

const isRecord = (value: unknown): value is Record<string, unknown> => (
  typeof value === 'object' && value !== null && !Array.isArray(value)
);

const isNode = (value: unknown): value is FormSchemaNode => {
  if (!isRecord(value)) return false;
  return typeof value.id === 'string'
    && (value.kind === 'field' || value.kind === 'layout')
    && typeof value.type === 'string'
    && typeof value.title === 'string'
    && Array.isArray(value.children)
    && value.children.every(isNode);
};

export const parseSchemaJson = (raw: string): SchemaParseResult => {
  let parsed: unknown;
  try {
    parsed = JSON.parse(raw);
  } catch (error) {
    return { ok: false, schema: null, error: `JSON 语法错误：${error instanceof Error ? error.message : '无法解析'}` };
  }
  if (!isRecord(parsed) || parsed.schemaVersion !== 2 || typeof parsed.key !== 'string'
    || typeof parsed.title !== 'string' || !Array.isArray(parsed.nodes) || !parsed.nodes.every(isNode)) {
    return { ok: false, schema: null, error: '仅支持结构完整的 FormSchema v2 文档' };
  }
  return { ok: true, schema: parsed as unknown as FormSchemaDocument, error: '' };
};

export const buildSchemaDebugSummary = (schema: FormSchemaDocument): SchemaDebugSummary => {
  const summary: SchemaDebugSummary = {
    nodes: 0, fields: 0, containers: 0, validationRules: 0,
    conditions: 0, events: 0, dataSources: 0, maxDepth: 0
  };
  const visit = (nodes: FormSchemaNode[], depth: number) => {
    for (const node of nodes) {
      summary.nodes += 1;
      summary.maxDepth = Math.max(summary.maxDepth, depth);
      if (node.kind === 'field') summary.fields += 1;
      if (node.children.length > 0 || node.kind === 'layout') summary.containers += 1;
      summary.validationRules += node.validation?.length ?? 0;
      summary.conditions += node.conditions?.length ?? 0;
      summary.events += Object.values(node.events ?? {}).reduce((count, actions) => count + actions.length, 0);
      if (node.dataSource) summary.dataSources += 1;
      visit(node.children, depth + 1);
    }
  };
  visit(schema.nodes, 1);
  return summary;
};
