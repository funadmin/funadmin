import http from '@/utils/http';

export type ManagementResource = 'users' | 'roles' | 'permissions' | 'menus';
export interface ManagementRow {
  id: number;
  username?: string;
  name?: string;
  code?: string;
  nickname?: string;
  email?: string;
  mobile?: string;
  path?: string;
  routeName?: string;
  permission?: string;
  status: number;
  is_super_admin?: number;
  roleIds?: number[];
  permissionIds?: number[];
}

const prefix = '/management';
export const managementApi = {
  list: (resource: ManagementResource, params: API.PageQuery) => http.get<API.PageResult<ManagementRow>>(`${prefix}/${resource}`, params),
  create: (resource: ManagementResource, data: Record<string, unknown>) => http.post<ManagementRow>(`${prefix}/${resource}`, data),
  update: (resource: ManagementResource, id: number, data: Record<string, unknown>) => http.put<ManagementRow>(`${prefix}/${resource}/${id}`, data),
  remove: (resource: ManagementResource, id: number) => http.delete<void>(`${prefix}/${resource}/${id}`)
};
