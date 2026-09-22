import http from '@/utils/http';

const PREFIX = '/system/language';

export interface LanguageModel {
  id: number;
  name: string;
  isDefault: 0 | 1;
  status: 0 | 1;
  createdAt: string;
  updatedAt: string;
}

export interface LanguageQuery {
  page: number;
  pageSize: number;
  name?: string;
}

export interface LanguagePack {
  locale: string;
  version: number;
  unchanged?: boolean;
  messages: Record<string, string>;
}

export interface LanguageLineModel {
  id: number;
  locale: string;
  key: string;
  ns: string;
  value: string;
  updatedAt: string;
}

export interface LanguageLineQuery {
  page: number;
  pageSize: number;
  locale: string;
  keyword?: string;
  ns?: string;
}

export const languageApi = {
  pack: (locale: string, version = 0, signal?: AbortSignal) =>
    http.get<LanguagePack>(`${PREFIX}/pack`, version > 0 ? { locale, version } : { locale }, signal ? { signal } : undefined),
  namespaces: (locale: string) => http.get<string[]>(`${PREFIX}/ns`, { locale }),
  lines: (params: LanguageLineQuery) => http.get<API.PageResult<LanguageLineModel>>(`${PREFIX}/lines`, params),
  saveLine: (data: { locale: string; key: string; value: string }) =>
    http.post<{ id: number }>(`${PREFIX}/lines`, data, { requestOptions: { showSuccessMsg: true } }),
  removeLine: (id: number) =>
    http.delete<{ removed: number }>(`${PREFIX}/lines`, { id }, { requestOptions: { showSuccessMsg: true } }),
  list: (params: LanguageQuery) => http.get<API.PageResult<LanguageModel>>(PREFIX, { params }),
  detail: (id: number) => http.get<LanguageModel>(`${PREFIX}/${id}`),
  create: (data: Pick<LanguageModel, 'name'>) =>
    http.post<LanguageModel>(PREFIX, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Pick<LanguageModel, 'name'>) =>
    http.put<LanguageModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (id: number) =>
    http.delete<void>(`${PREFIX}/${id}`, undefined, { requestOptions: { showSuccessMsg: true } }),
  removeMany: (ids: number[]) =>
    http.delete<{ removed: number }>(PREFIX, { ids }, { requestOptions: { showSuccessMsg: true } })
};
