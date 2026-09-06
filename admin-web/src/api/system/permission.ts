import http from '@/utils/http';

const PREFIX = '/system/permission';

export interface PermissionModel {
  id: number;
  parentId: number;
  appName: string;
  code: string;
  object: string;
  action: string;
  name: string;
  resourceType: 'group' | 'route';
  status: 0 | 1;
  isPublic: 0 | 1;
  sort: number;
  sourceType: string;
  sourceName: string;
  createdAt?: string;
  updatedAt?: string;
  children?: PermissionModel[];
}

export const permissionApi = {
  tree: () => http.get<PermissionModel[]>(`${PREFIX}/tree`),
  detail: (id: number) => http.get<PermissionModel>(`${PREFIX}/${id}`),
  create: (data: Partial<PermissionModel>) =>
    http.post<PermissionModel>(PREFIX, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<PermissionModel>) =>
    http.put<PermissionModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (id: number) =>
    http.delete<void>(`${PREFIX}/${id}`, undefined, { requestOptions: { showSuccessMsg: true } }),
  removeMany: (ids: number[]) =>
    http.delete<{ removed: number }>(PREFIX, { ids }, { requestOptions: { showSuccessMsg: true } })
};
