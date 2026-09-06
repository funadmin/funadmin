import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const source = readFileSync(
  resolve(process.cwd(), 'src/views/system/menu/components/MenuFormDialog.vue'),
  'utf8'
);
const sidebarItem = readFileSync(resolve(process.cwd(), 'src/layout/components/SidebarItem.vue'), 'utf8');
const topMenu = readFileSync(resolve(process.cwd(), 'src/layout/components/TopMenu.vue'), 'utf8');
const topSubItem = readFileSync(resolve(process.cwd(), 'src/layout/components/TopSubItem.vue'), 'utf8');
const iconGenerator = readFileSync(resolve(process.cwd(), 'scripts/generate-ep-icons.mjs'), 'utf8');
const iconMigrationPath = resolve(process.cwd(), '../database/migrations/048_admin_menu_icon_completion.sql');

describe('菜单表单图标字段', () => {
  it('图标选择器不能放在无指令的原生 template 中', () => {
    expect(source).toContain('<el-form-item label="图标" prop="icon">');
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
    expect(iconGenerator).toContain("resolve('../database/migrations')");
    expect(iconGenerator).toContain("'.sql'");
  });

  it('增量迁移为全部 Admin Web 菜单补齐图标字段', () => {
    const migration = readFileSync(iconMigrationPath, 'utf8');
    expect(migration).toContain("WHERE `menu`.`source_type` = 'admin_web'");
    expect(migration).toMatch(/COALESCE\(NULLIF\(TRIM\(`menu`\.`icon`\), ''\), ''\) = ''/);
    expect(migration).toContain("THEN 'i-ep-folder'");
    expect(migration).toContain("ELSE 'i-ep-document'");
  });
});
