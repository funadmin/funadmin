import request from '@/utils/http';

export interface OrderTestModel {
  id: number;
  field1?: string;
  field2?: string;
  field3?: string;
  field4?: string;
  field5?: number;
  field6?: Record<string, unknown> | unknown[];
  createdAt?: string;
  updatedAt?: string;
  deletedAt?: string;
}
export type OrderTestModelId = number;
export interface OrderTestModelQuery { page: number; pageSize: number; recycled?: 0 | 1; sort?: string; order?: 'asc' | 'desc'; [key: string]: string | number | undefined }
export type OrderTestModelPayload = Partial<Omit<OrderTestModel, 'id'>>;

export const orderTestApi = {
  list: (params: OrderTestModelQuery) => request.get<API.PageResult<OrderTestModel>>('/generated/order-test', params),
  detail: (id: OrderTestModelId) => request.get<OrderTestModel>(`/generated/order-test/${id}`),
  create: (data: OrderTestModelPayload) => request.post<OrderTestModel>('/generated/order-test', data),
  update: (id: OrderTestModelId, data: OrderTestModelPayload) => request.put<OrderTestModel>(`/generated/order-test/${id}`, data),
  remove: (id: OrderTestModelId) => request.delete(`/generated/order-test/${id}`),
  restore: (id: OrderTestModelId) => request.post(`/generated/order-test/${id}/restore`),
  forceDelete: (id: OrderTestModelId) => request.delete(`/generated/order-test/${id}/destroy`),
  removeMany: (ids: OrderTestModelId[]) => request.delete('/generated/order-test', { ids }),
  restoreMany: (ids: OrderTestModelId[]) => request.post('/generated/order-test/restore', { ids }),
  forceDeleteMany: (ids: OrderTestModelId[]) => request.delete('/generated/order-test/destroy', { ids }),
  importRows: (rows: OrderTestModelPayload[]) => request.post('/generated/order-test/import', { rows }),
  exportRows: (params: Partial<OrderTestModelQuery>) => request.get<OrderTestModel[]>('/generated/order-test/export', params)
};
