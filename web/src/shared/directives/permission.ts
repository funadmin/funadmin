import type { Directive, DirectiveBinding } from 'vue';
import { shallowRef, watchEffect } from 'vue';
import { hasPermission, type PermissionMode, type PermissionRequirement } from '@/utils/permission';

/**
 * 按钮级权限指令：
 *   v-perm="'authorization:admin:create'"
 *   v-perm="['authorization:admin:create', 'authorization:admin:update']" 任意一个匹配即可
 *   v-perm:all="['a', 'b']" 必须全部匹配
 */
interface PermissionState {
  requirement: ReturnType<typeof shallowRef<PermissionRequirement>>;
  mode: ReturnType<typeof shallowRef<PermissionMode>>;
  display: string;
  priority: string;
  stop: () => void;
}

const states = new WeakMap<HTMLElement, PermissionState>();

function apply(el: HTMLElement, state: PermissionState) {
  if (hasPermission(state.requirement.value, state.mode.value)) {
    if (state.display) el.style.setProperty('display', state.display, state.priority);
    else el.style.removeProperty('display');
  } else {
    // 保留 Vue 管理的节点，重新授权后可恢复显示；important 防止按钮样式覆盖。
    el.style.setProperty('display', 'none', 'important');
  }
}

export const permission: Directive<HTMLElement, PermissionRequirement> = {
  mounted(el, binding) {
    const state: PermissionState = {
      requirement: shallowRef(binding.value),
      mode: shallowRef(binding.arg === 'all' ? 'all' : 'any'),
      display: el.style.getPropertyValue('display'),
      priority: el.style.getPropertyPriority('display'),
      stop: () => {}
    };
    states.set(el, state);
    state.stop = watchEffect(() => apply(el, state));
  },
  updated(el, binding: DirectiveBinding<PermissionRequirement>) {
    const state = states.get(el);
    if (!state) return;
    state.requirement.value = binding.value;
    state.mode.value = binding.arg === 'all' ? 'all' : 'any';
    apply(el, state);
  },
  beforeUnmount(el) {
    states.get(el)?.stop();
    states.delete(el);
  }
};
