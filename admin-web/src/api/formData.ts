import http from '@/utils/http';
import type { FormDefinition, FormFieldDef } from '@/api/form';

export type FormRecordId = string | number;

export interface FormDataMutationResult {
  id: FormRecordId;
  primaryKey: string;
}

export interface FormDataMeta {
  form: FormDefinition;
  fields: FormFieldDef[];
  primaryKey: { name: string; type: 'integer' | 'string' };
}

const PREFIX = '/form/data';

export const formDataApi = {
  meta: (key: string) => http.get<FormDataMeta>(`${PREFIX}/meta/${key}`),
  index: (key: string, params: Record<string, unknown>) => http.get<{ list: Record<string, unknown>[]; total: number }>(`${PREFIX}/index/${key}`, params),
  export: (key: string, params: Record<string, unknown>) => http.get<{ list: Record<string, unknown>[] }>(`${PREFIX}/export/${key}`, params),
  detail: (key: string, id: string | number) => http.get<{ row: Record<string, unknown>; children: Record<string, { list: Record<string, unknown>[]; total: number }> }>(`${PREFIX}/detail/${key}/${id}`),
  options: (key: string, field: string) => http.get<{ options: Array<{ label: string; value: string | number }> }>(`${PREFIX}/options/${key}/${field}`),
  sub: (key: string, relation: string, id: FormRecordId, params: Record<string, unknown>) =>
    http.get<{ list: Record<string, unknown>[]; total: number }>(`${PREFIX}/sub/${key}/${relation}/${id}`, params),
  create: (key: string, data: Record<string, unknown>) => http.post<FormDataMutationResult>(`${PREFIX}/create/${key}`, { data }),
  update: (key: string, id: FormRecordId, data: Record<string, unknown>) => http.post<FormDataMutationResult>(`${PREFIX}/update/${key}/${id}`, { data }),
  remove: (key: string, id: string | number) => http.post<{ removed: number; mode: string }>(`${PREFIX}/remove/${key}`, { id })
};
