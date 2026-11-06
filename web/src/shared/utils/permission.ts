import { useUserStore } from '@/store/modules/user';

export type PermissionRequirement = string | readonly string[] | null | undefined;
export type PermissionMode = 'any' | 'all';

/** 仅匹配明确授予的动作；查看权限不会自动授予修改权限。 */
export function checkPermission(
  granted: readonly string[] | null | undefined,
  required: PermissionRequirement,
  mode: PermissionMode = 'any'
): boolean {
  if (required == null || required === '') return true;
  const permissions = granted ?? [];
  if (permissions.includes('*') || permissions.includes('*:*:*')) return true;
  const requirements = typeof required === 'string' ? [required] : required;
  return mode === 'all'
    ? requirements.every(permission => permissions.includes(permission))
    : requirements.some(permission => permissions.includes(permission));
}

/** 在渲染、computed 或事件处理器中读取当前账号的最新权限。 */
export function hasPermission(required: PermissionRequirement, mode: PermissionMode = 'any'): boolean {
  if (required == null || required === '') return true;
  return checkPermission(useUserStore().permissions, required, mode);
}
