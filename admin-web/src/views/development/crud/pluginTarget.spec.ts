import { describe, expect, it } from 'vitest';
import type { DevelopmentPluginOption } from '@/api/development/plugin';
import { pluginTargetFor } from './pluginTarget';

const plugin = (scopes: DevelopmentPluginOption['scopes']): DevelopmentPluginOption => ({
  code: 'catalog',
  name: '商品插件',
  manifestVersion: 2,
  version: '1.0.0',
  scopes
});

describe('CRUD 工作台新建插件目标', () => {
  it('优先生成管理后台和独立应用两端代码', () => {
    expect(pluginTargetFor(plugin(['application', 'console', 'both']))).toEqual({
      type: 'plugin',
      plugin: 'catalog',
      scope: 'both'
    });
  });

  it('插件仅支持单端时选择实际可用范围', () => {
    expect(pluginTargetFor(plugin(['console']))?.scope).toBe('console');
    expect(pluginTargetFor(plugin(['application']))?.scope).toBe('application');
  });

  it('无可用生成范围时拒绝建立目标', () => {
    expect(pluginTargetFor(plugin([]))).toBeNull();
  });
});
