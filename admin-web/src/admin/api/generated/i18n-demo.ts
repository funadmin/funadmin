import request from '@/utils/http';

export interface I18nDemoModel {
  id: number;
  name: string;
  status: number;
  remark: string;
  createdAt?: string;
  updatedAt?: string;
  deletedAt?: string;
}
export type I18nDemoModelId = number;
export interface I18nDemoModelQuery { page: number; pageSize: number; recycled?: 0 | 1; sort?: string; order?: 'asc' | 'desc'; [key: string]: string | number | undefined }
export type I18nDemoModelPayload = Partial<Omit<I18nDemoModel, 'id'>>;

export const i18nDemoApi = {
  listActions: (_key: string, location: import('@/views/form/schema/types').FormListButtonLocation, signal?: AbortSignal) => request.get<import('@/api/formData').FormListActionCatalog>('/generated/i18n-demo/list-actions', { location }, { signal, requestOptions: { showErrorMsg: false } }),
  listAction: (_key: string, payload: import('@/api/formData').FormListActionRequest) => request.post<import('@/api/formData').FormListActionReply>('/generated/i18n-demo/list-action', payload, { requestOptions: { showErrorMsg: false } }),
  listButtonAdapter: {"formKey":"i18n_demo","schemaHash":"2f24886b74ff4ee2fbcc3070bb427de0d586d0e53ab53896608b8435c2a6cf01","catalogPermission":"generated:i18n-demo:list-actions","executePermission":"generated:i18n-demo:list-action","fieldMap":{"id":"id","name":"name","status":"status","remark":"remark","created_at":"createdAt","updated_at":"updatedAt","deleted_at":"deletedAt"}} as const,
  list: (params: I18nDemoModelQuery) => request.get<API.PageResult<I18nDemoModel>>('/generated/i18n-demo', params),
  detail: (id: I18nDemoModelId) => request.get<I18nDemoModel>(`/generated/i18n-demo/${id}`),
  create: (data: I18nDemoModelPayload) => request.post<I18nDemoModel>('/generated/i18n-demo', data),
  update: (id: I18nDemoModelId, data: I18nDemoModelPayload) => request.put<I18nDemoModel>(`/generated/i18n-demo/${id}`, data),
  remove: (id: I18nDemoModelId) => request.delete(`/generated/i18n-demo/${id}`),
  restore: (id: I18nDemoModelId) => request.post(`/generated/i18n-demo/${id}/restore`),
  forceDelete: (id: I18nDemoModelId) => request.delete(`/generated/i18n-demo/${id}/destroy`),
  removeMany: (ids: I18nDemoModelId[]) => request.delete('/generated/i18n-demo', { ids }),
  restoreMany: (ids: I18nDemoModelId[]) => request.post('/generated/i18n-demo/restore', { ids }),
  forceDeleteMany: (ids: I18nDemoModelId[]) => request.delete('/generated/i18n-demo/destroy', { ids }),
  status: (id: I18nDemoModelId, status: number) => request.post(`/generated/i18n-demo/${id}/status`, { status }),
  importRows: (rows: I18nDemoModelPayload[]) => request.post('/generated/i18n-demo/import', { rows }),
  exportRows: (params: Partial<I18nDemoModelQuery>) => request.get<I18nDemoModel[]>('/generated/i18n-demo/export', params)
};
