import request from '@/utils/http';

export interface OrderTestModel {
  id: number;
  field1?: string;
  field7?: Record<string, unknown> | unknown[];
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
  listActions: (_key: string, location: import('@/views/form/schema/types').FormListButtonLocation, signal?: AbortSignal) => request.get<import('@/api/formData').FormListActionCatalog>('/generated/order-test/list-actions', { location }, { signal, requestOptions: { showErrorMsg: false } }),
  listAction: (_key: string, payload: import('@/api/formData').FormListActionRequest) => request.post<import('@/api/formData').FormListActionReply>('/generated/order-test/list-action', payload, { requestOptions: { showErrorMsg: false } }),
  listButtonAdapter: {"formKey":"order_test","schemaHash":"2d35f76d667148372df68c9c738e66e94e3c5506ec41c7d5687af0b0469a4305","catalogPermission":"generated:order-test:list-actions","executePermission":"generated:order-test:list-action","fieldMap":{"id":"id","field_1":"field1","field_7":"field7","field_2":"field2","field_3":"field3","field_4":"field4","field_5":"field5","field_6":"field6","created_at":"createdAt","updated_at":"updatedAt","deleted_at":"deletedAt"}} as const,
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
  options: (source: string) => request.get<Array<{ label: string; value: string | number }>>(`/generated/order-test/options/${source}`),
  importRows: (rows: OrderTestModelPayload[]) => request.post('/generated/order-test/import', { rows }),
  exportRows: (params: Partial<OrderTestModelQuery>) => request.get<OrderTestModel[]>('/generated/order-test/export', params)
};
