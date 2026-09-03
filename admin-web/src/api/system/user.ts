import http from '@/utils/http';

const PREFIX = '/system/user';

export interface UserModel {
  id: number;
  username: string;
  nickname: string;
  email?: string;
  mobile?: string;
  status: 0 | 1;
  roleIds?: number[];
  deptId?: number;
  createdAt?: string;
  updatedAt?: string;
}

export const userApi = {
  list: (params: API.PageQuery) => http.get<API.PageResult<UserModel>>(`${PREFIX}`, params),
  detail: (id: number) => http.get<UserModel>(`${PREFIX}/${id}`),
  create: (data: Partial<UserModel> & { password: string }) =>
    http.post<UserModel>(`${PREFIX}`, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<UserModel>) =>
    http.put<UserModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (ids: number | number[]) =>
    http.delete<void>(`${PREFIX}`, { ids: Array.isArray(ids) ? ids : [ids] }, {
      requestOptions: { showSuccessMsg: true }
    }),
  resetPassword: (id: number, password: string) =>
    http.post<void>(`${PREFIX}/${id}/reset-password`, { password }, {
      requestOptions: { showSuccessMsg: true }
    }),
  toggleStatus: (id: number, status: 0 | 1) =>
    http.post<void>(`${PREFIX}/${id}/status`, { status }),
  /** 批量导入（CSV 解析后的对象数组） */
  batchImport: (rows: Array<Partial<UserModel> & { username: string }>) =>
    http.post<{ created: number; skipped: number; errors: string[] }>(
      `${PREFIX}/import`,
      { rows },
      { requestOptions: { showSuccessMsg: true } }
    )
};
