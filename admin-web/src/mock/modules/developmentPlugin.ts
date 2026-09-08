import type { MockRoute } from '../types';
import { ok } from '../types';

const plugins = [
  { code: 'demo', name: '演示插件', manifestVersion: 2 as const, version: '1.0.0', scopes: ['application', 'console', 'both'] }
];

export const developmentPluginMockHandlers: MockRoute[] = [
  { method: 'GET', url: '/development/plugin/options', handler: () => ok(plugins) },
  { method: 'POST', url: '/development/plugin/create/preview', handler: ({ body }) => ok({
    auditId: 'mock-create-preview',
    conflicts: [],
    plan: {
      operation: 'create',
      target: `plugins/${body.name || 'demo'}`,
      files: [
        { path: `plugins/${body.name || 'demo'}/plugin.json`, status: 'create' },
        { path: `plugins/${body.name || 'demo'}/Plugin.php`, status: 'create' }
      ]
    }
  }) },
  { method: 'POST', url: '/development/plugin/create', handler: ({ body }) => ok({
    auditId: 'mock-create', conflicts: [], plugin: { code: body.name, directory: `plugins/${body.name}` }
  }) },
  { method: 'POST', url: '/development/plugin/validate', handler: () => ok({ auditId: 'mock-validate', conflicts: [], valid: true }) },
  { method: 'POST', url: '/development/plugin/package', handler: ({ body }) => ok({
    auditId: 'mock-package', conflicts: [], downloadPath: `runtime/download/plugins/${body.code}-1.0.0.zip`, downloadUrl: `/development/plugin/package/${body.code}/download`, sha256: 'a'.repeat(64)
  }) }
];
