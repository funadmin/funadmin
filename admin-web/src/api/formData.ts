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

export interface FormFieldError {
  path: string;
  message: string;
}

const PREFIX = '/form/data';

export const formDataApi = {
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
  create: (key: string, data: Record<string, unknown>, include: string[] = []) => http.post<FormDataMutationResult>(`${PREFIX}/create/${key}`, { data, include }, { requestOptions: { showErrorMsg: false } }),
  update: (key: string, id: FormRecordId, data: Record<string, unknown>, include: string[] = []) => http.post<FormDataMutationResult>(`${PREFIX}/update/${key}/${id}`, { data, include }, { requestOptions: { showErrorMsg: false } }),
  remove: (key: string, id: string | number) => http.post<{ removed: number; mode: string }>(`${PREFIX}/remove/${key}`, { id })
};
