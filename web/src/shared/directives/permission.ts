import type { Directive, DirectiveBinding } from 'vue';
import { useUserStore } from '@/store/modules/user';

/**
 * 按钮级权限指令：
 *   v-perm="'system:user:add'"
 *   v-perm="['system:user:add', 'system:user:edit']" 任意一个匹配即可
 *   v-perm:all="['a', 'b']" 必须全部匹配
 */
function check(value: unknown, mode: 'any' | 'all' = 'any'): boolean {
  if (!value) return true;
  const userStore = useUserStore();
  const perms = userStore.permissions || [];
  /** 超级权限：`*` 或与接口文档常见的 `*:*:*` 全通配 */
  if (perms.some((p) => p === '*' || p === '*:*:*')) return true;

  if (Array.isArray(value)) {
    return mode === 'all'
      ? value.every((v) => perms.includes(v))
      : value.some((v) => perms.includes(v));
  }
  return perms.includes(String(value));
}

function update(el: HTMLElement, binding: DirectiveBinding) {
  const mode = binding.arg === 'all' ? 'all' : 'any';
  if (!check(binding.value, mode)) {
    el.parentNode?.removeChild(el);
  }
}

export const permission: Directive = {
  mounted: update,
  updated: update
};
