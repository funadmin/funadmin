import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

function source(path: string) {
  return readFileSync(resolve(process.cwd(), path), 'utf8');
}

describe('前端性能契约', () => {
  it('不把全部业务视图强制合并到一级目录 chunk', () => {
    const config = source('vite.config.ts');
    expect(config).not.toContain("return `view-${m[1]}`");
  });

  it('生产构建启用静态资源压缩', () => {
    expect(source('.env')).toContain('VITE_APP_BUILD_COMPRESS=true');
  });

  it('首次鉴权并行获取用户信息和菜单', () => {
    const guard = source('src/router/guard.ts');
    expect(guard).toContain('Promise.all([');
    expect(guard).toContain('userStore.fetchUserInfo()');
    expect(guard).toContain('permissionStore.fetchMenus()');
  });

  it('限制 keep-alive 实例数量', () => {
    expect(source('src/layout/components/LayoutRouterView.vue')).toMatch(/<keep-alive[^>]+:max="12"/);
  });

  it('列表缩略图使用懒加载', () => {
    expect(source('src/views/system/member-level/index.vue')).toMatch(/<el-image[^>]+lazy/);
    expect(source('src/views/system/attachment/index.vue')).toMatch(/<el-image[^>]+lazy/);
  });

  it('ECharts 不注册未使用的图表和功能组件', () => {
    const component = source('src/components/Echarts/index.vue');
    expect(component).not.toContain('ScatterChart');
    expect(component).not.toContain('DataZoomComponent');
    expect(component).not.toContain('ToolboxComponent');
  });
});
