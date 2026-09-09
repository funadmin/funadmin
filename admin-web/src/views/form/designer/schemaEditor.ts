import type { FormSchemaDocument, FormSchemaNode } from '@/api/form';
import { evaluateCondition, type Condition } from '../runtime/conditionEvaluator';

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

export interface DesignerDebugState {
  previewValues: Record<string, unknown>;
  conditionHits: Array<{ nodeId: string; matched: boolean; action: string; target: string }>;
  actionTrace: Array<{ nodeId: string; event: string; type: string; status: 'configured' }>;
  dataSources: Array<{ nodeId: string; status: 'configured'; kind: string }>;
  validationResults: Array<{ nodeId: string; field: string; valid: boolean; message: string }>;
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

const validationPassed = (type: string, value: unknown, expected: unknown): boolean => {
  if (type === 'required') return value !== null && value !== undefined && value !== '' && (!Array.isArray(value) || value.length > 0);
  if (type === 'minlen') return typeof value === 'string' && value.length >= Number(expected);
  if (type === 'maxlen') return typeof value === 'string' && value.length <= Number(expected);
  if (type === 'min') return typeof value === 'number' && value >= Number(expected);
  if (type === 'max') return typeof value === 'number' && value <= Number(expected);
  if (type === 'pattern' || type === 'regex') {
    try { return typeof value === 'string' && new RegExp(String(expected ?? '')).test(value); } catch { return false; }
  }
  return true;
};

/** 设计器专用纯调试器：只解释配置，不触发运行时动作或网络请求。 */
export const buildDesignerDebugState = (
  schema: FormSchemaDocument,
  values: Readonly<Record<string, unknown>>
): DesignerDebugState => {
  const state: DesignerDebugState = {
    previewValues: { ...values }, conditionHits: [], actionTrace: [], dataSources: [], validationResults: []
  };
  const pending = [...schema.nodes];
  while (pending.length) {
    const node = pending.shift()!;
    pending.unshift(...node.children);
    for (const entry of node.conditions ?? []) {
      const rule = entry as unknown as { when?: Condition; then?: { action?: unknown; target?: unknown } };
      const condition = rule.when ?? entry as unknown as Condition;
      let matched = false;
      try { matched = evaluateCondition(condition, values); } catch { matched = false; }
      state.conditionHits.push({
        nodeId: node.id,
        matched,
        action: String(rule.then?.action ?? ''),
        target: String(rule.then?.target ?? node.field ?? '')
      });
    }
    for (const [event, actions] of Object.entries(node.events ?? {})) {
      for (const action of actions as Array<{ type: string }>) {
        state.actionTrace.push({ nodeId: node.id, event, type: action.type, status: 'configured' });
      }
    }
    if (node.dataSource) {
      state.dataSources.push({
        nodeId: node.id,
        status: 'configured',
        kind: String(node.dataSource.kind ?? node.dataSource.mode ?? 'unknown')
      });
    }
    const field = String(node.field ?? '');
    for (const rule of node.validation ?? []) {
      const valid = validationPassed(rule.type, values[field], rule.value);
      state.validationResults.push({ nodeId: node.id, field, valid, message: valid ? '' : String(rule.message ?? rule.type) });
    }
  }
  return state;
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
