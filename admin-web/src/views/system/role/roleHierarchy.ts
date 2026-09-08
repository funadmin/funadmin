import type { RoleModel } from '@/api/system/role';
import { listToTree } from '@/utils/tree';

export type RoleTreeNode = RoleModel & { children?: RoleTreeNode[] };

export function roleTree(roles: RoleModel[]): RoleTreeNode[] {
  const visibleIds = new Set(roles.map((role) => role.id));
  return listToTree(roles.map((role) => ({
    ...role,
    parentId: visibleIds.has(role.parentId) ? role.parentId : 0,
    children: undefined
  })), { parentKey: 'parentId' });
}

export function parentRoleOptions(roles: RoleModel[], currentRoleId?: number): RoleTreeNode[] {
  const excluded = new Set<number>();
  if (currentRoleId !== undefined) {
    const queue = [currentRoleId];
    while (queue.length) {
      const roleId = queue.shift()!;
      if (excluded.has(roleId)) continue;
      excluded.add(roleId);
      queue.push(...roles.filter((role) => role.parentId === roleId).map((role) => role.id));
    }
  }
  return roleTree(roles.filter((role) => !excluded.has(role.id)));
}

export function filterRoleTree(tree: RoleTreeNode[], predicate: (role: RoleModel) => boolean): RoleTreeNode[] {
  return tree.flatMap((role) => {
    const children = filterRoleTree(role.children || [], predicate);
    return predicate(role) || children.length ? [{ ...role, children }] : [];
  });
}

export function childRoleLevel(parentRoleIds: number[], roles: RoleModel[], currentLevel: number): number {
  const selectedLevels = roles
    .filter((role) => parentRoleIds.includes(role.id))
    .map((role) => role.level);

  return selectedLevels.length ? Math.min(9999, Math.max(...selectedLevels) + 1) : currentLevel;
}
