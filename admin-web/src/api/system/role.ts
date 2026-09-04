import http from '@/utils/http';

const PREFIX = '/system/role';

export type DataScope = 'all' | 'dept_and_children' | 'dept' | 'self' | 'custom';

export interface RoleModel {
  id: number;
  name: string;
  code: string;
  level: number;
  dataScope: DataScope;
  remark?: string;
  status: 0 | 1;
  parentRoleIds: number[];
  departmentIds: number[];
  permissionIds: number[];
  createdAt?: string;
}

export const roleApi = {
  list: (params: API.PageQuery) => http.get<API.PageResult<RoleModel>>(`${PREFIX}`, params),
  all: () => http.get<RoleModel[]>(`${PREFIX}/all`),
  parentOptions: () => http.get<RoleModel[]>(`${PREFIX}/parent-options`),
  permissionTree: () => http.get<API.MenuItem[]>(`${PREFIX}/permission-tree`),
  detail: (id: number) => http.get<RoleModel>(`${PREFIX}/${id}`),
  create: (data: Partial<RoleModel>) =>
    http.post<RoleModel>(`${PREFIX}`, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<RoleModel>) =>
    http.put<RoleModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (ids: number | number[]) =>
    http.delete<void>(`${PREFIX}`, { ids: Array.isArray(ids) ? ids : [ids] }, {
      requestOptions: { showSuccessMsg: true }
    }),
  assignPermissions: (id: number, permissionIds: number[]) =>
    http.post<void>(`${PREFIX}/${id}/permissions`, { permissionIds }, {
      requestOptions: { showSuccessMsg: true }
    })
};
