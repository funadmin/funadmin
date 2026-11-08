import { ElMessage } from 'element-plus';
import { i18n } from '@/locales';

/**
 * 插件安装/启用/禁用/更新/重部署/卸载会改变后端路由集合；注解路由只在 worker 启动时注册，
 * 后端在操作完整成功后请求一次平滑重载并返回 reloadRequested/reloadReason。
 */
export interface PluginReloadResult {
  reloadRequested?: boolean;
  reloadReason?: string | null;
}

export interface PluginReloadNotifier {
  success: (message: string) => unknown;
  warning: (message: string) => unknown;
}

/** 后端收到重载信号后逐个重启 worker；稍等再刷新插件路由与菜单，避免命中尚未注册新路由的旧 worker。 */
export const PLUGIN_RELOAD_SETTLE_MS = 1500;

export const waitForPluginReload = (ms: number = PLUGIN_RELOAD_SETTLE_MS): Promise<void> =>
  new Promise((resolve) => { setTimeout(resolve, ms); });

const reloadFields = (result: unknown): PluginReloadResult =>
  result !== null && typeof result === 'object' ? (result as PluginReloadResult) : {};

/** 按响应提示重载结果；返回是否已请求重载（需要延迟刷新）。不含重载字段的响应不提示。 */
export function notifyPluginReload(result: unknown, notifier: PluginReloadNotifier = ElMessage): boolean {
  const { reloadRequested, reloadReason } = reloadFields(result);
  if (reloadRequested === true) {
    notifier.success(i18n.global.t('plugin.backendReloaded', '后端已重新加载，新页面稍后可用'));
    return true;
  }
  if (reloadRequested === false) {
    notifier.warning(i18n.global.t('plugin.restartRequired', { reason: reloadReason || '-' }, '请重启后端服务使新接口生效（原因：{reason}）'));
  }
  return false;
}

/** 生命周期操作成功后：提示重载结果，已请求重载时等待约 1.5 秒再刷新路由/菜单与列表。 */
export async function afterPluginLifecycle(
  result: unknown,
  refresh: () => Promise<unknown>,
  options: { notifier?: PluginReloadNotifier; wait?: (ms: number) => Promise<unknown> } = {}
): Promise<void> {
  if (notifyPluginReload(result, options.notifier)) {
    await (options.wait ?? waitForPluginReload)(PLUGIN_RELOAD_SETTLE_MS);
  }
  await refresh();
}
