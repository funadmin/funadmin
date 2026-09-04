import http from '@/utils/http';

const PREFIX = '/system/member-level';

export interface MemberLevelModel {
  id: number;
  name: string;
  amount: string;
  discount: number;
  thumb: string;
  status: 0 | 1;
  sort: number;
  description: string;
  createdAt: string;
  updatedAt: string;
  deletedAt: string;
}

export interface MemberLevelQuery {
  page: number;
  pageSize: number;
  name?: string;
  status?: 0 | 1;
  recycled?: 0 | 1;
}

export type MemberLevelPayload = Pick<MemberLevelModel, 'name' | 'amount' | 'discount' | 'thumb' | 'status' | 'sort' | 'description'>;

export const memberLevelApi = {
  list: (params: MemberLevelQuery) => http.get<API.PageResult<MemberLevelModel>>(PREFIX, { params }),
  detail: (id: number) => http.get<MemberLevelModel>(`${PREFIX}/${id}`),
  create: (data: MemberLevelPayload) =>
    http.post<MemberLevelModel>(PREFIX, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: MemberLevelPayload) =>
    http.put<MemberLevelModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  updateStatus: (id: number, status: 0 | 1) =>
    http.post<MemberLevelModel>(`${PREFIX}/${id}/status`, { status }, { requestOptions: { showSuccessMsg: true } }),
  recycle: (ids: number[]) =>
    http.delete<{ removed: number }>(PREFIX, { ids }, { requestOptions: { showSuccessMsg: true } }),
  restore: (ids: number[]) =>
    http.post<{ restored: number }>(`${PREFIX}/restore`, { ids }, { requestOptions: { showSuccessMsg: true } }),
  destroy: (ids: number[]) =>
    http.delete<{ removed: number }>(`${PREFIX}/destroy`, { ids }, { requestOptions: { showSuccessMsg: true } }),
  exportRows: (params: Omit<MemberLevelQuery, 'page' | 'pageSize'>) =>
    http.get<MemberLevelModel[]>(`${PREFIX}/export`, { params })
};
