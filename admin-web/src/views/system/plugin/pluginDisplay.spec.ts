import { describe, expect, it } from 'vitest';
import {
  applicationLabel,
  operationLabel,
  pluginSourceLabel,
  pluginStateLabel,
  scopeLabel
} from './pluginDisplay';

describe('插件中心中文显示', () => {
  it('将插件生命周期状态转换为中文', () => {
    expect(pluginStateLabel('discovered')).toBe('待安装');
    expect(pluginStateLabel('enabled')).toBe('已启用');
    expect(pluginStateLabel('failed')).toBe('失败');
  });

  it('将来源、操作和能力标识转换为中文', () => {
    expect(pluginSourceLabel('cloud')).toBe('云市场');
    expect(operationLabel('migrate')).toBe('数据库迁移');
    expect(applicationLabel('console')).toBe('管理后台');
    expect(scopeLabel('both')).toBe('独立应用和管理后台');
  });

  it('未知值使用中文兜底而不是直接显示英文标识', () => {
    expect(pluginStateLabel('custom-state')).toBe('未知状态');
    expect(pluginSourceLabel('custom-source')).toBe('未知来源');
    expect(operationLabel('custom-operation')).toBe('未知操作');
  });
});
