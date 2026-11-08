import request from '@/utils/http';

export interface OrderModel {
  id: number;
  field1?: string;
  field2?: string;
  field3?: string;
  field5?: number;
  field5Copy?: number;
  field4?: string;
  createdAt?: string;
  updatedAt?: string;
  deletedAt?: string;
}
export type OrderModelId = number;
export interface OrderModelQuery { page: number; pageSize: number; recycled?: 0 | 1; sort?: string; order?: 'asc' | 'desc'; [key: string]: string | number | undefined }
export type OrderModelPayload = Partial<Omit<OrderModel, 'id'>>;

export const orderApi = {
  listActions: (_key: string, location: import('@/views/form/schema/types').FormListButtonLocation, signal?: AbortSignal) => request.get<import('@/api/formData').FormListActionCatalog>('/business/order/list-actions', { location }, { signal, requestOptions: { showErrorMsg: false } }),
  listAction: (_key: string, payload: import('@/api/formData').FormListActionRequest) => request.post<import('@/api/formData').FormListActionReply>('/business/order/list-action', payload, { requestOptions: { showErrorMsg: false } }),
  listButtonAdapter: {"formKey":"order","schemaHash":"a89864e189620fc8f917c94a0475460931ae96469ae931229e4c4dadb167fc06","catalogPermission":"business:order:list-actions","executePermission":"business:order:list-action","fieldMap":{"id":"id","field_1":"field1","field_2":"field2","field_3":"field3","field_5":"field5","field_5_copy":"field5Copy","field_4":"field4","created_at":"createdAt","updated_at":"updatedAt","deleted_at":"deletedAt"}} as const,
  list: (params: OrderModelQuery) => request.get<API.PageResult<OrderModel>>('/business/order', params),
  detail: (id: OrderModelId) => request.get<OrderModel>(`/business/order/${id}`),
  create: (data: OrderModelPayload) => request.post<OrderModel>('/business/order', data),
  update: (id: OrderModelId, data: OrderModelPayload) => request.put<OrderModel>(`/business/order/${id}`, data),
  remove: (id: OrderModelId) => request.delete(`/business/order/${id}`),
  restore: (id: OrderModelId) => request.post(`/business/order/${id}/restore`),
  forceDelete: (id: OrderModelId) => request.delete(`/business/order/${id}/destroy`),
  removeMany: (ids: OrderModelId[]) => request.delete('/business/order', { ids }),
  restoreMany: (ids: OrderModelId[]) => request.post('/business/order/restore', { ids }),
  forceDeleteMany: (ids: OrderModelId[]) => request.delete('/business/order/destroy', { ids }),
  importRows: (rows: OrderModelPayload[]) => request.post('/business/order/import', { rows }),
  exportRows: (params: Partial<OrderModelQuery>) => request.get<OrderModel[]>('/business/order/export', params)
};
