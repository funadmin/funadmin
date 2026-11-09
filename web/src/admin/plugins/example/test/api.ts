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
  list: (params: TestModelQuery) => request.get<API.PageResult<TestModel>>('/plugins/example/test', params),
  detail: (id: TestModelId) => request.get<TestModel>(`/plugins/example/test/${id}`),
  create: (data: TestModelPayload) => request.post<TestModel>('/plugins/example/test', data),
  update: (id: TestModelId, data: TestModelPayload) => request.put<TestModel>(`/plugins/example/test/${id}`, data),
  remove: (id: TestModelId) => request.delete(`/plugins/example/test/${id}`),
  restore: (id: TestModelId) => request.post(`/plugins/example/test/${id}/restore`),
  forceDelete: (id: TestModelId) => request.delete(`/plugins/example/test/${id}/destroy`),
  removeMany: (ids: TestModelId[]) => request.delete('/plugins/example/test', { ids }),
  restoreMany: (ids: TestModelId[]) => request.post('/plugins/example/test/restore', { ids }),
  forceDeleteMany: (ids: TestModelId[]) => request.delete('/plugins/example/test/destroy', { ids }),
  importRows: (rows: TestModelPayload[]) => request.post('/plugins/example/test/import', { rows }),
  exportRows: (params: Partial<TestModelQuery>) => request.get<TestModel[]>('/plugins/example/test/export', params)
};
