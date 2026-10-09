import request from '@/utils/http';

export interface TestModel {
  id: number;
  field1?: string;
  field2?: string;
  field3?: string;
  createdAt?: string;
  updatedAt?: string;
  deletedAt?: string;
}
export type TestModelId = number;
export interface TestModelQuery { page: number; pageSize: number; recycled?: 0 | 1; sort?: string; order?: 'asc' | 'desc'; [key: string]: string | number | undefined }
export type TestModelPayload = Partial<Omit<TestModel, 'id'>>;

export const testApi = {
  list: (params: TestModelQuery) => request.get<API.PageResult<TestModel>>('/plugin/example/test', params),
  detail: (id: TestModelId) => request.get<TestModel>(`/plugin/example/test/${id}`),
  create: (data: TestModelPayload) => request.post<TestModel>('/plugin/example/test', data),
  update: (id: TestModelId, data: TestModelPayload) => request.put<TestModel>(`/plugin/example/test/${id}`, data),
  remove: (id: TestModelId) => request.delete(`/plugin/example/test/${id}`),
  restore: (id: TestModelId) => request.post(`/plugin/example/test/${id}/restore`),
  forceDelete: (id: TestModelId) => request.delete(`/plugin/example/test/${id}/destroy`),
  removeMany: (ids: TestModelId[]) => request.delete('/plugin/example/test', { ids }),
  restoreMany: (ids: TestModelId[]) => request.post('/plugin/example/test/restore', { ids }),
  forceDeleteMany: (ids: TestModelId[]) => request.delete('/plugin/example/test/destroy', { ids }),
  importRows: (rows: TestModelPayload[]) => request.post('/plugin/example/test/import', { rows }),
  exportRows: (params: Partial<TestModelQuery>) => request.get<TestModel[]>('/plugin/example/test/export', params)
};
