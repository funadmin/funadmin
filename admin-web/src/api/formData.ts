import http from '@/utils/http';
import type { FormDefinition, FormFieldDef } from '@/api/form';

export interface FormDataMeta {
  form: FormDefinition;
  fields: FormFieldDef[];
}

const PREFIX = '/form/data';

export const formDataApi = {
  meta: (key: string) => http.get<FormDataMeta>(`${PREFIX}/meta/${key}`),
  index: (key: string, params: Record<string, unknown>) => http.get<{ list: Record<string, unknown>[]; total: number }>(`${PREFIX}/index/${key}`, params),
  export: (key: string, params: Record<string, unknown>) => http.get<{ list: Record<string, unknown>[] }>(`${PREFIX}/export/${key}`, params),
  detail: (key: string, id: number) => http.get<{ row: Record<string, unknown>; children: Record<string, { list: Record<string, unknown>[]; total: number }> }>(`${PREFIX}/detail/${key}/${id}`),
  options: (key: string, field: string) => http.get<{ options: Array<{ label: string; value: string | number }> }>(`${PREFIX}/options/${key}/${field}`),
  sub: (key: string, relation: string, id: number, params: Record<string, unknown>) =>
    http.get<{ list: Record<string, unknown>[]; total: number }>(`${PREFIX}/sub/${key}/${relation}/${id}`, params),
  create: (key: string, data: Record<string, unknown>) => http.post<{ id: number }>(`${PREFIX}/create/${key}`, { data }),
  update: (key: string, id: number, data: Record<string, unknown>) => http.post<{ id: number }>(`${PREFIX}/update/${key}/${id}`, { data }),
  remove: (key: string, id: number) => http.post<{ removed: number; mode: string }>(`${PREFIX}/remove/${key}`, { id })
};
