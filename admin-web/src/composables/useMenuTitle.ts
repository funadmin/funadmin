import { useI18n } from 'vue-i18n';
import type { RouteRecordRaw } from 'vue-router';

/**
 * 侧栏 / 顶栏 / Tab / 面包屑菜单标题。
 * 优先用路由 name 对应 `menu.${name}` 文案；无映射时回退 meta.title（多为后端中文）。
 */
export function useMenuTitle() {
  const { t, te } = useI18n();

  function menuTitle(route: {
    name?: RouteRecordRaw['name'];
    meta?: { title?: string };
  }): string {
    const n = route.name;
    if (typeof n === 'string' && te(`menu.${n}`)) {
      return t(`menu.${n}`) as string;
    }
    return (route.meta?.title as string) ?? '';
  }

  return { menuTitle };
}
