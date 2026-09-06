import type { Router } from 'vue-router';
import { pluginApi, type EnabledPluginModule } from '@/api/system/plugin';
import { syncPluginModules } from '@/router/pluginModules';

export interface PluginModuleSyncResult {
  loaded: string[];
  errors: Array<{ name: string; message: string }>;
}

export async function loadPluginModulesSafely(
  router: Router,
  fetchModules: () => Promise<EnabledPluginModule[]> = pluginApi.enabledModules,
  reportError: (error: unknown) => void = console.error
): Promise<PluginModuleSyncResult> {
  try {
    const result = await syncPluginModules(router, await fetchModules());
    result.errors.forEach((error) => reportError(`[plugin:${error.name}] ${error.message}`));
    return result;
  } catch (error) {
    reportError(error);
    return { loaded: [], errors: [] };
  }
}
