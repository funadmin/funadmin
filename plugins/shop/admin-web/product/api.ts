import request from '@/utils/http';

export interface ProductModel {
  id: number;
  name: string;
  price: string;
  status: number;
  createdAt?: string;
  updatedAt?: string;
  deletedAt?: string;
}
export type ProductModelId = number;
export interface ProductModelQuery { page: number; pageSize: number; recycled?: 0 | 1; sort?: string; order?: 'asc' | 'desc'; [key: string]: unknown }
export type ProductModelPayload = Partial<Omit<ProductModel, 'id'>>;

export const productApi = {
  list: (params: ProductModelQuery) => request.get<API.PageResult<ProductModel>>('/console/plugin/shop/product', params),
  detail: (id: ProductModelId) => request.get<ProductModel>(`/console/plugin/shop/product/${id}`),
  create: (data: ProductModelPayload) => request.post<ProductModel>('/console/plugin/shop/product', data),
  update: (id: ProductModelId, data: ProductModelPayload) => request.put<ProductModel>(`/console/plugin/shop/product/${id}`, data),
  remove: (id: ProductModelId) => request.delete(`/console/plugin/shop/product/${id}`),
  restore: (id: ProductModelId) => request.post(`/console/plugin/shop/product/${id}/restore`),
  forceDelete: (id: ProductModelId) => request.delete(`/console/plugin/shop/product/${id}/destroy`),
  removeMany: (ids: ProductModelId[]) => request.delete('/console/plugin/shop/product', { ids }),
  restoreMany: (ids: ProductModelId[]) => request.post('/console/plugin/shop/product/restore', { ids }),
  forceDeleteMany: (ids: ProductModelId[]) => request.delete('/console/plugin/shop/product/destroy', { ids }),
  status: (id: ProductModelId, status: number) => request.post(`/console/plugin/shop/product/${id}/status`, { status })
};
