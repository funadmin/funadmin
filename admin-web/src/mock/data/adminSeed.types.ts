/** Mock 用户行：对齐 docs/api.md UserModel */
export interface MockUserRow {
  id: number;
  username: string;
  nickname: string;
  email: string;
  mobile: string;
  status: 0 | 1;
  roleIds: number[];
  deptId: number;
  createdAt: string;
  updatedAt?: string;
}

/** Mock 角色行：对齐 docs/api.md RoleModel */
export interface MockRoleRow {
  id: number;
  name: string;
  code: string;
  remark: string;
  status: 0 | 1;
  menuIds: number[];
  createdAt: string;
}
