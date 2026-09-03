import http from '@/utils/http';

const PREFIX = '/system/dict';

/** 通用字典「选项」（用于下拉框、Tag 渲染） */
export interface DictOption {
  label: string;
  value: string | number;
  status?: 0 | 1;
  cssClass?: string;
}

/** 字典分类（管理页主表） */
export interface DictType {
  id: number;
  code: string;
  name: string;
  status: 0 | 1;
  remark?: string;
  createdAt?: string;
}

/** 字典项（管理页明细表） */
export interface DictItemModel {
  id: number;
  typeCode: string;
  label: string;
  value: string;
  sort: number;
  status: 0 | 1;
  cssClass?: string;
  remark?: string;
}

export const dictApi = {
  /** 根据字典 code 拉取选项列表（业务页用） */
  options: (code: string) => http.get<DictOption[]>(`${PREFIX}/${code}/options`),
  /** 批量拉取多个字典 */
  batch: (codes: string[]) => http.post<Record<string, DictOption[]>>(`${PREFIX}/batch`, { codes })
};

/** 字典分类管理 API */
export const dictTypeApi = {
  list: (params: API.PageQuery & Partial<DictType>) =>
    http.get<API.PageResult<DictType>>(`${PREFIX}/types`, { params }),
  create: (data: Partial<DictType>) =>
    http.post<DictType>(`${PREFIX}/types`, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<DictType>) =>
    http.put<DictType>(`${PREFIX}/types/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (id: number) =>
    http.delete<void>(`${PREFIX}/types/${id}`, undefined, { requestOptions: { showSuccessMsg: true } }),
  removeMany: (ids: number[]) =>
    http.delete<{ removed: number }>(`${PREFIX}/types`, { ids }, { requestOptions: { showSuccessMsg: true } })
};

/** 字典项管理 API */
export const dictItemApi = {
  list: (params: API.PageQuery & Partial<DictItemModel>) =>
    http.get<API.PageResult<DictItemModel>>(`${PREFIX}/items`, { params }),
  create: (data: Partial<DictItemModel>) =>
    http.post<DictItemModel>(`${PREFIX}/items`, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<DictItemModel>) =>
    http.put<DictItemModel>(`${PREFIX}/items/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (id: number) =>
    http.delete<void>(`${PREFIX}/items/${id}`, undefined, { requestOptions: { showSuccessMsg: true } }),
  removeMany: (ids: number[]) =>
    http.delete<{ removed: number }>(`${PREFIX}/items`, { ids }, { requestOptions: { showSuccessMsg: true } })
};
