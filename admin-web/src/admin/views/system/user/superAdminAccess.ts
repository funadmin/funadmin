/** 管理员列表中某一行可执行的操作。超管行只允许超管本人修改资料和密码。 */
export interface UserRowAccess {
  editable: boolean;
  resettable: boolean;
  deletable: boolean;
  statusToggle: boolean;
  roleEditable: boolean;
  selectable: boolean;
}

export function userRowAccess(row: { id: number; isSuper?: boolean }, currentUserId: number | undefined): UserRowAccess {
  if (!row.isSuper) {
    return { editable: true, resettable: true, deletable: true, statusToggle: true, roleEditable: true, selectable: true };
  }
  const self = currentUserId !== undefined && row.id === currentUserId;
  return { editable: self, resettable: self, deletable: false, statusToggle: false, roleEditable: false, selectable: false };
}

type RoleOption = import('@/api/system/role').RoleModel;

/**
 * 角色下拉选项。可分配角色列表不含系统超管角色，超管行回显时会只剩 ID；
 * 这里只为超管行补一个仅用于显示的超管角色（下拉在超管行是禁用的，不能借此分配）。
 */
export function roleOptionsForRow(options: RoleOption[], row: { isSuper?: boolean; roleIds?: number[] } | null | undefined, superRoleName: string): RoleOption[] {
  if (!row?.isSuper) return options;
  const missing = (row.roleIds || []).filter((id) => !options.some((role) => role.id === id));
  const placeholders = missing.map((id): RoleOption => ({
    id, name: superRoleName, code: 'super_admin', level: 0, dataScope: 'all', status: 1,
    parentId: 0, parentRoleIds: [], departmentIds: [], permissionIds: []
  }));
  return [...placeholders, ...options];
}
