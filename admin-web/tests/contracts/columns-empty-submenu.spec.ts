import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const layout = readFileSync(resolve(process.cwd(), 'src/layout/index.vue'), 'utf8');
const topMenu = readFileSync(resolve(process.cwd(), 'src/layout/components/TopMenu.vue'), 'utf8');
const columnsRail = readFileSync(resolve(process.cwd(), 'src/layout/components/ColumnsRail.vue'), 'utf8');
const sidebarItem = readFileSync(resolve(process.cwd(), 'src/layout/components/SidebarItem.vue'), 'utf8');
const topSubItem = readFileSync(resolve(process.cwd(), 'src/layout/components/TopSubItem.vue'), 'utf8');
const routeUtils = readFileSync(resolve(process.cwd(), 'src/utils/route.ts'), 'utf8');

describe('双列布局空二级菜单', () => {
  it('无可见二级菜单时左侧区域进入窄轨模式', () => {
    expect(layout).toContain("'is-rail-only': !currentRootChildren.length");
    expect(layout).toMatch(/\.app-layout__columns-aside\.is-rail-only\s+\.app-layout__columns-logo/);
    expect(layout).toMatch(/\.app-layout__columns-aside\.is-rail-only\s+\.app-layout__columns-logo\s+:deep\(\.app-logo\)/);
  });

  it('顶层单页面的空路径包装子路由不视为二级菜单', () => {
    expect(routeUtils).toContain('getVisibleMenuChildren');
    expect(routeUtils).toContain("child.path !== ''");
    expect(layout).toContain('getVisibleMenuChildren(root)');
    expect(topMenu).toContain('getVisibleMenuChildren(item)');
    expect(columnsRail).toContain('getVisibleMenuChildren(item)');
    expect(sidebarItem).toContain('getVisibleMenuChildren(props.route)');
    expect(topSubItem).toContain('getVisibleMenuChildren(props.route)');
  });
});
