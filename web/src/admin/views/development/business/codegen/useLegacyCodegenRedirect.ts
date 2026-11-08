import { onActivated, onBeforeMount, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { hasPermission } from '@/utils/permission';
import { adminRoutePath } from '@/utils/route';

/** 代码生成页（菜单 BusinessDevelopment，组件 development/business/mine）。 */
export const CODEGEN_PAGE_PATH = adminRoutePath('/development/business/mine');
export const CODEGEN_PAGE_PERMISSION = 'development:business:modules';

/**
 * 旧菜单（从数据库生成 / 生成记录）合并进代码生成页后的兼容跳转。
 * 旧路由由数据库菜单按组件路径注册，因此跳转放在旧页面自身：
 * 只有拥有代码生成页权限的账号才跳转，其余账号继续使用原页面，不因菜单合并失去访问能力。
 */
/** intent 为 null（或返回 null）时不跳转：代码生成页上没有该账号可用的对应入口，继续使用原页面。 */
export function useLegacyCodegenRedirect(intent: Record<string, string> | null | (() => Record<string, string> | null)) {
  const route = useRoute();
  const router = useRouter();
  const redirected = ref(false);

  const attempt = () => {
    const query = typeof intent === 'function' ? intent() : intent;
    redirected.value = query !== null && hasPermission(CODEGEN_PAGE_PERMISSION);
    if (redirected.value) void router.replace({ path: CODEGEN_PAGE_PATH, query: { ...route.query, ...query } });
  };

  // 旧路由可能被 keep-alive 缓存：首次挂载在 onBeforeMount 处理，之后每次重新激活再判断一次。
  let activations = 0;
  onBeforeMount(attempt);
  onActivated(() => {
    if (activations++ > 0) attempt();
  });

  return { redirected };
}
