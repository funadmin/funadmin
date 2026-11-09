import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const source = readFileSync(
  resolve(process.cwd(), 'src/admin/views/system/menu/components/MenuFormDialog.vue'),
  'utf8'
);
const sidebarItem = readFileSync(resolve(process.cwd(), 'src/admin/layout/components/SidebarItem.vue'), 'utf8');
const topMenu = readFileSync(resolve(process.cwd(), 'src/admin/layout/components/TopMenu.vue'), 'utf8');
const topSubItem = readFileSync(resolve(process.cwd(), 'src/admin/layout/components/TopSubItem.vue'), 'utf8');
const iconGenerator = readFileSync(resolve(process.cwd(), 'scripts/generate-ep-icons.mjs'), 'utf8');
const iconStyles = readFileSync(resolve(process.cwd(), 'src/shared/styles/ep-icons.css'), 'utf8');

describe('菜单表单图标字段', () => {
  it('图标选择器不能放在无指令的原生 template 中', () => {
    expect(source).toContain(`<el-form-item :label="t('systemMenu.icon', '图标')" prop="icon">`);
    expect(source).toContain('<IconSelect v-model="form.icon" />');
    expect(source).not.toMatch(/<template>\s*<el-row[\s\S]*?<IconSelect v-model="form\.icon" \/>[\s\S]*?<\/template>/);
  });

  it('所有菜单布局都为缺失图标提供目录或页面图标', () => {
    expect(sidebarItem).toContain('<i v-else class="i-ep-document app-menu-icon" />');
    expect(sidebarItem).toContain('<i v-else class="i-ep-folder app-menu-icon" />');
    expect(topMenu).toContain("item.meta?.icon || (hasChildren(item) ? 'i-ep-folder' : 'i-ep-document')");
    expect(topSubItem).toContain("route.meta?.icon || (visibleChildren.length ? 'i-ep-folder' : 'i-ep-document')");
  });

  it('图标样式生成器扫描数据库菜单迁移中的图标类', () => {
    expect(iconGenerator).toContain("resolve('../admin/database/core/migrations')");
    expect(iconGenerator).toContain("'.sql'");
  });

  it('开发工具菜单图标具有稳定 safelist 来源和生成样式', () => {
    expect(iconGenerator).toContain('menuIconSafelist');
    for (const iconClass of ['i-ep-briefcase', 'i-ep-coin']) {
      expect(iconGenerator).toContain(`'${iconClass}'`);
      expect(iconStyles).toContain(`.${iconClass}{`);
    }
  });
});
