import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const layout = readFileSync(resolve(process.cwd(), 'src/layout/index.vue'), 'utf8');

describe('双列布局空二级菜单', () => {
  it('无可见二级菜单时左侧区域进入窄轨模式', () => {
    expect(layout).toContain("'is-rail-only': !currentRootChildren.length");
    expect(layout).toMatch(/\.app-layout__columns-aside\.is-rail-only\s+\.app-layout__columns-logo/);
    expect(layout).toMatch(/\.app-layout__columns-aside\.is-rail-only\s+\.app-layout__columns-logo\s+:deep\(\.app-logo\)/);
  });
});
