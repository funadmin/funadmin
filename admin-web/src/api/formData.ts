import http from '@/utils/http';
import type { FormDefinition, FormFieldDef } from '@/api/form';
import type { FormSchemaDocument } from '@/views/form/schema/types';

export type FormRecordId = string | number;

export interface FormDataMutationResult {
  id: FormRecordId;
  primaryKey: string;
}

export interface FormDataMeta {
  form: FormDefinition;
  fields: FormFieldDef[];
  primaryKey: { name: string; type: 'integer' | 'string' };
  schema: FormSchemaDocument;
  schemaHash: string;
  etag: string;
  categoryOptions?: Array<{ label: string; value: string | number; disabled?: boolean }>;
}

export interface FormLeftTreeResult {
  nodes: Array<{ id: FormRecordId; value: FormRecordId; label: string; parent: FormRecordId | null }>;
  sourceKey: string;
  schemaHash: string;
  actions: Partial<Record<'create' | 'addChild' | 'edit' | 'delete', boolean>>;
}

export interface FormFieldError {
  path: string;
  message: string;
}

const PREFIX = '/form/data';

export const formDataApi = {
  leftTree: (key: string) => http.get<FormLeftTreeResult>(`${PREFIX}/left-tree/${key}`),
    leftTreeForm: (key: string, operation: 'create' | 'addChild' | 'edit', id: FormRecordId, schemaHash: string, optionField = '', context: Record<string, unknown> = {}) =>
      http.get<{ meta: FormDataMeta; row: Record<string, unknown>; options?: Array<{ label: string; value: string | number }>; total?: number }>(`${PREFIX}/left-tree-form/${key}/${operation}`, { id, schemaHash, optionField, context }),
  mutateLeftTree: (key: string, operation: 'create' | 'addChild' | 'edit' | 'delete', id: FormRecordId, data: Record<string, unknown>, schemaHash: string, sourceSchemaHash: string) =>
    http.post(`${PREFIX}/left-tree/${key}/${operation}`, { id, data, schemaHash, sourceSchemaHash }),
  meta: (key: string) => http.get<FormDataMeta>(`${PREFIX}/meta/${key}`),
  index: (key: string, params: Record<string, unknown>) => http.get<{ list: Record<string, unknown>[]; total: number }>(`${PREFIX}/index/${key}`, params),
  export: (key: string, params: Record<string, unknown>) => http.get<{ list: Record<string, unknown>[] }>(`${PREFIX}/export/${key}`, params),
  detail: (key: string, id: string | number) => http.get<{ row: Record<string, unknown>; children: Record<string, { list: Record<string, unknown>[]; total: number }> }>(`${PREFIX}/detail/${key}/${id}`),
  options: (key: string, field: string, params: Record<string, unknown> = {}, signal?: AbortSignal) =>
    http.get<{ options: Array<{ label: string; value: string | number; disabled?: boolean }>; total?: number }>(
      `${PREFIX}/options/${key}/${field}`,
      params,
      { signal }
    ),
  validate: (key: string, field: string, validator: string, value: unknown, values: Record<string, unknown>, params: Record<string, unknown>, signal?: AbortSignal) =>
    http.post<{ valid: boolean; fieldErrors: FormFieldError[] }>(
      `${PREFIX}/validate/${key}/${field}`,
      { validator, value, values, params },
      { signal, requestOptions: { showErrorMsg: false } }
    ),
  sub: (key: string, relation: string, id: FormRecordId, params: Record<string, unknown>) =>
    http.get<{ list: Record<string, unknown>[]; total: number }>(`${PREFIX}/sub/${key}/${relation}/${id}`, params),
  action: (key: string, action: string, parameters: Record<string, unknown>, idempotencyKey = crypto.randomUUID(), signal?: AbortSignal) =>
    http.post<{ result: unknown }>(`${PREFIX}/action/${key}/${action}`, { parameters }, {
      signal,
      headers: { 'Idempotency-Key': idempotencyKey }
    }),
  create: (key: string, data: Record<string, unknown>, include: string[] = [], schemaHash = '') => http.post<FormDataMutationResult>(`${PREFIX}/create/${key}`, { data, include, schemaHash }, { requestOptions: { showErrorMsg: false } }),
  update: (key: string, id: FormRecordId, data: Record<string, unknown>, include: string[] = [], schemaHash = '') => http.post<FormDataMutationResult>(`${PREFIX}/update/${key}/${id}`, { data, include, schemaHash }, { requestOptions: { showErrorMsg: false } }),
  remove: (key: string, id: string | number, schemaHash = '') => http.post<{ removed: number; mode: string }>(`${PREFIX}/remove/${key}`, { id, schemaHash })
};
