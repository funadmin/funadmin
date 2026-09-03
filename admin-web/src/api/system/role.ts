import http from '@/utils/http';

const PREFIX = '/system/role';

export interface RoleModel {
  id: number;
  name: string;
  code: string;
  remark?: string;
  status: 0 | 1;
  menuIds?: number[];
  createdAt?: string;
}

export const roleApi = {
  list: (params: API.PageQuery) => http.get<API.PageResult<RoleModel>>(`${PREFIX}`, params),
  all: () => http.get<RoleModel[]>(`${PREFIX}/all`),
  detail: (id: number) => http.get<RoleModel>(`${PREFIX}/${id}`),
  create: (data: Partial<RoleModel>) =>
    http.post<RoleModel>(`${PREFIX}`, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<RoleModel>) =>
    http.put<RoleModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (ids: number | number[]) =>
    http.delete<void>(`${PREFIX}`, { ids: Array.isArray(ids) ? ids : [ids] }, {
      requestOptions: { showSuccessMsg: true }
    }),
  assignMenus: (id: number, menuIds: number[]) =>
    http.post<void>(`${PREFIX}/${id}/menus`, { menuIds }, {
      requestOptions: { showSuccessMsg: true }
    })
};
