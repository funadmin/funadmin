import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');
const migration = readProjectFile('database/migrations/015_title_to_name.sql');

describe('015 title→name 改名迁移契约', () => {
  const tables = ['fun_admin_log', 'fun_admin_menu', 'fun_attach_group', 'fun_auth_group', 'fun_permission'];

  it.each(tables)('%s 的 title 改名必须由 information_schema 守卫且可重跑', (table) => {
    expect(migration).toContain(`TABLE_NAME = '${table}' AND COLUMN_NAME = 'title'`);
    expect(migration).toContain('CHANGE COLUMN `title` `name`');
  });

  it('改名通过 PREPARE 动态执行，未执行时回退 DO 0', () => {
    expect(migration).toContain('PREPARE stmt FROM @sql');
    expect(migration).toContain('EXECUTE stmt');
    expect(migration).toContain('DEALLOCATE PREPARE stmt');
    expect(migration).toContain("'DO 0'");
  });

  it('角色表 title 唯一索引随列同步改名为 name', () => {
    expect(migration).toContain('RENAME INDEX `title` TO `name`');
  });

  it('015 后的 permission 写入必须使用 name，不得继续写入 title', () => {
    const laterMigrations = ['027_plugin_purge_permission.sql'];

    for (const migrationName of laterMigrations) {
      const laterMigration = readProjectFile(`database/migrations/${migrationName}`);
      const permissionInsertColumns = laterMigration.match(
        /INSERT\s+INTO\s+`fun_permission`\s*\(([^)]+)\)/i
      )?.[1] ?? '';

      expect(permissionInsertColumns, migrationName).toMatch(/`name`/i);
      expect(permissionInsertColumns, migrationName).not.toMatch(/`title`/i);
    }
  });

  it('迁移不包含裸破坏性语句', () => {
    expect(migration).not.toMatch(/^(DROP|TRUNCATE|RENAME)\s/mi);
  });

  it('后端菜单 API 以 name 暴露名称并以 routeName 暴露路由名', () => {
    const authService = readProjectFile('app/backend/service/AuthService.php');
    const systemMenu = readProjectFile('app/backend/controller/system/SystemMenu.php');
    for (const source of [authService, systemMenu]) {
      expect(source).toContain("'routeName' => (string) ($meta['name'] ?? ('Menu_' . (int) $menu->id))");
      expect(source).toContain("'name' => (string) $menu->name,");
      expect(source).not.toContain('$menu->title');
    }
  });

  it('角色与权限 API 统一使用 name 字段', () => {
    const role = readProjectFile('app/backend/controller/system/SystemRole.php');
    const permission = readProjectFile('app/backend/controller/system/SystemPermission.php');
    expect(role).toContain("'name' => (string) $role->name,");
    expect(role).not.toContain('$role->title');
    expect(permission).toContain("'name' => (string) $permission->name,");
    expect(permission).not.toContain('$permission->title');
  });

  it('前端路由转换从 routeName 取路由名、从 name 取标题', () => {
    const dynamic = readProjectFile('admin-web/src/router/dynamic.ts');
    expect(dynamic).toContain('menu.routeName');
    expect(dynamic).toContain('title: menu.name,');
    expect(dynamic).not.toContain('menu.title');
  });
});
