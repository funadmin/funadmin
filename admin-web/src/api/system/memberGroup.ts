import http from '@/utils/http';

const PREFIX = '/system/member-group';

export interface MemberGroupModel {
  id: number;
  name: string;
  status: 0 | 1;
  isDefault: 0 | 1;
  createdAt: string;
  updatedAt: string;
  deletedAt: string;
}

export interface MemberGroupQuery {
  page: number;
  pageSize: number;
  name?: string;
  status?: 0 | 1;
  recycled?: 0 | 1;
}

export const memberGroupApi = {
  list: (params: MemberGroupQuery) => http.get<API.PageResult<MemberGroupModel>>(PREFIX, { params }),
  detail: (id: number) => http.get<MemberGroupModel>(`${PREFIX}/${id}`),
  create: (data: Pick<MemberGroupModel, 'name' | 'status'>) =>
    http.post<MemberGroupModel>(PREFIX, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Pick<MemberGroupModel, 'name' | 'status'>) =>
    http.put<MemberGroupModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  updateStatus: (id: number, status: 0 | 1) =>
    http.post<MemberGroupModel>(`${PREFIX}/${id}/status`, { status }, { requestOptions: { showSuccessMsg: true } }),
  recycle: (ids: number[]) =>
    http.delete<{ removed: number }>(PREFIX, { ids }, { requestOptions: { showSuccessMsg: true } }),
  restore: (ids: number[]) =>
    http.post<{ restored: number }>(`${PREFIX}/restore`, { ids }, { requestOptions: { showSuccessMsg: true } }),
  destroy: (ids: number[]) =>
    http.delete<{ removed: number }>(`${PREFIX}/destroy`, { ids }, { requestOptions: { showSuccessMsg: true } }),
  exportRows: (params: Omit<MemberGroupQuery, 'page' | 'pageSize'>) =>
    http.get<MemberGroupModel[]>(`${PREFIX}/export`, { params })
};
