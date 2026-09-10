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
  parentId: number;
  parentRoleIds: number[];
  departmentIds: number[];
  permissionIds: number[];
  createdAt?: string;
}

export interface AuthorizationSource {
  roleId: number;
  roleName: string;
}

export interface PermissionAction {
  id: number;
  name: string;
  code: string;
  direct: boolean;
  inherited: boolean;
  inheritedFrom: AuthorizationSource[];
}

export interface PermissionResourceRow {
  key: string;
  name: string;
  actions: PermissionAction[];
}

export interface PermissionGroup {
  id: number;
  name: string;
  resources: PermissionResourceRow[];
}

export interface FieldPermission {
  id: number;
  permissionId: number;
  resource: string;
  field: string;
  name: string;
  view: boolean;
  edit: boolean;
  inheritedView: boolean;
  inheritedEdit: boolean;
  inheritedFrom: AuthorizationSource[];
}

export interface AuthorizationTreeNode {
  id: number;
  parentId: number;
  name: string;
  code?: string;
  children?: AuthorizationTreeNode[];
}

export interface RoleInheritanceParent extends AuthorizationSource {
  relation: 'primary' | 'additional';
}

export interface RoleInheritanceAncestor extends AuthorizationSource {
  sourceRoleIds: number[];
  sourceRoleNames: string[];
}

export interface RoleInheritanceDetail {
  directParents: RoleInheritanceParent[];
  ancestors: RoleInheritanceAncestor[];
}

export interface RoleAuthorization {
  roleId: number;
  roles: AuthorizationTreeNode[];
  permissionGroups: PermissionGroup[];
  fields: FieldPermission[];
  dataScope: DataScope;
  departmentIds: number[];
  departmentTree: AuthorizationTreeNode[];
  effectivePermissionIds: number[];
  inheritance?: RoleInheritanceDetail;
}

export interface SaveRoleAuthorization {
  permissionIds: number[];
  fieldPermissions: Array<{ fieldId: number; view: boolean; edit: boolean }>;
  dataScope: DataScope;
  departmentIds: number[];
}

export const roleApi = {
  list: (params: API.PageQuery) => http.get<API.PageResult<RoleModel>>(`${PREFIX}`, params),
  all: () => http.get<RoleModel[]>(`${PREFIX}/all`),
  parentOptions: () => http.get<RoleModel[]>(`${PREFIX}/parent-options`),
  permissionTree: () => http.get<API.MenuItem[]>(`${PREFIX}/permission-tree`),
  detail: (id: number) => http.get<RoleModel>(`${PREFIX}/${id}`),
  create: (data: Partial<RoleModel> & { parentId?: number }) =>
    http.post<RoleModel>(`${PREFIX}`, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<RoleModel> & { parentId?: number }) =>
    http.put<RoleModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (ids: number | number[]) =>
    http.delete<void>(`${PREFIX}`, { ids: Array.isArray(ids) ? ids : [ids] }, {
      requestOptions: { showSuccessMsg: true }
    }),
  assignPermissions: (id: number, permissionIds: number[]) =>
    http.post<void>(`${PREFIX}/${id}/permissions`, { permissionIds }, {
      requestOptions: { showSuccessMsg: true }
    }),
  authorization: (id: number) => http.get<RoleAuthorization>(`${PREFIX}/${id}/authorization`),
  saveAuthorization: (id: number, data: SaveRoleAuthorization) =>
    http.put<void>(`${PREFIX}/${id}/authorization`, data, {
      requestOptions: { showSuccessMsg: true }
    }),
  copyAuthorization: (id: number, sourceRoleId: number) =>
    http.post<void>(`${PREFIX}/${id}/authorization/copy`, { sourceRoleId }, {
      requestOptions: { showSuccessMsg: true }
    })
};
