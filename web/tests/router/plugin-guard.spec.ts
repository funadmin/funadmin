import { describe, expect, it } from 'vitest';
import { loadPluginModulesSafely } from '@/router/pluginStartup';

describe('plugin router startup', () => {
  it('enabled modules 请求失败时不影响核心路由', async () => {
    const errors: unknown[] = [];
    const result = await loadPluginModulesSafely(
      {} as never,
      async () => { throw new Error('network failed'); },
      (error) => errors.push(error)
    );

    expect(result.loaded).toEqual([]);
    expect(result.errors).toEqual([]);
    expect(errors).toHaveLength(1);
  });
});
