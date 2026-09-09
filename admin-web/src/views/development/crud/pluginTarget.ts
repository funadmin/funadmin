import type { DevelopmentPluginOption } from '@/api/development/plugin';
import type { CrudTarget } from '@/types/development/crud';

export const pluginTargetFor = (plugin: DevelopmentPluginOption): Extract<CrudTarget, { type: 'plugin' }> | null => {
  const scope = plugin.scopes.includes('both')
    ? 'both'
    : plugin.scopes.includes('console')
      ? 'console'
      : plugin.scopes.includes('application')
        ? 'application'
        : null;

  return scope ? { type: 'plugin', plugin: plugin.code, scope } : null;
};
