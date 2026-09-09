import request from '@/utils/http';

export interface TestModel {
  id: number;
  field1?: string;
  field2?: string;
  field3?: number;
  field4?: number;
  field5?: string;
  field6?: number;
  field7?: string;
  field8?: number;
  createdAt?: string;
  updatedAt?: string;
  deletedAt?: string;
}
export type TestModelId = number;
export interface TestModelQuery { page: number; pageSize: number; recycled?: 0 | 1; sort?: string; order?: 'asc' | 'desc'; [key: string]: string | number | undefined }
export type TestModelPayload = Partial<Omit<TestModel, 'id'>>;

export const testApi = {
  list: (params: TestModelQuery) => request.get<API.PageResult<TestModel>>('/generated/test', params),
  detail: (id: TestModelId) => request.get<TestModel>(`/generated/test/${id}`),
  create: (data: TestModelPayload) => request.post<TestModel>('/generated/test', data),
  update: (id: TestModelId, data: TestModelPayload) => request.put<TestModel>(`/generated/test/${id}`, data),
  remove: (id: TestModelId) => request.delete(`/generated/test/${id}`),
  restore: (id: TestModelId) => request.post(`/generated/test/${id}/restore`),
  forceDelete: (id: TestModelId) => request.delete(`/generated/test/${id}/destroy`),
  removeMany: (ids: TestModelId[]) => request.delete('/generated/test', { ids }),
  restoreMany: (ids: TestModelId[]) => request.post('/generated/test/restore', { ids }),
  forceDeleteMany: (ids: TestModelId[]) => request.delete('/generated/test/destroy', { ids }),
  importRows: (rows: TestModelPayload[]) => request.post('/generated/test/import', { rows }),
  exportRows: (params: Partial<TestModelQuery>) => request.get<TestModel[]>('/generated/test/export', params)
};
