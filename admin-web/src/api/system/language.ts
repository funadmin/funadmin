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
  messages: Record<string, string>;
}

export const languageApi = {
  pack: (locale: string, signal?: AbortSignal) =>
    http.get<LanguagePack>(`${PREFIX}/pack`, { locale }, signal ? { signal } : undefined),
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
